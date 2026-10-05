<?php

namespace App\Policies;

use App\Models\CashierShift;
use App\Models\User;

class CashierShiftPolicy
{
    public function view(User $user, CashierShift $shift): bool
    {
        return $user->can('manage-products') || $shift->cashier_id === $user->id;
    }

    public function close(User $user, CashierShift $shift): bool
    {
        return $shift->cashier_id === $user->id;
    }

    public function transferCash(User $user, CashierShift $shift): bool
    {
        return $user->can('manage-cash');
    }
}
