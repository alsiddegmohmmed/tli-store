<?php

namespace App\Domain\Shifts;

use App\Models\CashierShift;
use App\Models\PosStation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CloseShift
{
    public function __construct(private ShiftTotals $totals) {}

    /** @param numeric-string $countedCash */
    public function execute(User $user, int $shiftId, string $countedCash, ?string $note): CashierShift
    {
        $stationId = CashierShift::findOrFail($shiftId)->station_id;

        return DB::transaction(function () use ($user, $shiftId, $stationId, $countedCash, $note): CashierShift {
            PosStation::whereKey($stationId)->lockForUpdate()->firstOrFail();
            $shift = CashierShift::whereKey($shiftId)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('close', $shift);
            if ($shift->closed_at) {
                abort_unless($shift->counted_cash !== null && bccomp($shift->counted_cash, $countedCash, 2) === 0 && $shift->closing_note === $note,
                    409, 'أُغلقت الوردية ببيانات مختلفة. راجع نتيجة الإغلاق المحفوظة.');

                return $shift;
            }
            $totals = $this->totals->calculate($shift);
            $shift->update([...$totals, 'counted_cash' => bcadd($countedCash, '0', 2),
                'difference' => bcsub($countedCash, $totals['expected_cash'], 2), 'closing_note' => $note, 'closed_at' => now()]);

            return $shift;
        }, 3);
    }
}
