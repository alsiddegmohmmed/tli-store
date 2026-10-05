<?php

namespace App\Domain\Shifts;

use App\Domain\Accounting\JournalPoster;
use App\Models\CashierShift;
use App\Models\PosStation;
use App\Models\ShiftCashTransfer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** @phpstan-type TransferInput array{operation_id: string, direction: 'in'|'out', amount: numeric-string, reference: string, reason: string} */
class RecordCashTransfer
{
    public function __construct(private ShiftTotals $totals, private JournalPoster $journals) {}

    /** @param TransferInput $data */
    public function execute(User $user, int $shiftId, array $data): ShiftCashTransfer
    {
        $shift = CashierShift::findOrFail($shiftId);
        Gate::forUser($user)->authorize('transferCash', $shift);
        $existing = ShiftCashTransfer::where('operation_id', $data['operation_id'])->first();
        if ($existing) {
            return $this->replay($existing, $user, $shiftId, $data);
        }
        try {
            return DB::transaction(function () use ($user, $shiftId, $shift, $data): ShiftCashTransfer {
                // Same ordering as sale/close: movement either enters the close or is rejected.
                PosStation::whereKey($shift->station_id)->lockForUpdate()->firstOrFail();
                $locked = CashierShift::whereKey($shiftId)->lockForUpdate()->firstOrFail();
                $existing = ShiftCashTransfer::where('operation_id', $data['operation_id'])->first();
                if ($existing) {
                    return $this->replay($existing, $user, $shiftId, $data);
                }
                if ($locked->closed_at) {
                    throw ValidationException::withMessages(['shift_id' => 'الوردية مغلقة. لا يمكن تسجيل تحويل جديد عليها.']);
                }
                if ($data['direction'] === 'out' && bccomp($data['amount'], $this->totals->calculate($locked)['expected_cash'], 2) > 0) {
                    throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من النقد المتوقع في الصندوق. راجع المبلغ والحركات قبل التحويل.']);
                }
                $transfer = ShiftCashTransfer::create([...$data, 'shift_id' => $shiftId, 'created_by' => $user->id,
                    'amount' => bcadd($data['amount'], '0', 2), 'recorded_at' => now()]);
                $this->journals->post(['shift_cash_transfer_id' => $transfer->id], $user->id, now('Asia/Riyadh')->toDateString(), 'تحويل نقدي للوردية '.$shiftId, [
                    ['account_code' => $data['direction'] === 'in' ? 'cash' : 'cash_reserve', 'debit' => $transfer->amount],
                    ['account_code' => $data['direction'] === 'in' ? 'cash_reserve' : 'cash', 'credit' => $transfer->amount],
                ]);

                return $transfer;
            }, 3);
        } catch (QueryException $error) {
            if ($error->getCode() !== '23505' || ! str_contains($error->getMessage(), 'shift_cash_transfers_operation_id_unique')) {
                throw $error;
            }

            return $this->replay(ShiftCashTransfer::where('operation_id', $data['operation_id'])->firstOrFail(), $user, $shiftId, $data);
        }
    }

    /** @param TransferInput $data */
    private function replay(ShiftCashTransfer $transfer, User $user, int $shiftId, array $data): ShiftCashTransfer
    {
        abort_unless($transfer->created_by === $user->id && $transfer->shift_id === $shiftId
            && $transfer->direction === $data['direction'] && bccomp($transfer->amount, $data['amount'], 2) === 0
            && $transfer->reference === $data['reference'] && $transfer->reason === $data['reason'],
            409, 'هذه المحاولة محفوظة ببيانات مختلفة. راجع سجل التحويلات قبل المتابعة.');

        return $transfer;
    }
}
