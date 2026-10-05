<?php

namespace App\Http\Requests;

use App\Models\CashierShift;
use Illuminate\Foundation\Http\FormRequest;

/** @phpstan-import-type TransferInput from \App\Domain\Shifts\RecordCashTransfer */
class RecordCashTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('shift');

        return $shift instanceof CashierShift && ($this->user()?->can('transferCash', $shift) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['operation_id' => ['required', 'uuid'], 'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'string', 'regex:/^\d{1,18}(\.\d{1,2})?$/D', 'gt:0'],
            'reference' => ['required', 'string', 'max:120'], 'reason' => ['required', 'string', 'max:500']];
    }

    /** @return TransferInput */
    public function transferData(): array
    {
        /** @var TransferInput $data */
        $data = $this->validated();

        return $data;
    }

    public function messages(): array
    {
        return ['operation_id.*' => 'تعذر تحديد محاولة التحويل. راجع السجل قبل المتابعة.',
            'direction.*' => 'اختر اتجاه التحويل بين الصندوق والخزنة الرئيسية.',
            'amount.*' => 'أدخل مبلغًا أكبر من صفر، بخانتين عشريتين كحد أقصى.',
            'reference.*' => 'أدخل مرجع التحويل بما لا يتجاوز 120 حرفًا.',
            'reason.*' => 'أدخل سبب التحويل بما لا يتجاوز 500 حرف.'];
    }
}
