<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:products,barcode'],
            'name' => ['required', 'string', 'max:120'],
            'unit' => ['required', 'string', 'max:24'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'tax_rate' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
        ];
    }

    public function attributes(): array
    {
        return ['barcode' => 'الباركود', 'name' => 'اسم الصنف', 'unit' => 'الوحدة', 'price' => 'السعر', 'tax_rate' => 'نسبة الضريبة'];
    }
}
