<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_bill_id', 'supplier_payment_id', 'created_by', 'kind', 'original_name', 'storage_path', 'mime_type', 'extension', 'size', 'sha256', 'paper_location', 'recorded_at'])]
#[Hidden(['storage_path', 'sha256'])]
class DocumentEvidence extends Model
{
    protected $table = 'document_evidence';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
