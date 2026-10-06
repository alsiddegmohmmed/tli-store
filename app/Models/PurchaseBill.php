<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property numeric-string $total */
#[Fillable(['operation_id', 'request_hash', 'supplier_id', 'supplier_snapshot', 'created_by', 'reference', 'reference_key', 'bill_date', 'description', 'total', 'printed_tax', 'recorded_at'])]
class PurchaseBill extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['supplier_snapshot' => 'array', 'total' => 'decimal:2', 'printed_tax' => 'decimal:2', 'recorded_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SupplierPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
