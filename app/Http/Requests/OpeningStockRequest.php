<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** @phpstan-import-type OpeningInput from \App\Domain\Stock\RecordOpeningStock */
class OpeningStockRequest extends FormRequest
{
    /** @return OpeningInput */
    public function openingData(): array
    {
        /** @var OpeningInput $data */
        $data = $this->validated();
        $data['product_id'] = (int) $data['product_id'];

        return $data;
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'string', 'numeric', 'regex:/^\d{1,9}(\.\d{1,3})?$/', 'gt:0'],
            'opening_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Riyadh')->toDateString()],
            'reference' => ['required', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return ['product_id.*' => 'اختر صنفًا مسجلًا في النظام.',
            'quantity.*' => 'أدخل كمية موجبة بثلاث خانات عشرية كحد أقصى.',
            'opening_date.*' => 'أدخل تاريخًا صحيحًا لا يتجاوز اليوم.', 'reference.*' => 'أدخل مرجع محضر الجرد الافتتاحي.'];
    }
}
