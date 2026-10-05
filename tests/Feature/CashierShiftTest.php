<?php

namespace Tests\Feature;

use App\Domain\Sales\BasketCalculator;
use App\Domain\Shifts\OpenShift;
use App\Domain\Stock\RecordOpeningStock;
use App\Enums\UserRole;
use App\Models\PosStation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashierShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private PosStation $station;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::factory()->create();
        $this->station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق 1']);
    }

    private function opening(): array
    {
        return ['operation_id' => (string) Str::uuid(), 'station_id' => $this->station->id, 'opening_cash' => '100.00'];
    }

    private function saleInput(int $shiftId): array
    {
        $product = Product::create(['barcode' => '00001', 'name' => 'صنف اختبار', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00']);
        app(RecordOpeningStock::class)->execute(User::factory()->create(['role' => UserRole::Owner]), [
            'product_id' => $product->id, 'quantity' => '3', 'opening_date' => now()->toDateString(), 'reference' => 'اختبار وردية',
        ]);
        $items = [['product_id' => $product->id, 'quantity' => '2']];
        $quote = app(BasketCalculator::class)->calculate($items, Product::all()->keyBy('id'));

        return ['operation_id' => (string) Str::uuid(), 'station_id' => $this->station->id, 'shift_id' => $shiftId, 'items' => $items,
            'quote_fingerprint' => $quote['fingerprint'], 'cash_tender' => '150.00', 'card_amount' => '100.00', 'card_confirmed' => true];
    }

    public function test_opening_is_owned_idempotent_and_does_not_post_cash_or_income(): void
    {
        $input = $this->opening();
        $this->postJson('/shifts', $input)->assertUnauthorized();
        $id = $this->actingAs($this->cashier)->postJson('/shifts', $input)->assertOk()->json('id');
        $this->postJson('/shifts', [...$input, 'opening_cash' => '100'])->assertOk()->assertJsonPath('id', $id);
        $this->postJson('/shifts', [...$input, 'opening_cash' => '101'])->assertConflict();
        $this->postJson('/shifts', $this->opening())->assertUnprocessable()->assertJsonValidationErrors('station_id');
        $other = PosStation::create(['code' => 'POS-2', 'name' => 'صندوق 2']);
        $this->postJson('/shifts', [...$this->opening(), 'station_id' => $other->id])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson('/shifts', $this->opening())->assertUnprocessable();
        $this->postJson('/shifts', $input)->assertConflict();
        $this->get('/shifts/'.$id)->assertForbidden();
        $this->post('/shifts/'.$id.'/close', ['counted_cash' => '100'])->assertForbidden();
        $this->assertDatabaseCount('cashier_shifts', 1);
        $this->assertDatabaseCount('sale_payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->get('/shifts/'.$id)->assertOk();
        $this->post('/shifts/'.$id.'/close', ['counted_cash' => '100'])->assertForbidden();
    }

    public function test_closing_preserves_applied_cash_card_change_variance_and_exact_recovery(): void
    {
        $opening = $this->opening();
        $shift = app(OpenShift::class)->execute($this->cashier, $opening);
        $input = $this->saleInput($shift->id);
        $saleId = $this->actingAs($this->cashier)->postJson('/pos/sales', $input)->assertOk()->assertJsonPath('change', '20.00')->json('id');
        $this->post('/shifts/'.$shift->id.'/close', ['counted_cash' => '225', 'closing_note' => 'عجز تجريبي'])->assertRedirect('/shifts/'.$shift->id);
        $this->assertDatabaseHas('cashier_shifts', ['id' => $shift->id, 'sale_count' => 1, 'sales_total' => '230.00',
            'cash_sales' => '130.00', 'card_sales' => '100.00', 'expected_cash' => '230.00', 'counted_cash' => '225.00', 'difference' => '-5.00']);
        $closedAt = $shift->fresh()->closed_at;
        $this->post('/shifts/'.$shift->id.'/close', ['counted_cash' => '225.00', 'closing_note' => 'عجز تجريبي'])->assertRedirect();
        $this->assertEquals($closedAt, $shift->fresh()->closed_at);
        $this->postJson('/shifts/'.$shift->id.'/close', ['counted_cash' => '230'])->assertConflict();
        $this->postJson('/shifts', $opening)->assertOk()->assertJsonPath('id', $shift->id);
        $this->postJson('/pos/sales', $input)->assertOk()->assertJsonPath('id', $saleId);
        $next = $this->postJson('/shifts', $this->opening())->assertOk()->json('id');
        $this->postJson('/pos/sales', [...$input, 'operation_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('station_id');
        $this->postJson('/pos/payment-preview', $input)->assertUnprocessable();
        $this->postJson('/pos/sales', [...$input, 'shift_id' => $next])->assertConflict();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->get('/shifts/'.$shift->id)->assertInertia(fn (Assert $page) => $page->component('shifts/show')->where('summary.expected_cash', '230.00')->has('sales.data', 1));
    }

    public function test_new_sales_require_the_cashiers_exact_open_shift_but_old_sales_still_recover(): void
    {
        $shift = app(OpenShift::class)->execute($this->cashier, $this->opening());
        $input = $this->saleInput($shift->id);
        $legacy = $input;
        unset($legacy['shift_id']);
        $this->actingAs($this->cashier)->postJson('/pos/sales', $legacy)->assertUnprocessable()->assertJsonValidationErrors('station_id');
        $this->actingAs(User::factory()->create())->postJson('/pos/sales', $input)->assertUnprocessable();
        $this->postJson('/pos/payment-preview', $input)->assertUnprocessable();
        $id = $this->actingAs($this->cashier)->postJson('/pos/sales', $input)->assertOk()->json('id');
        // Represent a sale committed before this migration, using the previous canonical identity.
        $oldHash = hash('sha256', json_encode([$input['station_id'], [['product_id' => $input['items'][0]['product_id'], 'quantity' => '2.000']],
            $input['quote_fingerprint'], '150.00', '100.00', true, null], JSON_THROW_ON_ERROR));
        DB::table('sales')->where('id', $id)->update(['shift_id' => null, 'request_hash' => $oldHash]);
        $this->postJson('/pos/sales', $legacy)->assertOk()->assertJsonPath('id', $id);
        $this->post('/shifts/'.$shift->id.'/close', ['counted_cash' => '105'])->assertRedirect();
        $this->assertDatabaseHas('cashier_shifts', ['id' => $shift->id, 'sale_count' => 0, 'cash_sales' => '0.00', 'card_sales' => '0.00', 'expected_cash' => '100.00', 'difference' => '5.00']);
        $this->assertNull(Sale::findOrFail($id)->shift_id);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_validation_and_history_filter_do_not_expose_another_cashiers_shift(): void
    {
        $this->actingAs($this->cashier)->postJson('/shifts', [...$this->opening(), 'opening_cash' => '-1'])->assertUnprocessable();
        $this->postJson('/shifts', [...$this->opening(), 'opening_cash' => '0.001'])->assertUnprocessable();
        $this->station->update(['is_active' => false]);
        $this->postJson('/shifts', $this->opening())->assertUnprocessable();
        $this->station->update(['is_active' => true]);
        $shift = app(OpenShift::class)->execute($this->cashier, $this->opening());
        $this->postJson('/shifts/'.$shift->id.'/close', ['counted_cash' => '-1'])->assertUnprocessable();
        $this->assertNull($shift->fresh()->closed_at);
        $this->actingAs(User::factory()->create())->get('/shifts')->assertInertia(fn (Assert $page) => $page->has('shifts.data', 0)->where('activeShift', null));
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]))->get('/shifts')->assertInertia(fn (Assert $page) => $page->has('shifts.data', 1));
    }
}
