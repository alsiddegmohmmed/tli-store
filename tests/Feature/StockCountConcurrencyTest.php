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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RunsConcurrentOperations;
use Tests\TestCase;

class StockCountConcurrencyTest extends TestCase
{
    use DatabaseMigrations, RunsConcurrentOperations;

    private function fixture(): array
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $product = Product::create(['barcode' => '00001', 'name' => 'صنف اختبار', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00']);
        app(RecordOpeningStock::class)->execute($owner, ['product_id' => $product->id, 'quantity' => '5', 'opening_date' => now()->toDateString(), 'reference' => 'جرد تجريبي']);
        $input = ['action' => 'stock_count', 'user_id' => $owner->id, 'data' => ['operation_id' => (string) Str::uuid(), 'product_id' => $product->id,
            'expected_quantity' => '5', 'expected_revision' => 0, 'counted_quantity' => '3', 'reference' => 'TEST-COUNT', 'reason' => 'اختبار عد موثق']];

        return [$owner, $product, $input];
    }

    public function test_simultaneous_count_retries_post_once(): void
    {
        [, $product, $input] = $this->fixture();
        $results = $this->race([$input, $input]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame('3.000', InventoryBalance::findOrFail($product->id)->quantity);
        $this->assertSame(1, InventoryBalance::findOrFail($product->id)->revision);
    }

    public function test_competing_counts_cannot_overwrite_each_other(): void
    {
        [, $product, $input] = $this->fixture();
        $other = $input;
        $other['data']['operation_id'] = (string) Str::uuid();
        $other['data']['counted_quantity'] = '2';
        $results = $this->race([$input, $other]);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['ok']));
        $this->assertCount(1, array_filter($results, fn ($result) => isset($result['errors']['expected_quantity'])));
        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame(1, InventoryBalance::findOrFail($product->id)->revision);
        $this->assertSame(InventoryBalance::findOrFail($product->id)->quantity, bcadd((string) DB::table('stock_movements')->sum('quantity'), '0', 3));
    }

    public function test_sale_and_count_serialize_without_losing_the_sale_deduction(): void
    {
        [$owner, $product, $input] = $this->fixture();
        $station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق 1']);
        $shift = app(OpenShift::class)->execute($owner, ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'opening_cash' => '0']);
        $items = [['product_id' => $product->id, 'quantity' => '1']];
        $quote = app(BasketCalculator::class)->calculate($items, Product::all()->keyBy('id'));
        $results = $this->race([$input, ['user_id' => $owner->id, 'data' => ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id,
            'shift_id' => $shift->id, 'items' => $items, 'quote_fingerprint' => $quote['fingerprint'], 'cash_tender' => '115', 'card_amount' => '0', 'card_confirmed' => false]]]);
        $this->assertTrue($results[1]['ok']);
        $balance = InventoryBalance::findOrFail($product->id);
        if ($results[0]['ok']) {
            $this->assertSame('2.000', $balance->quantity);
            $this->assertSame(2, $balance->revision);
            $this->assertDatabaseCount('stock_counts', 1);
        } else {
            $this->assertArrayHasKey('expected_quantity', $results[0]['errors']);
            $this->assertSame('4.000', $balance->quantity);
            $this->assertSame(1, $balance->revision);
            $this->assertDatabaseCount('stock_counts', 0);
        }
        $this->assertSame($balance->quantity, bcadd((string) DB::table('stock_movements')->sum('quantity'), '0', 3));
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }
}
