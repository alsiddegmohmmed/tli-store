<?php

namespace Tests\Feature;

use App\Domain\Accounting\JournalPoster;
use App\Domain\Purchasing\BillBalance;
use App\Domain\Purchasing\RecordPurchaseBill;
use App\Domain\Purchasing\RecordSupplierPayment;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class SupplierPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $supplier = Supplier::create(['name' => 'مورد اختبار', 'created_by' => $owner->id, 'updated_by' => $owner->id]);
        $bill = app(RecordPurchaseBill::class)->execute($owner, ['operation_id' => (string) Str::uuid(), 'supplier_id' => $supplier->id,
            'reference' => 'TEST-BILL', 'bill_date' => now('Asia/Riyadh')->toDateString(), 'description' => 'بضاعة اختبار',
            'total' => '115.10', 'printed_tax' => null, 'confirmed_unpaid' => true]);
        $data = ['operation_id' => (string) Str::uuid(), 'payment_date' => $bill->bill_date, 'amount' => '50.00', 'method' => 'bank',
            'reference' => 'TEST-PAY-1', 'funding_reference' => 'حساب شركة تجريبي', 'actual_payer' => 'دافع اختبار', 'confirmed_paid_from_company' => true];

        return [$owner, $bill, $data];
    }

    public function test_partial_and_full_payments_reduce_debt_once_with_separate_journals(): void
    {
        [$owner, $bill, $data] = $this->context();
        $this->actingAs($owner);
        $url = '/purchases/'.$bill->id.'/payments';
        $id = $this->postJson($url, [...$data, 'created_by' => 999])->assertOk()->json('id');
        $this->assertSame(['paid' => '50.00', 'remaining' => '65.10'], app(BillBalance::class)->calculate($bill));
        $this->assertDatabaseHas('supplier_payments', ['id' => $id, 'created_by' => $owner->id, 'actual_payer' => 'دافع اختبار']);
        $this->assertDatabaseHas('journal_lines', ['account_code' => 'supplier_payable', 'debit' => '50.00']);
        $this->assertDatabaseHas('journal_lines', ['account_code' => 'company_bank', 'credit' => '50.00']);
        $this->postJson($url, $data)->assertOk()->assertJsonPath('id', $id);
        $this->postJson($url, [...$data, 'amount' => '49'])->assertConflict();
        $this->postJson($url, [...$data, 'actual_payer' => 'دافع آخر'])->assertConflict();
        $this->postJson($url, [...$data, 'operation_id' => (string) Str::uuid(), 'reference' => 'test-pay-1'])->assertUnprocessable();
        $this->postJson($url, [...$data, 'operation_id' => (string) Str::uuid(), 'reference' => 'TEST-PAY-2', 'amount' => '65.11'])->assertUnprocessable();
        $this->postJson($url, [...$data, 'operation_id' => (string) Str::uuid(), 'reference' => 'TEST-PAY-2', 'amount' => '65.10', 'method' => 'cash_reserve'])->assertOk();
        $this->postJson($url, $data)->assertOk()->assertJsonPath('id', $id);
        $this->assertSame(['paid' => '115.10', 'remaining' => '0.00'], app(BillBalance::class)->calculate($bill));
        $this->assertDatabaseCount('purchase_bills', 1);
        $this->assertDatabaseCount('supplier_payments', 2);
        $this->assertDatabaseCount('journal_entries', 3);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('sales', 0);
        $this->assertNull($bill->fresh()->printed_tax);
        $this->get('/purchases?supplier_id='.$bill->supplier_id)->assertOk();
        $this->get('/purchases')->assertInertia(fn (Assert $page) => $page->where('bills.data.0.balance.remaining', '0.00'));
        $this->get('/purchases/'.$bill->id)->assertInertia(fn (Assert $page) => $page->where('balance.paid', '115.10')->has('payments.data', 2));
        $this->get($url.'/'.$id)->assertInertia(fn (Assert $page) => $page->component('purchases/payment')->has('journal', 2));
        $this->putJson($url.'/'.$id, $data)->assertStatus(405);
        $this->deleteJson($url.'/'.$id)->assertStatus(405);
    }

    public function test_permissions_confirmation_and_source_validation_protect_payments(): void
    {
        [$owner, $bill, $data] = $this->context();
        $url = '/purchases/'.$bill->id.'/payments';
        $this->postJson($url, $data)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($url, $data)->assertForbidden();
        $this->actingAs($owner);
        foreach ([['confirmed_paid_from_company' => false], ['amount' => '0'], ['amount' => '0.001'], ['method' => 'personal'], ['actual_payer' => ''],
            ['funding_reference' => ''], ['payment_date' => now()->addDay()->toDateString()], ['payment_date' => now()->subDay()->toDateString()]] as $invalid) {
            $this->postJson($url, [...$data, ...$invalid])->assertUnprocessable();
        }
        $this->assertDatabaseCount('supplier_payments', 0);
        $id = $this->postJson($url, $data)->assertOk()->json('id');
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->get($url.'/'.$id)->assertOk();
        $this->postJson($url, $data)->assertConflict();
        $this->actingAs(User::factory()->create())->get($url.'/'.$id)->assertForbidden();
    }

    public function test_accounting_failure_rolls_back_payment_and_allows_same_attempt_recovery(): void
    {
        [$owner, $bill, $data] = $this->context();
        $this->mock(JournalPoster::class, fn (MockInterface $mock) => $mock->shouldReceive('post')->once()->andThrow(new \RuntimeException('Test payment failure')));
        try {
            app(RecordSupplierPayment::class)->execute($owner, $bill->id, $data);
            $this->fail('Expected posting failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Test payment failure', $error->getMessage());
        }
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame('115.10', app(BillBalance::class)->calculate($bill)['remaining']);
        $this->app->forgetInstance(JournalPoster::class);
        app(RecordSupplierPayment::class)->execute($owner, $bill->id, $data);
        $this->assertDatabaseCount('supplier_payments', 1);
        $this->assertSame('65.10', app(BillBalance::class)->calculate($bill)['remaining']);
    }
}
