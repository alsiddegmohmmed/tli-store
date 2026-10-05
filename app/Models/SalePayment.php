<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sale_id', 'method', 'amount', 'reference', 'manually_confirmed'])]
class SalePayment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'manually_confirmed' => 'boolean'];
    }
}
