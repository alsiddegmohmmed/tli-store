<?php

namespace Tests\Feature;

use App\Domain\Sales\BasketCalculator;
use App\Domain\Shifts\OpenShift;
use App\Domain\Stock\RecordOpeningStock;
use App\Enums\UserRole;
use App\Models\CashierShift;
use App\Models\InventoryBalance;
use App\Models\PosStation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\Support\RunsConcurrentOperations;
use Tests\TestCase;

class ShiftConcurrencyTest extends TestCase
{
    use DatabaseMigrations, RunsConcurrentOperations;

    public function test_two_cashiers_cannot_open_the_same_station(): void
    {
        $station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق 1']);
        $inputs = [];
        for ($i = 0; $i < 2; $i++) {
            $inputs[] = ['action' => 'open_shift', 'user_id' => User::factory()->create()->id,
                'data' => ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'opening_cash' => '100']];
        }
        $results = $this->race($inputs);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['ok']));
        $this->assertCount(1, array_filter($results, fn ($result) => isset($result['errors']['station_id'])));
        $this->assertDatabaseCount('cashier_shifts', 1);
    }

    public function test_one_cashier_cannot_open_two_stations_simultaneously(): void
    {
        $user = User::factory()->create();
        $inputs = [];
        for ($i = 1; $i <= 2; $i++) {
            $station = PosStation::create(['code' => 'POS-'.$i, 'name' => 'صندوق '.$i]);
            $inputs[] = ['action' => 'open_shift', 'user_id' => $user->id,
                'data' => ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'opening_cash' => '100']];
        }
        $results = $this->race($inputs);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['ok']));
        $this->assertCount(1, array_filter($results, fn ($result) => isset($result['errors']['station_id'])));
        $this->assertDatabaseCount('cashier_shifts', 1);
    }

    public function test_sale_racing_with_close_is_included_or_rejected_before_posting(): void
    {
        $user = User::factory()->create();
        $station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق 1']);
        $shift = app(OpenShift::class)->execute($user, ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'opening_cash' => '100']);
        $product = Product::create(['barcode' => '00001', 'name' => 'صنف اختبار', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00']);
        app(RecordOpeningStock::class)->execute(User::factory()->create(['role' => UserRole::Owner]), [
            'product_id' => $product->id, 'quantity' => '1', 'opening_date' => now()->toDateString(), 'reference' => 'اختبار التزامن',
        ]);
        $items = [['product_id' => $product->id, 'quantity' => '1']];
        $quote = app(BasketCalculator::class)->calculate($items, Product::all()->keyBy('id'));
        $results = $this->race([
            ['user_id' => $user->id, 'data' => ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'shift_id' => $shift->id,
                'items' => $items, 'quote_fingerprint' => $quote['fingerprint'], 'cash_tender' => '120', 'card_amount' => '0', 'card_confirmed' => false]],
            ['action' => 'close_shift', 'user_id' => $user->id, 'data' => ['shift_id' => $shift->id, 'counted_cash' => '215']],
        ]);
        $this->assertTrue($results[1]['ok']);
        $closed = CashierShift::findOrFail($shift->id);
        $this->assertNotNull($closed->closed_at);
        if ($results[0]['ok']) {
            $this->assertSame('215.00', $closed->expected_cash);
            $this->assertSame('0.00', $closed->difference);
            $this->assertSame(1, $closed->sale_count);
            $this->assertSame('0.000', InventoryBalance::findOrFail($product->id)->quantity);
            $this->assertDatabaseCount('journal_entries', 1);
        } else {
            $this->assertArrayHasKey('station_id', $results[0]['errors']);
            $this->assertSame('100.00', $closed->expected_cash);
            $this->assertSame('115.00', $closed->difference);
            $this->assertSame(0, $closed->sale_count);
            $this->assertSame('1.000', InventoryBalance::findOrFail($product->id)->quantity);
            $this->assertDatabaseCount('sales', 0);
            $this->assertDatabaseCount('journal_entries', 0);
        }
    }
}
