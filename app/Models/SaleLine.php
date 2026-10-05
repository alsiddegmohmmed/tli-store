<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sale_id', 'product_id', 'name', 'barcode', 'unit', 'quantity', 'unit_price', 'tax_rate', 'net', 'tax', 'total'])]
class SaleLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'tax_rate' => 'decimal:2',
            'net' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2'];
    }
}
