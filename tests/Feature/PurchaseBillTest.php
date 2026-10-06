<?php

namespace Tests\Feature;

use App\Domain\Accounting\JournalPoster;
use App\Domain\Purchasing\RecordPurchaseBill;
use App\Enums\UserRole;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class PurchaseBillTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $supplier = Supplier::create(['name' => 'مورد اختبار', 'created_by' => $owner->id, 'updated_by' => $owner->id]);
        $data = ['operation_id' => (string) Str::uuid(), 'supplier_id' => $supplier->id, 'reference' => 'TEST-BILL-1',
            'bill_date' => now('Asia/Riyadh')->toDateString(), 'description' => 'بضاعة اختبار', 'total' => '115.10', 'printed_tax' => '15.01', 'confirmed_unpaid' => true];

        return [$owner, $supplier, $data];
    }

    public function test_bill_and_balanced_journal_commit_together_without_stock_or_payment_effects(): void
    {
        [$owner, $supplier, $data] = $this->context();
        $id = $this->actingAs($owner)->postJson('/purchases', [...$data, 'created_by' => 999, 'supplier_snapshot' => ['name' => 'مزور']])->assertOk()->json('id');
        $bill = PurchaseBill::findOrFail($id);
        $this->assertSame('115.10', $bill->total);
        $this->assertSame('15.01', $bill->printed_tax);
        $this->assertSame($owner->id, $bill->created_by);
        $this->assertSame($supplier->name, $bill->supplier_snapshot['name']);
        $this->assertDatabaseHas('journal_lines', ['account_code' => 'purchase_review', 'debit' => '115.10', 'credit' => '0.00']);
        $this->assertDatabaseHas('journal_lines', ['account_code' => 'supplier_payable', 'debit' => '0.00', 'credit' => '115.10']);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('sale_payments', 0);
        $supplier->update(['name' => 'اسم جديد', 'tax_number' => 'TEST-TAX', 'is_active' => false]);
        $this->get('/purchases/'.$id)->assertInertia(fn (Assert $page) => $page->where('bill.supplier_snapshot.name', 'مورد اختبار')->where('bill.supplier_snapshot.tax_number', null)->has('journal', 2));
        $this->postJson('/purchases', $data)->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('purchase_bills', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->postJson('/purchases', [...$data, 'total' => '120'])->assertConflict();
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->postJson('/purchases', $data)->assertConflict();
        $this->putJson('/purchases/'.$id, $data)->assertStatus(405);
        $this->deleteJson('/purchases/'.$id)->assertStatus(405);
    }

    public function test_unknown_tax_is_distinct_from_verified_zero_and_duplicate_documents_are_blocked(): void
    {
        [$owner, , $data] = $this->context();
        $this->actingAs($owner)->postJson('/purchases', [...$data, 'printed_tax' => null])->assertOk();
        $this->assertNull(PurchaseBill::firstOrFail()->printed_tax);
        $this->postJson('/purchases', [...$data, 'operation_id' => (string) Str::uuid(), 'reference' => 'test-bill-1'])->assertUnprocessable();
        $this->postJson('/purchases', [...$data, 'operation_id' => (string) Str::uuid(), 'reference' => 'TEST-BILL-2', 'printed_tax' => '0'])->assertOk();
        $this->assertDatabaseHas('purchase_bills', ['reference' => 'TEST-BILL-2', 'printed_tax' => '0.00']);
        $this->assertDatabaseCount('purchase_bills', 2);
    }

    public function test_permissions_and_validation_prevent_invalid_liabilities(): void
    {
        [$owner, $supplier, $data] = $this->context();
        $this->postJson('/purchases', $data)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->get('/purchases')->assertForbidden();
        $this->get('/suppliers/'.$supplier->id.'/purchase-bill')->assertForbidden();
        $this->postJson('/purchases', $data)->assertForbidden();
        $this->actingAs($owner);
        $this->postJson('/purchases', [...$data, 'printed_tax' => '120'])->assertUnprocessable()
            ->assertJsonPath('errors.printed_tax.0', 'الضريبة لا يمكن أن تتجاوز إجمالي المستند.');
        foreach ([['total' => '0'], ['total' => '1.001'], ['printed_tax' => '116'], ['printed_tax' => '-1'], ['confirmed_unpaid' => false], ['bill_date' => now()->addDay()->toDateString()]] as $invalid) {
            $this->postJson('/purchases', [...$data, ...$invalid])->assertUnprocessable();
        }
        $supplier->update(['is_active' => false]);
        $this->postJson('/purchases', $data)->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
        $this->assertDatabaseCount('purchase_bills', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_journal_failure_rolls_back_the_bill_and_retry_can_finish(): void
    {
        [$owner, , $data] = $this->context();
        $this->mock(JournalPoster::class, fn (MockInterface $mock) => $mock->shouldReceive('post')->once()->andThrow(new \RuntimeException('Test posting failure')));
        try {
            app(RecordPurchaseBill::class)->execute($owner, $data);
            $this->fail('Expected posting failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Test posting failure', $error->getMessage());
        }
        $this->assertDatabaseCount('purchase_bills', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->app->forgetInstance(JournalPoster::class);
        app(RecordPurchaseBill::class)->execute($owner, $data);
        $this->assertDatabaseCount('purchase_bills', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame('0.00', bcsub((string) DB::table('journal_lines')->sum('debit'), (string) DB::table('journal_lines')->sum('credit'), 2));
    }
}
