<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_id', 'created_by', 'quantity', 'opening_date', 'reference'])]
class StockOpening extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'opening_date' => 'immutable_date'];
    }
}
