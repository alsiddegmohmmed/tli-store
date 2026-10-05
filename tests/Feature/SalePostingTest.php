<?php

namespace Tests\Feature;

use App\Domain\Accounting\JournalPoster;
use App\Domain\Sales\RecordSale;
use App\Domain\Stock\RecordOpeningStock;
use App\Enums\UserRole;
use App\Models\InventoryBalance;
use App\Models\PosStation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SalePostingTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $product;

    private PosStation $station;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::factory()->create();
        $this->product = Product::create(['barcode' => '00001', 'name' => 'صنف اختبار', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00']);
        $this->station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق 1']);
        app(RecordOpeningStock::class)->execute(User::factory()->create(['role' => UserRole::Owner]), [
            'product_id' => $this->product->id, 'quantity' => '5', 'opening_date' => now()->toDateString(), 'reference' => 'جرد تجريبي',
        ]);
    }

    private function payload(): array
    {
        $items = [['product_id' => $this->product->id, 'quantity' => '2']];
        $fingerprint = $this->actingAs($this->cashier)->postJson('/pos/quote', ['station_id' => $this->station->id, 'items' => $items])->assertOk()->json('fingerprint');

        return ['operation_id' => (string) Str::uuid(), 'station_id' => $this->station->id, 'items' => $items,
            'quote_fingerprint' => $fingerprint, 'cash_tender' => '150.00', 'card_amount' => '100.00', 'card_confirmed' => true, 'card_reference' => 'TEST-123'];
    }

    public function test_journal_rejects_amounts_whose_database_rounding_could_break_balance(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Journal amounts must be rounded to cents before posting.');
        DB::transaction(fn () => app(JournalPoster::class)->post(1, $this->cashier->id, now()->toDateString(), 'اختبار', [
            ['account_code' => 'cash', 'debit' => '0.014'],
            ['account_code' => 'sales', 'credit' => '0.016'],
        ]));
    }

    public function test_split_sale_records_applied_cash_change_stock_and_a_balanced_journal(): void
    {
        $payload = $this->payload();
        $result = $this->postJson('/pos/sales', $payload)->assertOk()->assertJsonPath('total', '230.00')->assertJsonPath('change', '20.00')->assertJsonPath('fiscal_status', 'not_issued');
        $saleId = $result->json('id');
        $this->assertDatabaseHas('sale_payments', ['sale_id' => $saleId, 'method' => 'cash', 'amount' => '130.00']);
        $this->assertDatabaseHas('sale_payments', ['sale_id' => $saleId, 'method' => 'card', 'amount' => '100.00', 'manually_confirmed' => true]);
        $this->assertSame('3.000', InventoryBalance::findOrFail($this->product->id)->quantity);
        $this->assertDatabaseHas('stock_movements', ['quantity' => '-2.000']);
        $this->assertSame('230.00', DB::table('journal_lines')->sum('debit'));
        $this->assertSame('230.00', DB::table('journal_lines')->sum('credit'));
        $this->assertDatabaseHas('journal_lines', ['account_code' => 'sales', 'credit' => '200.00']);
        $this->assertDatabaseHas('journal_lines', ['account_code' => 'output_vat', 'credit' => '30.00']);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_retry_recovers_the_same_sale_after_catalogue_deactivation_and_preserves_snapshots(): void
    {
        $payload = $this->payload();
        $id = $this->postJson('/pos/sales', $payload)->assertOk()->json('id');
        $this->product->update(['name' => 'اسم معدل', 'price' => '300.00', 'is_active' => false]);
        $this->station->update(['is_active' => false]);
        $payload['cash_tender'] = '150';
        $payload['items'][0]['quantity'] = '2.000';
        $this->postJson('/pos/sales', $payload)->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_payments', 2);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('sale_lines', ['sale_id' => $id, 'name' => 'صنف اختبار', 'unit_price' => '115.00']);
    }

    public function test_operation_key_cannot_be_reused_with_changed_payment_or_another_cashier(): void
    {
        $payload = $this->payload();
        $this->postJson('/pos/sales', $payload)->assertOk();
        $changed = [...$payload, 'cash_tender' => '200.00'];
        $this->postJson('/pos/sales', $changed)->assertConflict();
        $this->actingAs(User::factory()->create())->postJson('/pos/sales', $payload)->assertConflict();
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_stale_price_insufficient_stock_and_unconfirmed_card_reject_without_posting(): void
    {
        $payload = $this->payload();
        $this->postJson('/pos/sales', [...$payload, 'card_confirmed' => false])->assertUnprocessable()->assertJsonValidationErrors('card_confirmed');
        $this->product->update(['price' => '116.00']);
        $this->postJson('/pos/sales', $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->product->update(['price' => '115.00']);
        InventoryBalance::findOrFail($this->product->id)->update(['quantity' => '1']);
        $this->postJson('/pos/sales', $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_journal_failure_rolls_back_sale_payment_and_stock_changes(): void
    {
        $payload = $this->payload();
        DB::table('ledger_accounts')->where('code', 'sales')->delete();
        $this->withoutExceptionHandling();
        try {
            $this->postJson('/pos/sales', $payload);
            $this->fail('Expected the missing ledger account to reject journal posting.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->getCode());
        }
        $this->assertSame('5.000', InventoryBalance::findOrFail($this->product->id)->quantity);
        foreach (['sales', 'sale_lines', 'sale_payments', 'journal_entries', 'journal_lines'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_cashier_can_only_read_their_sales_and_cannot_set_opening_stock(): void
    {
        $payload = $this->payload();
        $id = $this->postJson('/pos/sales', $payload)->assertOk()->json('id');
        $this->actingAs(User::factory()->create())->get('/sales/'.$id)->assertForbidden();
        $this->get('/stock')->assertForbidden();
        $this->postJson('/stock/openings', ['product_id' => $this->product->id, 'quantity' => '10', 'opening_date' => now()->toDateString(), 'reference' => 'غير مصرح'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->get('/sales/'.$id)->assertOk();
        $this->assertSame('3.000', InventoryBalance::findOrFail($this->product->id)->quantity);
    }

    public function test_opening_stock_cannot_be_counted_twice_and_live_checkout_stays_disabled(): void
    {
        $payload = $this->payload();
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]))->postJson('/stock/openings', ['product_id' => $this->product->id,
            'quantity' => '5', 'opening_date' => now()->toDateString(), 'reference' => 'تكرار'])->assertUnprocessable();
        $this->app['env'] = 'production';
        try {
            app(RecordSale::class)->execute($this->cashier, $payload);
            $this->fail('Production checkout must remain disabled.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame('5.000', InventoryBalance::findOrFail($this->product->id)->quantity);
    }
}
