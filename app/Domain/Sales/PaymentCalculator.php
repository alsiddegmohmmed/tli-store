<?php

namespace App\Domain\Sales;

use Illuminate\Validation\ValidationException;

class PaymentCalculator
{
    /**
     * @param  numeric-string  $total
     * @param  numeric-string  $cashTender
     * @param  numeric-string  $cardAmount
     * @return array{total: numeric-string, cash_tender: numeric-string, cash_applied: numeric-string, card_amount: numeric-string, change: numeric-string}
     */
    public function calculate(string $total, string $cashTender, string $cardAmount): array
    {
        if (bccomp($total, '0', 2) <= 0) {
            throw ValidationException::withMessages(['items' => 'يجب أن يكون إجمالي البيع أكبر من صفر.']);
        }
        if (bccomp($cardAmount, $total, 2) > 0) {
            throw ValidationException::withMessages(['card_amount' => 'مبلغ البطاقة يتجاوز إجمالي البيع.']);
        }
        $cashApplied = bcsub($total, $cardAmount, 2);
        if (bccomp($cashTender, $cashApplied, 2) < 0) {
            throw ValidationException::withMessages(['cash_tender' => 'المبلغ المستلم لا يغطي إجمالي البيع.']);
        }
        if (bccomp($cashApplied, '0', 2) === 0 && bccomp($cashTender, '0', 2) > 0) {
            throw ValidationException::withMessages(['cash_tender' => 'البطاقة تغطي الإجمالي. اجعل المبلغ النقدي صفرًا.']);
        }

        return ['total' => $total, 'cash_tender' => bcadd($cashTender, '0', 2), 'cash_applied' => $cashApplied,
            'card_amount' => bcadd($cardAmount, '0', 2), 'change' => bcsub($cashTender, $cashApplied, 2)];
    }
}
