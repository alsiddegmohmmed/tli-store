<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** @phpstan-import-type OpeningInput from \App\Domain\Shifts\OpenShift */
class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['operation_id' => ['required', 'uuid'], 'station_id' => ['required', 'integer', 'exists:pos_stations,id'],
            'opening_cash' => ['required', 'string', 'regex:/^\d{1,18}(\.\d{1,2})?$/D']];
    }

    /** @return OpeningInput */
    public function openingData(): array
    {
        /** @var OpeningInput $data */
        $data = $this->validated();
        $data['station_id'] = (int) $data['station_id'];

        return $data;
    }

    public function messages(): array
    {
        return ['operation_id.*' => 'تعذر تحديد محاولة فتح الوردية. راجع السجل قبل المتابعة.',
            'station_id.*' => 'اختر صندوقًا مسجلًا في النظام.', 'opening_cash.*' => 'أدخل النقد المعدود عند الافتتاح بصيغة صحيحة، بخانتين عشريتين كحد أقصى.'];
    }
}
