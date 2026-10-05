<?php

namespace App\Http\Requests;

class PaymentPreviewRequest extends QuoteRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(),
            'shift_id' => ['required', 'integer', 'exists:cashier_shifts,id'],
            'quote_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'cash_tender' => ['required', 'string', 'numeric', 'regex:/^\d{1,18}(\.\d{1,2})?$/'],
            'card_amount' => ['required', 'string', 'numeric', 'regex:/^\d{1,18}(\.\d{1,2})?$/'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(),
            'cash_tender.*' => 'أدخل المبلغ النقدي المستلم بصيغة صحيحة، بخانتين عشريتين كحد أقصى.',
            'card_amount.*' => 'أدخل مبلغ البطاقة بصيغة صحيحة، بخانتين عشريتين كحد أقصى.',
            'quote_fingerprint.*' => 'أعد حساب السلة قبل تسجيل الدفع.',
            'shift_id.*' => 'افتح وردية باسمك وراجع سجلها قبل تسجيل الدفع.',
        ];
    }
}
