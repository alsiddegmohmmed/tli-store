<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'station_id' => ['required', 'integer', Rule::exists('pos_stations', 'id')->where('is_active', true)],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'string', 'numeric', 'regex:/^\d+(\.\d{1,3})?$/', 'decimal:0,3', 'gt:0', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'station_id.*' => 'اختر صندوقًا متاحًا.',
            'items.*' => 'أضف الأصناف إلى السلة، بحد أقصى 100 صنف.',
            'items.*.product_id.*' => 'أحد الأصناف غير متاح أو مكرر. راجع السلة.',
            'items.*.quantity.*' => 'الكمية يجب أن تكون أكبر من صفر وحتى 10000، بثلاث خانات عشرية كحد أقصى.',
        ];
    }
}
