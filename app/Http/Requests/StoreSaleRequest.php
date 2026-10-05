<?php

namespace App\Http\Requests;

/** @phpstan-import-type SaleInput from \App\Domain\Sales\RecordSale */
class StoreSaleRequest extends PaymentPreviewRequest
{
    /** @return SaleInput */
    public function saleData(): array
    {
        /** @var SaleInput $data */
        $data = $this->validated();
        $data['station_id'] = (int) $data['station_id'];
        $data['card_confirmed'] = (bool) $data['card_confirmed'];
        if (isset($data['shift_id'])) {
            $data['shift_id'] = (int) $data['shift_id'];
        }

        return $data;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(),
            // Replays must still recover a committed sale if a product/station was deactivated later.
            'station_id' => ['required', 'integer', 'exists:pos_stations,id'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            // Older committed attempts had no shift field. The action only accepts this for replay.
            'shift_id' => ['sometimes', 'nullable', 'integer', 'exists:cashier_shifts,id'],
            'operation_id' => ['required', 'uuid'],
            'card_confirmed' => ['required', 'boolean'],
            'card_reference' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(),
            'operation_id.*' => 'تعذر تحديد محاولة الحفظ. لا تكرر الدفع؛ أعد فتح الصفحة.',
            'card_confirmed.*' => 'حدد ما إذا نجحت عملية البطاقة على جهاز الدفع.',
            'card_reference.*' => 'أدخل مرجع عملية البطاقة بما لا يتجاوز 64 حرفًا.',
        ];
    }
}
