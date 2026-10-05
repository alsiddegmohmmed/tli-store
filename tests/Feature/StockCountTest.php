<?php

namespace Tests\Feature;

use App\Domain\Sales\BasketCalculator;
use App\Domain\Shifts\OpenShift;
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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockCountTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->product = Product::create(['barcode' => '00001', 'name' => 'صنف اختبار', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00']);
        app(RecordOpeningStock::class)->execute($this->owner, ['product_id' => $this->product->id, 'quantity' => '5', 'opening_date' => now()->toDateString(), 'reference' => 'جرد افتتاحي']);
    }

    private function input(string $quantity = '3.125'): array
    {
        $balance = InventoryBalance::findOrFail($this->product->id);

        return ['operation_id' => (string) Str::uuid(), 'product_id' => $this->product->id, 'expected_quantity' => $balance->quantity,
            'expected_revision' => $balance->revision, 'counted_quantity' => $quantity, 'reference' => 'TEST-COUNT', 'reason' => 'تصحيح كمية من محضر موثق'];
    }

    public function test_count_posts_only_the_difference_and_replay_preserves_snapshot_after_later_changes(): void
    {
        $input = $this->input();
        $id = $this->actingAs($this->owner)->postJson('/stock/counts', $input)->assertOk()->json('id');
        $this->assertSame('3.125', InventoryBalance::findOrFail($this->product->id)->quantity);
        $this->assertSame(1, InventoryBalance::findOrFail($this->product->id)->revision);
        $this->assertDatabaseHas('stock_counts', ['id' => $id, 'recorded_quantity' => '5.000', 'counted_quantity' => '3.125', 'difference' => '-1.875', 'balance_revision' => 0]);
        $this->assertDatabaseHas('stock_movements', ['stock_count_id' => $id, 'quantity' => '-1.875']);
        $this->postJson('/stock/counts', $this->input('8'))->assertOk();
        $this->product->update(['name' => 'اسم معدل', 'barcode' => '00002']);
        $this->postJson('/stock/counts', [...$input, 'expected_quantity' => '5', 'counted_quantity' => '3.125'])->assertOk()->assertJsonPath('id', $id);
        $this->assertSame('8.000', InventoryBalance::findOrFail($this->product->id)->quantity);
        $this->assertSame('8.000', bcadd((string) DB::table('stock_movements')->sum('quantity'), '0', 3));
        $this->assertDatabaseCount('stock_counts', 2);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('sale_payments', 0);
        $this->get('/stock/counts/'.$id)->assertInertia(fn (Assert $page) => $page->component('stock/count')->where('count.product_snapshot.name', 'صنف اختبار')->where('count.product_snapshot.barcode', '00001'));
        $this->put('/stock/counts/'.$id, $input)->assertMethodNotAllowed();
        $this->delete('/stock/counts/'.$id)->assertMethodNotAllowed();
    }

    public function test_changed_attempt_unauthorized_staff_and_missing_opening_cannot_post(): void
    {
        $input = $this->input();
        $this->postJson('/stock/counts', $input)->assertUnauthorized();
        $cashier = User::factory()->create();
        $this->actingAs($cashier)->postJson('/stock/counts', $input)->assertForbidden();
        $this->get('/stock/counts')->assertForbidden();
        $id = $this->actingAs($this->owner)->postJson('/stock/counts', $input)->assertOk()->json('id');
        $this->postJson('/stock/counts', [...$input, 'counted_quantity' => '4'])->assertConflict();
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->postJson('/stock/counts', $input)->assertConflict();
        $this->get('/stock/counts/'.$id)->assertOk();
        $this->actingAs($cashier)->get('/stock/counts/'.$id)->assertForbidden();
        $unknown = Product::create(['barcode' => 'UNKNOWN', 'name' => 'رصيد مجهول', 'unit' => 'حبة', 'price' => '1', 'tax_rate' => '15']);
        $this->actingAs($this->owner)->postJson('/stock/counts', [...$this->input(), 'product_id' => $unknown->id, 'expected_quantity' => '0', 'expected_revision' => 0])->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->assertDatabaseCount('stock_counts', 1);
    }

    public function test_zero_count_and_matching_count_are_distinct_and_stale_revision_rejects_even_if_quantity_returns(): void
    {
        $stale = $this->input('4');
        $this->actingAs($this->owner)->postJson('/stock/counts', $this->input('0'))->assertOk();
        $this->assertSame('0.000', InventoryBalance::findOrFail($this->product->id)->quantity);
        $this->postJson('/stock/counts', $this->input('5'))->assertOk();
        $this->postJson('/stock/counts', $stale)->assertUnprocessable()->assertJsonValidationErrors('expected_quantity');
        $this->postJson('/stock/counts', $this->input('5'))->assertOk();
        $this->assertDatabaseCount('stock_counts', 3);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertSame(3, InventoryBalance::findOrFail($this->product->id)->revision);
    }

    public function test_verified_correction_allows_a_previously_blocked_sale(): void
    {
        $this->actingAs($this->owner)->postJson('/stock/counts', $this->input('0'))->assertOk();
        $station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق 1']);
        $shift = app(OpenShift::class)->execute($this->owner, ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'opening_cash' => '0']);
        $items = [['product_id' => $this->product->id, 'quantity' => '1']];
        $quote = app(BasketCalculator::class)->calculate($items, Product::all()->keyBy('id'));
        $sale = ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'shift_id' => $shift->id,
            'items' => $items, 'quote_fingerprint' => $quote['fingerprint'], 'cash_tender' => '115', 'card_amount' => '0', 'card_confirmed' => false];
        $this->postJson('/pos/sales', $sale)->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson('/stock/counts', $this->input('2'))->assertOk();
        $this->postJson('/pos/sales', $sale)->assertOk();
        $this->assertSame('1.000', InventoryBalance::findOrFail($this->product->id)->quantity);
        $this->assertSame(3, InventoryBalance::findOrFail($this->product->id)->revision);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_invalid_quantity_and_movement_failure_leave_count_and_balance_unchanged(): void
    {
        foreach (['-1', '1.0001', '1000000000000000'] as $invalid) {
            $this->actingAs($this->owner)->postJson('/stock/counts', $this->input($invalid))->assertUnprocessable()->assertJsonValidationErrors('counted_quantity');
        }
        $this->postJson('/stock/counts', [...$this->input(), 'reason' => '  '])->assertUnprocessable()->assertJsonValidationErrors('reason');
        DB::statement("CREATE FUNCTION reject_count_movement() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.stock_count_id IS NOT NULL THEN RAISE EXCEPTION 'test failure'; END IF; RETURN NEW; END; $$");
        DB::statement('CREATE TRIGGER reject_count_movement BEFORE INSERT ON stock_movements FOR EACH ROW EXECUTE FUNCTION reject_count_movement()');
        $this->withoutExceptionHandling();
        try {
            $this->postJson('/stock/counts', $this->input());
            $this->fail('Movement failure must roll back the count.');
        } catch (QueryException) {
            $this->assertDatabaseCount('stock_counts', 0);
            $this->assertSame('5.000', InventoryBalance::findOrFail($this->product->id)->quantity);
            $this->assertSame(0, InventoryBalance::findOrFail($this->product->id)->revision);
            $this->assertDatabaseCount('stock_movements', 1);
        }
    }
}
