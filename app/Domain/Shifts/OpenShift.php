<?php

namespace App\Domain\Shifts;

use App\Models\CashierShift;
use App\Models\PosStation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-type OpeningInput array{operation_id: string, station_id: int, opening_cash: numeric-string} */
class OpenShift
{
    /** @param OpeningInput $data */
    public function execute(User $user, array $data): CashierShift
    {
        $existing = CashierShift::where('operation_id', $data['operation_id'])->first();
        if ($existing) {
            return $this->replay($existing, $user, $data);
        }
        try {
            return DB::transaction(function () use ($user, $data): CashierShift {
                $station = PosStation::whereKey($data['station_id'])->lockForUpdate()->firstOrFail();
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = CashierShift::where('operation_id', $data['operation_id'])->first();
                if ($existing) {
                    return $this->replay($existing, $user, $data);
                }
                if (! $station->is_active) {
                    throw ValidationException::withMessages(['station_id' => 'هذا الصندوق غير متاح.']);
                }
                if (CashierShift::whereNull('closed_at')->where('cashier_id', $user->id)->exists()) {
                    throw ValidationException::withMessages(['station_id' => 'لديك وردية مفتوحة. ارجع إلى سجلها قبل فتح وردية أخرى.']);
                }
                if (CashierShift::whereNull('closed_at')->where('station_id', $station->id)->exists()) {
                    throw ValidationException::withMessages(['station_id' => 'الصندوق مستخدم في وردية مفتوحة. اختر صندوقًا آخر.']);
                }

                return CashierShift::create(['operation_id' => $data['operation_id'], 'cashier_id' => $user->id,
                    'station_id' => $station->id, 'opening_cash' => bcadd($data['opening_cash'], '0', 2), 'opened_at' => now()]);
            }, 3);
        } catch (QueryException $error) {
            if ($error->getCode() !== '23505' || ! str_contains($error->getMessage(), 'cashier_shifts_operation_id_unique')) {
                throw $error;
            }

            return $this->replay(CashierShift::where('operation_id', $data['operation_id'])->firstOrFail(), $user, $data);
        }
    }

    /** @param OpeningInput $data */
    private function replay(CashierShift $shift, User $user, array $data): CashierShift
    {
        abort_unless($shift->cashier_id === $user->id && $shift->station_id === $data['station_id']
            && bccomp($shift->opening_cash, $data['opening_cash'], 2) === 0, 409, 'هذه المحاولة مرتبطة بوردية مختلفة. راجع السجل قبل المتابعة.');

        return $shift;
    }
}
