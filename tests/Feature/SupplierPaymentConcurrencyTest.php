<?php

namespace Tests\Feature;

use App\Domain\Purchasing\BillBalance;
use App\Domain\Purchasing\RecordPurchaseBill;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RunsConcurrentOperations;
use Tests\TestCase;

class SupplierPaymentConcurrencyTest extends TestCase
{
    use DatabaseMigrations, RunsConcurrentOperations;

    protected function tearDown(): void
    {
        DB::statement('TRUNCATE purchase_bills CASCADE');
        parent::tearDown();
    }

    public function test_simultaneous_retries_and_competing_sources_do_not_duplicate_or_overpay(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $supplier = Supplier::create(['name' => 'مورد اختبار', 'created_by' => $owner->id, 'updated_by' => $owner->id]);
        $bill = app(RecordPurchaseBill::class)->execute($owner, ['operation_id' => (string) Str::uuid(), 'supplier_id' => $supplier->id,
            'reference' => 'TEST-BILL', 'bill_date' => now('Asia/Riyadh')->toDateString(), 'description' => 'بضاعة اختبار',
            'total' => '115.10', 'printed_tax' => null, 'confirmed_unpaid' => true]);
        $input = ['action' => 'supplier_payment', 'user_id' => $owner->id, 'bill_id' => $bill->id, 'data' => [
            'operation_id' => (string) Str::uuid(), 'payment_date' => $bill->bill_date, 'amount' => '15.10', 'method' => 'bank',
            'reference' => 'TEST-PAY-1', 'funding_reference' => 'حساب شركة تجريبي', 'actual_payer' => 'دافع اختبار', 'confirmed_paid_from_company' => true]];
        $results = $this->race([$input, $input]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $input['data'] = [...$input['data'], 'operation_id' => (string) Str::uuid(), 'reference' => 'TEST-PAY-2', 'amount' => '70'];
        $other = $input;
        $other['data'] = [...$input['data'], 'operation_id' => (string) Str::uuid(), 'reference' => 'TEST-PAY-3', 'method' => 'cash_reserve'];
        $competing = $this->race([$input, $other]);
        $this->assertCount(1, array_filter($competing, fn ($result) => $result['ok']));
        $this->assertCount(1, array_filter($competing, fn ($result) => isset($result['errors']['amount'])));
        $this->assertDatabaseCount('supplier_payments', 2);
        $this->assertDatabaseCount('journal_entries', 3);
        $this->assertSame(['paid' => '85.10', 'remaining' => '30.00'], app(BillBalance::class)->calculate($bill));
    }
}
