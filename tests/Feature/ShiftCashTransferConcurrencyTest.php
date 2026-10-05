<?php

namespace Tests\Feature;

use App\Domain\Shifts\OpenShift;
use App\Enums\UserRole;
use App\Models\CashierShift;
use App\Models\PosStation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RunsConcurrentOperations;
use Tests\TestCase;

class ShiftCashTransferConcurrencyTest extends TestCase
{
    use DatabaseMigrations, RunsConcurrentOperations;

    protected function tearDown(): void
    {
        // Clear committed concurrency fixtures in the isolated test database before rollback.
        // The migration deliberately refuses to erase posted operational transfer provenance.
        DB::statement('TRUNCATE cashier_shifts CASCADE');
        parent::tearDown();
    }

    private function context(): array
    {
        $cashier = User::factory()->create();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق اختبار']);
        $shift = app(OpenShift::class)->execute($cashier, ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'opening_cash' => '100']);
        $input = ['action' => 'cash_transfer', 'user_id' => $owner->id, 'shift_id' => $shift->id,
            'data' => ['operation_id' => (string) Str::uuid(), 'direction' => 'out', 'amount' => '70.00', 'reference' => 'اختبار', 'reason' => 'سحب إلى الخزنة']];

        return [$cashier, $shift, $input];
    }

    public function test_simultaneous_retries_post_one_transfer_and_one_journal(): void
    {
        [, , $input] = $this->context();
        $results = $this->race([$input, $input]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('shift_cash_transfers', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_lines', 2);
    }

    public function test_competing_withdrawals_cannot_overdraw_the_same_drawer(): void
    {
        [, , $input] = $this->context();
        $other = $input;
        $other['data']['operation_id'] = (string) Str::uuid();
        $results = $this->race([$input, $other]);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['ok']));
        $this->assertCount(1, array_filter($results, fn ($result) => isset($result['errors']['amount'])));
        $this->assertDatabaseCount('shift_cash_transfers', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_transfer_racing_with_close_is_included_or_rejected(): void
    {
        [$cashier, $shift, $input] = $this->context();
        $results = $this->race([$input, ['action' => 'close_shift', 'user_id' => $cashier->id,
            'data' => ['shift_id' => $shift->id, 'counted_cash' => '30']]]);
        $this->assertTrue($results[1]['ok']);
        $closed = CashierShift::findOrFail($shift->id);
        $this->assertNotNull($closed->closed_at);
        if ($results[0]['ok']) {
            $this->assertSame('30.00', $closed->expected_cash);
            $this->assertSame('70.00', $closed->cash_out);
            $this->assertSame('0.00', $closed->difference);
            $this->assertDatabaseCount('journal_entries', 1);
        } else {
            $this->assertArrayHasKey('shift_id', $results[0]['errors']);
            $this->assertSame('100.00', $closed->expected_cash);
            $this->assertSame('0.00', $closed->cash_out);
            $this->assertSame('-70.00', $closed->difference);
            $this->assertDatabaseCount('shift_cash_transfers', 0);
            $this->assertDatabaseCount('journal_entries', 0);
        }
    }
}
