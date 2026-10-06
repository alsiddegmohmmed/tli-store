<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property numeric-string $amount */
#[Fillable(['operation_id', 'request_hash', 'purchase_bill_id', 'created_by', 'payment_date', 'amount', 'method', 'reference', 'reference_key', 'funding_reference', 'actual_payer', 'recorded_at'])]
class SupplierPayment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'recorded_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
