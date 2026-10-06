<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** @phpstan-import-type BillInput from \App\Domain\Purchasing\RecordPurchaseBill */
class PurchaseBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-purchases') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['operation_id' => ['required', 'uuid'], 'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'reference' => ['required', 'string', 'max:120'], 'bill_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:500'], 'total' => ['required', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/D', 'gt:0'],
            'printed_tax' => ['nullable', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/D', 'lte:total'], 'confirmed_unpaid' => ['required', 'accepted']];
    }

    /** @return BillInput */
    public function billData(): array
    {
        /** @var BillInput $data */
        $data = $this->validated();
        $data['supplier_id'] = (int) $data['supplier_id'];
        $data['printed_tax'] ??= null;
        $data['confirmed_unpaid'] = $this->boolean('confirmed_unpaid');

        return $data;
    }

    public function attributes(): array
    {
        return ['operation_id' => 'هوية المحاولة', 'supplier_id' => 'المورد', 'reference' => 'رقم مستند المورد', 'bill_date' => 'تاريخ الفاتورة',
            'description' => 'البيان', 'total' => 'إجمالي المستند', 'printed_tax' => 'الضريبة كما في المستند', 'confirmed_unpaid' => 'تأكيد أن الفاتورة غير مدفوعة وغير مسجلة'];
    }

    public function messages(): array
    {
        return ['operation_id.*' => 'تعذر تحديد محاولة الحفظ. راجع سجل الفواتير قبل المتابعة.',
            'supplier_id.*' => 'اختر موردًا مسجلًا.', 'reference.*' => 'أدخل رقم مستند المورد بما لا يتجاوز 120 حرفًا.',
            'bill_date.*' => 'أدخل تاريخ فاتورة صحيحًا لا يتجاوز اليوم.', 'description.*' => 'أدخل بيان الفاتورة بما لا يتجاوز 500 حرف.',
            'total.*' => 'أدخل إجماليًا أكبر من صفر، بحد أقصى 12 رقمًا وخانتين عشريتين.',
            'printed_tax.lte' => 'الضريبة لا يمكن أن تتجاوز إجمالي المستند.',
            'printed_tax.*' => 'أدخل الضريبة كما في المستند بخانتين عشريتين كحد أقصى، أو اتركها فارغة إن لم تتأكد منها.',
            'confirmed_unpaid.*' => 'أكد أنه لم يُدفع أي مبلغ ولم تُسجل الفاتورة سابقًا قبل الحفظ.'];
    }
}
