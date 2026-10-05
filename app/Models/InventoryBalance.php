<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property numeric-string $quantity
 */
#[Fillable(['product_id', 'quantity'])]
class InventoryBalance extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }
}
