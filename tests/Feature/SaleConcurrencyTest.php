<?php

namespace Tests\Feature;

use App\Domain\Sales\BasketCalculator;
use App\Domain\Stock\RecordOpeningStock;
use App\Enums\UserRole;
use App\Models\InventoryBalance;
use App\Models\PosStation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SaleConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(): array
    {
        $product = Product::create(['barcode' => '00001', 'name' => 'صنف اختبار', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00']);
        app(RecordOpeningStock::class)->execute(User::factory()->create(['role' => UserRole::Owner]), [
            'product_id' => $product->id, 'quantity' => '1', 'opening_date' => now()->toDateString(), 'reference' => 'اختبار التزامن',
        ]);
        $items = [['product_id' => $product->id, 'quantity' => '1']];
        $quote = app(BasketCalculator::class)->calculate($items, Product::all()->keyBy('id'));

        return [$product, ['items' => $items, 'quote_fingerprint' => $quote['fingerprint'], 'cash_tender' => '115', 'card_amount' => '0', 'card_confirmed' => false]];
    }

    public function test_four_stations_competing_for_the_last_unit_commit_only_one_sale(): void
    {
        [$product, $data] = $this->fixture();
        $inputs = [];
        for ($i = 1; $i <= 4; $i++) {
            $station = PosStation::create(['code' => 'POS-'.$i, 'name' => 'صندوق '.$i]);
            $inputs[] = ['user_id' => User::factory()->create()->id, 'data' => [...$data, 'station_id' => $station->id, 'operation_id' => (string) Str::uuid()]];
        }
        $results = $this->race($inputs);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['ok']));
        $this->assertCount(3, array_filter($results, fn ($result) => ! $result['ok'] && isset($result['errors']['items'])));
        $this->assertSame('0.000', InventoryBalance::findOrFail($product->id)->quantity);
        foreach (['sales', 'sale_lines', 'sale_payments', 'journal_entries'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_simultaneous_retries_share_one_committed_operation(): void
    {
        [$product, $data] = $this->fixture();
        $station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق 1']);
        $input = ['user_id' => User::factory()->create()->id, 'data' => [...$data, 'station_id' => $station->id, 'operation_id' => (string) Str::uuid()]];
        $results = $this->race([$input, $input]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame('0.000', InventoryBalance::findOrFail($product->id)->quantity);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    private function race(array $inputs): array
    {
        $gate = sys_get_temp_dir().'/tli-race-'.Str::uuid();
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'tli_store_test', 'DB_URL' => '',
            'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password')];
        $workers = [];
        try {
            foreach ($inputs as $i => $input) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/record-sale.php'), $gate, $gate.'.'.$i], base_path(), $environment,
                    json_encode($input, JSON_THROW_ON_ERROR), 15);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            foreach ($workers as $i => $worker) {
                while (! is_file($gate.'.'.$i) && $worker->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                    clearstatcache();
                }
                $this->assertFileExists($gate.'.'.$i, $worker->getErrorOutput());
            }
            file_put_contents($gate, 'start');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($workers as $i => $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                } if (is_file($gate.'.'.$i)) {
                    unlink($gate.'.'.$i);
                }
            }
            if (is_file($gate)) {
                unlink($gate);
            }
        }
    }
}
