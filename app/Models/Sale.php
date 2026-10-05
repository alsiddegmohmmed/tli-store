<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property numeric-string $net
 * @property numeric-string $tax
 * @property numeric-string $total
 * @property numeric-string $cash_tender
 * @property numeric-string $change
 */
#[Fillable(['operation_id', 'request_hash', 'cashier_id', 'station_id', 'net', 'tax', 'total', 'cash_tender', 'change', 'recorded_at', 'seller_snapshot'])]
class Sale extends Model
{
    public $timestamps = false;

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return ['net' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2',
            'cash_tender' => 'decimal:2', 'change' => 'decimal:2', 'recorded_at' => 'immutable_datetime', 'seller_snapshot' => 'array'];
    }

    /** @return HasMany<SaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    /** @return HasMany<SalePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return BelongsTo<PosStation, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(PosStation::class, 'station_id');
    }
}
