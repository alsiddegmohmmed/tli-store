<?php

namespace App\Domain\Sales;

class InclusiveLineCalculator
{
    /**
     * @param  numeric-string  $price
     * @param  numeric-string  $quantity
     * @param  numeric-string  $taxRate
     * @return array{net: numeric-string, tax: numeric-string, total: numeric-string}
     */
    public function calculate(string $price, string $quantity, string $taxRate): array
    {
        // Preview policy: tax-inclusive prices; round each line half-up to two decimals.
        $total = $this->round(bcmul($price, $quantity, 6));
        $factor = bcadd('1', bcdiv($taxRate, '100', 8), 8);
        $net = $this->round(bcdiv($total, $factor, 16));

        return ['net' => $net, 'tax' => bcsub($total, $net, 2), 'total' => $total];
    }

    /** @param numeric-string $amount
     * @return numeric-string
     */
    private function round(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }
}
