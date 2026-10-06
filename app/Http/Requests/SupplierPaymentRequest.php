<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** @phpstan-import-type SupplierPaymentInput from \App\Domain\Purchasing\RecordSupplierPayment */
class SupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-purchases') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['operation_id' => ['required', 'uuid'], 'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/D', 'gt:0'], 'method' => ['required', 'in:cash_reserve,bank'],
            'reference' => ['required', 'string', 'max:120'], 'funding_reference' => ['required', 'string', 'max:120'],
            'actual_payer' => ['required', 'string', 'max:120'], 'confirmed_paid_from_company' => ['required', 'accepted']];
    }

    /** @return SupplierPaymentInput */
    public function paymentData(): array
    {
        /** @var SupplierPaymentInput $data */
        $data = $this->validated();
        $data['confirmed_paid_from_company'] = $this->boolean('confirmed_paid_from_company');

        return $data;
    }

    public function messages(): array
    {
        return ['operation_id.*' => 'تعذر تحديد محاولة الحفظ. راجع الدفعات قبل المتابعة.',
            'payment_date.*' => 'أدخل تاريخ سداد صحيحًا لا يتجاوز اليوم.', 'amount.*' => 'أدخل مبلغًا أكبر من صفر، بحد أقصى 12 رقمًا وخانتين عشريتين.',
            'method.*' => 'اختر الدفع من خزنة الشركة الرئيسية أو حسابها البنكي.', 'reference.*' => 'أدخل مرجع إثبات الدفع بما لا يتجاوز 120 حرفًا.',
            'funding_reference.*' => 'حدد مصدر أموال الشركة بما لا يتجاوز 120 حرفًا.', 'actual_payer.*' => 'حدد من دفع فعليًا بما لا يتجاوز 120 حرفًا.',
            'confirmed_paid_from_company.*' => 'أكد الدفع الفعلي من أموال الشركة قبل تسجيله.'];
    }
}
