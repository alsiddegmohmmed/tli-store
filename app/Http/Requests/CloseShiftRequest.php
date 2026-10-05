<?php

namespace App\Http\Requests;

use App\Models\CashierShift;
use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('shift');

        return $shift instanceof CashierShift && ($this->user()?->can('close', $shift) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['counted_cash' => ['required', 'string', 'regex:/^\d{1,18}(\.\d{1,2})?$/D'], 'closing_note' => ['nullable', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return ['counted_cash.*' => 'أدخل النقد المعدود فعليًا بصيغة صحيحة، بخانتين عشريتين كحد أقصى.', 'closing_note.*' => 'الملاحظة يجب ألا تتجاوز 500 حرف.'];
    }
}
