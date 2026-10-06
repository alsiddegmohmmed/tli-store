<?php

namespace App\Domain\Purchasing;

use App\Models\PurchaseBill;
use App\Models\SupplierPayment;

class BillBalance
{
    /** @return array{paid: numeric-string, remaining: numeric-string} */
    public function calculate(PurchaseBill $bill): array
    {
        /** @var numeric-string $sum */
        $sum = (string) SupplierPayment::where('purchase_bill_id', $bill->id)->sum('amount');

        return $this->fromPaid($bill->total, $sum);
    }

    /**
     * @param  numeric-string  $total
     * @param  numeric-string  $sum
     * @return array{paid: numeric-string, remaining: numeric-string}
     */
    public function fromPaid(string $total, string $sum): array
    {
        $paid = bcadd($sum, '0', 2);

        return ['paid' => $paid, 'remaining' => bcsub($total, $paid, 2)];
    }
}
