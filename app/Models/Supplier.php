<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'tax_number', 'commercial_registration', 'phone', 'city', 'notes', 'is_active', 'created_by', 'updated_by'])]
class Supplier extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
