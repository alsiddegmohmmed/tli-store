<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** @phpstan-import-type CountInput from \App\Domain\Stock\RecordStockCount */
class StockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['operation_id' => ['required', 'uuid'], 'product_id' => ['required', 'integer', 'exists:products,id'],
            'expected_revision' => ['required', 'integer', 'min:0', 'max:'.PHP_INT_MAX],
            'expected_quantity' => ['required', 'string', 'regex:/^\d{1,15}(\.\d{1,3})?$/D'],
            'counted_quantity' => ['required', 'string', 'regex:/^\d{1,15}(\.\d{1,3})?$/D'],
            'reference' => ['required', 'string', 'max:120'], 'reason' => ['required', 'string', 'max:500']];
    }

    /** @return CountInput */
    public function countData(): array
    {
        /** @var CountInput $data */
        $data = $this->validated();
        $data['product_id'] = (int) $data['product_id'];
        $data['expected_revision'] = (int) $data['expected_revision'];

        return $data;
    }

    public function messages(): array
    {
        return ['operation_id.*' => 'تعذر تحديد محاولة الجرد. راجع السجل قبل المتابعة.', 'product_id.*' => 'اختر صنفًا مسجلًا.',
            'expected_revision.*' => 'حدّث رصيد الصنف وأعد العد قبل التأكيد.', 'expected_quantity.*' => 'تعذر تحديد الرصيد السابق. حدّث الصنف وأعد العد.',
            'counted_quantity.*' => 'أدخل كمية صفر أو أكثر، بثلاث خانات عشرية كحد أقصى.',
            'reference.*' => 'أدخل مرجع محضر الجرد بما لا يتجاوز 120 حرفًا.', 'reason.*' => 'أدخل سبب الجرد بما لا يتجاوز 500 حرف.'];
    }
}
