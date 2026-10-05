<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property numeric-string $price
 * @property numeric-string $tax_rate
 */
#[Fillable(['barcode', 'name', 'unit', 'price', 'tax_rate', 'is_active'])]
class Product extends Model
{
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'tax_rate' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
