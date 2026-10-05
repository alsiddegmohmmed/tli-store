<?php

namespace App\Domain\Shifts;

use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OwnedOpenShift
{
    public function assertAvailable(User $user, ?CashierShift $shift): CashierShift
    {
        if (! $shift || $shift->closed_at || $shift->cashier_id !== $user->id) {
            throw ValidationException::withMessages(['station_id' => 'افتح وردية باسمك على هذا الصندوق قبل تسجيل الدفع.']);
        }

        return $shift;
    }
}
