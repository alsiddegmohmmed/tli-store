<?php

namespace Tests\Feature;

use App\Domain\Accounting\JournalPoster;
use App\Domain\Shifts\CloseShift;
use App\Domain\Shifts\OpenShift;
use App\Enums\UserRole;
use App\Models\CashierShift;
use App\Models\PosStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShiftCashTransferTest extends TestCase
{
    use RefreshDatabase;

    private function input(string $direction = 'in', string $amount = '50.25'): array
    {
        return ['operation_id' => (string) Str::uuid(), 'direction' => $direction, 'amount' => $amount,
            'reference' => 'مرجع اختبار', 'reason' => 'تحويل نقد تجريبي'];
    }

    private function shift(User $cashier): CashierShift
    {
        $station = PosStation::create(['code' => 'POS-'.Str::random(8), 'name' => 'صندوق اختبار']);

        return app(OpenShift::class)->execute($cashier, ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'opening_cash' => '100']);
    }

    public function test_transfers_post_once_change_cash_and_preserve_close_and_recovery(): void
    {
        $cashier = User::factory()->create();
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);
        $shift = $this->shift($cashier);
        $url = '/shifts/'.$shift->id.'/cash-transfers';
        $input = $this->input();
        $id = $this->actingAs($accountant)->postJson($url, $input)->assertOk()->json('id');
        $this->postJson($url, [...$input, 'amount' => '50.250'])->assertUnprocessable();
        $this->postJson($url, $input)->assertOk()->assertJsonPath('id', $id);
        $this->postJson($url, [...$input, 'amount' => '50'])->assertConflict();
        $this->postJson($url, [...$input, 'direction' => 'out'])->assertConflict();
        $this->postJson($url, [...$input, 'reason' => 'سبب مختلف'])->assertConflict();
        $this->postJson($url, [...$input, 'reference' => 'مرجع مختلف'])->assertConflict();
        $this->assertDatabaseCount('shift_cash_transfers', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseHas('journal_lines', ['account_code' => 'cash', 'debit' => '50.25', 'credit' => '0.00']);
        $this->assertDatabaseHas('journal_lines', ['account_code' => 'cash_reserve', 'credit' => '50.25', 'debit' => '0.00']);
        $this->assertDatabaseHas('journal_entries', ['sale_id' => null, 'shift_cash_transfer_id' => $id]);
        $out = $this->input('out', '20.10');
        $this->postJson($url, $out)->assertOk();
        $this->get('/shifts/'.$shift->id)->assertInertia(fn (Assert $page) => $page->component('shifts/show')
            ->where('summary.cash_in', '50.25')->where('summary.cash_out', '20.10')->where('summary.expected_cash', '130.15')->has('transfers.data', 2));
        $this->get($url.'/'.$id)->assertInertia(fn (Assert $page) => $page->component('shifts/cash-transfer')->has('journal', 2));
        $this->assertDatabaseHas('journal_lines', ['account_code' => 'cash', 'credit' => '20.10', 'debit' => '0.00']);
        $this->assertDatabaseHas('journal_lines', ['account_code' => 'cash_reserve', 'debit' => '20.10', 'credit' => '0.00']);
        app(CloseShift::class)->execute($cashier, $shift->id, '129.15', 'اختبار الإغلاق');
        $this->assertDatabaseHas('cashier_shifts', ['id' => $shift->id, 'cash_in' => '50.25', 'cash_out' => '20.10',
            'expected_cash' => '130.15', 'difference' => '-1.00', 'cash_sales' => '0.00', 'card_sales' => '0.00']);
        $this->postJson($url, $input)->assertOk()->assertJsonPath('id', $id);
        $this->postJson($url, $this->input())->assertUnprocessable()->assertJsonValidationErrors('shift_id');
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_payments', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_cashiers_cannot_post_transfers_or_read_another_drawer_and_staff_retries_are_owned(): void
    {
        $cashier = User::factory()->create();
        $shift = $this->shift($cashier);
        $otherShift = $this->shift(User::factory()->create());
        $url = '/shifts/'.$shift->id.'/cash-transfers';
        $input = $this->input();
        $this->postJson($url, $input)->assertUnauthorized();
        $this->actingAs($cashier)->postJson($url, $input)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $id = $this->postJson($url, $input)->assertOk()->json('id');
        $this->postJson('/shifts/'.$otherShift->id.'/cash-transfers', $input)->assertConflict();
        $this->get('/shifts/'.$otherShift->id.'/cash-transfers/'.$id)->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->postJson($url, $input)->assertConflict();
        $this->actingAs($cashier)->get($url.'/'.$id)->assertInertia(fn (Assert $page) => $page->has('journal', 0));
        $this->get('/shifts/'.$shift->id)->assertInertia(fn (Assert $page) => $page->where('canTransfer', false)->has('transfers.data', 1));
        $this->actingAs(User::factory()->create())->get($url.'/'.$id)->assertForbidden();
        $this->assertDatabaseCount('shift_cash_transfers', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_invalid_amounts_and_overdraw_do_not_post_and_withdrawing_the_exact_cash_is_allowed(): void
    {
        $shift = $this->shift(User::factory()->create());
        $url = '/shifts/'.$shift->id.'/cash-transfers';
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]));
        foreach (['0', '-1', '0.001', '1e2'] as $amount) {
            $this->postJson($url, $this->input('out', $amount))->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        $this->postJson($url, [...$this->input(), 'direction' => 'expense'])->assertUnprocessable();
        $this->postJson($url, [...$this->input(), 'reference' => '', 'reason' => ''])->assertUnprocessable();
        $input = $this->input('out', '100.01');
        $this->postJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('shift_cash_transfers', 0);
        $this->postJson($url, [...$input, 'amount' => '100'])->assertOk();
        $this->get('/shifts/'.$shift->id)->assertInertia(fn (Assert $page) => $page->where('summary.expected_cash', '0.00'));
    }

    public function test_failed_accounting_rolls_back_the_transfer_and_same_attempt_can_retry(): void
    {
        $shift = $this->shift(User::factory()->create());
        $url = '/shifts/'.$shift->id.'/cash-transfers';
        $input = $this->input();
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $this->mock(JournalPoster::class)->shouldReceive('post')->once()->andThrow(new \RuntimeException('Simulated posting failure'));
        $this->postJson($url, $input)->assertServerError();
        $this->assertDatabaseCount('shift_cash_transfers', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
        $this->app->forgetInstance(JournalPoster::class);
        $this->postJson($url, $input)->assertOk();
        $this->assertDatabaseCount('shift_cash_transfers', 1);
        $journal = DB::table('journal_lines')->selectRaw('SUM(debit) AS debits, SUM(credit) AS credits')->first();
        $this->assertSame('50.25', $journal->debits);
        $this->assertSame($journal->debits, $journal->credits);
    }

    public function test_migration_preserves_prior_closing_values_and_leaves_open_snapshots_null(): void
    {
        $cashier = User::factory()->create();
        $closed = $this->shift($cashier);
        app(CloseShift::class)->execute($cashier, $closed->id, '98.50', 'عد سابق');
        $open = $this->shift($cashier);
        $migration = require database_path('migrations/2026_10_05_000006_add_shift_cash_transfers.php');
        $migration->down();
        $before = DB::table('cashier_shifts')->where('id', $closed->id)->first();
        $migration->up();
        $after = DB::table('cashier_shifts')->where('id', $closed->id)->first();
        foreach ((array) $before as $field => $value) {
            $this->assertSame($value, $after->$field);
        }
        $this->assertSame('0.00', $after->cash_in);
        $this->assertSame('0.00', $after->cash_out);
        $this->assertDatabaseHas('cashier_shifts', ['id' => $open->id, 'closed_at' => null, 'cash_in' => null, 'cash_out' => null]);
    }
}
