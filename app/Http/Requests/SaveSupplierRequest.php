<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-purchases') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'tax_number' => ['nullable', 'string', 'max:40'],
            'commercial_registration' => ['nullable', 'string', 'max:40'], 'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:1000'], 'is_active' => ['required', 'boolean']];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم المورد', 'tax_number' => 'الرقم الضريبي', 'commercial_registration' => 'السجل التجاري',
            'phone' => 'رقم الاتصال', 'city' => 'المدينة', 'notes' => 'الملاحظات', 'is_active' => 'حالة المورد'];
    }
}
