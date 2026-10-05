<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property numeric-string $quantity
 * @property int $revision
 */
#[Fillable(['product_id', 'quantity'])]
class InventoryBalance extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    /**
     * Call only while holding the product and balance locks in a transaction.
     *
     * @param  numeric-string  $quantity
     */
    public function recordQuantity(string $quantity): void
    {
        $this->forceFill(['quantity' => bcadd($quantity, '0', 3), 'revision' => $this->revision + 1])->save();
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'revision' => 'integer'];
    }
}
