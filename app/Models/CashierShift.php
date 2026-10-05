<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string $opening_cash
 * @property numeric-string|null $counted_cash
 */
#[Fillable(['operation_id', 'cashier_id', 'station_id', 'opening_cash', 'opened_at', 'closed_at', 'sale_count', 'sales_total', 'cash_sales', 'card_sales', 'expected_cash', 'counted_cash', 'difference', 'closing_note'])]
class CashierShift extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['opening_cash' => 'decimal:2', 'sales_total' => 'decimal:2', 'cash_sales' => 'decimal:2',
            'card_sales' => 'decimal:2', 'expected_cash' => 'decimal:2', 'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2', 'sale_count' => 'integer', 'opened_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return BelongsTo<PosStation, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(PosStation::class);
    }
}
