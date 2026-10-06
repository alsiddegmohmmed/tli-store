<?php

namespace Tests\Feature;

use App\Domain\Evidence\RecordEvidence;
use App\Domain\Purchasing\RecordPurchaseBill;
use App\Domain\Purchasing\RecordSupplierPayment;
use App\Enums\UserRole;
use App\Models\DocumentEvidence;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function image(string $name = 'proof.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jLxoAAAAASUVORK5CYII='));
    }

    private function context(): array
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $supplier = Supplier::create(['name' => 'مورد اختبار', 'created_by' => $owner->id, 'updated_by' => $owner->id]);
        $bill = app(RecordPurchaseBill::class)->execute($owner, ['operation_id' => (string) Str::uuid(), 'supplier_id' => $supplier->id,
            'reference' => 'TEST-BILL', 'bill_date' => now('Asia/Riyadh')->toDateString(), 'description' => 'بضاعة اختبار',
            'total' => '115.10', 'printed_tax' => null, 'confirmed_unpaid' => true]);

        return [$owner, $bill];
    }

    public function test_evidence_is_private_linked_and_deduplicated_without_financial_effects(): void
    {
        [$owner, $bill] = $this->context();
        $url = '/purchases/'.$bill->id.'/evidence';
        $this->actingAs($owner)->post($url, ['file' => $this->image(), 'kind' => 'non_tax_receipt', 'created_by' => 999,
            'paper_location' => 'ملف اختبار'])->assertRedirect('/purchases/'.$bill->id);
        $evidence = DocumentEvidence::firstOrFail();
        $this->assertSame($owner->id, $evidence->created_by);
        $this->assertSame($bill->id, $evidence->purchase_bill_id);
        $this->assertNull($evidence->supplier_payment_id);
        Storage::disk('local')->assertExists($evidence->storage_path);
        $this->assertArrayNotHasKey('storage_path', $evidence->toArray());
        $this->assertArrayNotHasKey('sha256', $evidence->toArray());
        $this->get('/purchases/'.$bill->id)->assertInertia(fn (Assert $page) => $page->has('evidence.data', 1)
            ->where('evidence.data.0.original_name', 'proof.png')->missing('evidence.data.0.storage_path'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->post($url, ['file' => $this->image('renamed.png'),
            'kind' => 'other', 'paper_location' => 'اسم جديد'])->assertRedirect();
        $this->assertDatabaseCount('document_evidence', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame('non_tax_receipt', $evidence->fresh()->kind);
        $this->get('/evidence/'.$evidence->id.'/download')->assertOk()->assertDownload('document-'.$evidence->id.'.png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseCount('purchase_bills', 1);
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertNull($bill->fresh()->printed_tax);
        $this->actingAs(User::factory()->create())->get('/evidence/'.$evidence->id.'/download')->assertForbidden();
        $this->postJson($url, ['file' => $this->image(), 'kind' => 'other'])->assertForbidden();
        $this->actingAs($owner)->delete('/evidence/'.$evidence->id)->assertNotFound();
    }

    public function test_content_and_size_validation_reject_unsafe_or_inappropriate_uploads(): void
    {
        [$owner, $bill] = $this->context();
        $url = '/purchases/'.$bill->id.'/evidence';
        $this->actingAs($owner);
        $temporary = tmpfile();
        fwrite($temporary, '<html>Not an invoice</html>');
        $spoof = new UploadedFile(stream_get_meta_data($temporary)['uri'], 'invoice.pdf', 'application/pdf', null, true);
        try {
            $this->assertSame('text/html', $spoof->getMimeType());
            $this->postJson($url, ['file' => $spoof, 'kind' => 'tax_invoice'])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        } finally {
            fclose($temporary);
        }
        $this->postJson($url, ['file' => UploadedFile::fake()->create('large.pdf', 2049, 'application/pdf'), 'kind' => 'tax_invoice'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($url, ['file' => $this->image(), 'kind' => 'bank_proof'])->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->assertDatabaseCount('document_evidence', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_payment_evidence_cannot_attach_to_another_bill_or_establish_tax(): void
    {
        [$owner, $bill] = $this->context();
        $payment = app(RecordSupplierPayment::class)->execute($owner, $bill->id, ['operation_id' => (string) Str::uuid(),
            'payment_date' => $bill->bill_date, 'amount' => '50', 'method' => 'bank', 'reference' => 'TEST-PAY',
            'funding_reference' => 'حساب اختبار', 'actual_payer' => 'دافع اختبار', 'confirmed_paid_from_company' => true]);
        $url = '/purchases/'.$bill->id.'/payments/'.$payment->id.'/evidence';
        $this->actingAs($owner)->post($url, ['file' => $this->image(), 'kind' => 'bank_proof'])->assertRedirect();
        $evidence = DocumentEvidence::firstOrFail();
        $this->assertSame($payment->id, $evidence->supplier_payment_id);
        $this->assertNull($evidence->purchase_bill_id);
        $this->postJson($url, ['file' => $this->image(), 'kind' => 'tax_invoice'])->assertUnprocessable();
        $other = app(RecordPurchaseBill::class)->execute($owner, ['operation_id' => (string) Str::uuid(), 'supplier_id' => $bill->supplier_id,
            'reference' => 'TEST-BILL-2', 'bill_date' => $bill->bill_date, 'description' => 'بضاعة اختبار',
            'total' => '115.10', 'printed_tax' => null, 'confirmed_unpaid' => true]);
        $this->postJson('/purchases/'.$other->id.'/payments/'.$payment->id.'/evidence', ['file' => $this->image(), 'kind' => 'bank_proof'])->assertNotFound();
        $this->assertDatabaseCount('document_evidence', 1);
        $this->assertDatabaseCount('supplier_payments', 1);
        $this->assertDatabaseCount('journal_entries', 3);
        $this->assertNull($bill->fresh()->printed_tax);
    }

    public function test_database_failure_removes_the_file_and_missing_file_is_reported(): void
    {
        [$owner, $bill] = $this->context();
        DocumentEvidence::creating(fn () => throw new \RuntimeException('Test evidence failure'));
        try {
            app(RecordEvidence::class)->execute($owner, $bill, $this->image(), 'other', null);
            $this->fail('Expected evidence failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Test evidence failure', $error->getMessage());
        } finally {
            DocumentEvidence::flushEventListeners();
        }
        $this->assertDatabaseCount('document_evidence', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $saved = app(RecordEvidence::class)->execute($owner, $bill, $this->image(), 'other', null);
        Storage::disk('local')->delete($saved->storage_path);
        $this->actingAs($owner)->get('/evidence/'.$saved->id.'/download')->assertNotFound();
        $this->assertDatabaseCount('document_evidence', 1);
    }
}
