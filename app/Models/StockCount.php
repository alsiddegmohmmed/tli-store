<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['operation_id', 'request_hash', 'product_id', 'created_by', 'product_snapshot', 'recorded_quantity', 'counted_quantity', 'difference', 'balance_revision', 'reference', 'reason', 'recorded_at'])]
class StockCount extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['product_snapshot' => 'array', 'recorded_quantity' => 'decimal:3', 'counted_quantity' => 'decimal:3',
            'difference' => 'decimal:3', 'balance_revision' => 'integer', 'recorded_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
