<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-company') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:120'],
            'vat_number' => ['required', 'string', 'regex:/^3[0-9]{13}3$/D'],
            'commercial_registration' => ['required', 'string', 'regex:/^[A-Za-z0-9]{1,20}$/D'],
            'street' => ['required', 'string', 'max:120'],
            'building_number' => ['required', 'string', 'regex:/^[0-9]{4}$/D'],
            'district' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'regex:/^[0-9]{5}$/D'],
        ];
    }

    public function attributes(): array
    {
        return ['legal_name' => 'الاسم القانوني', 'vat_number' => 'الرقم الضريبي', 'commercial_registration' => 'السجل التجاري',
            'street' => 'الشارع', 'building_number' => 'رقم المبنى', 'district' => 'الحي', 'city' => 'المدينة', 'postal_code' => 'الرمز البريدي'];
    }

    public function messages(): array
    {
        return ['vat_number.regex' => 'أدخل الرقم الضريبي من 15 رقمًا؛ يبدأ وينتهي بالرقم 3.',
            'commercial_registration.regex' => 'أدخل رقم السجل التجاري دون مسافات، بما لا يتجاوز 20 حرفًا أو رقمًا.',
            'building_number.regex' => 'أدخل رقم المبنى من 4 أرقام.', 'postal_code.regex' => 'أدخل الرمز البريدي من 5 أرقام.'];
    }
}
