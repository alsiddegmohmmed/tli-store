<?php

namespace Tests\Unit;

use App\Domain\Sales\InclusiveLineCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InclusiveLineCalculatorTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_tax_inclusive_amounts_reconcile(string $price, string $quantity, string $rate, array $expected): void
    {
        $actual = (new InclusiveLineCalculator)->calculate($price, $quantity, $rate);

        $this->assertSame($expected, $actual);
        $this->assertSame($actual['total'], bcadd($actual['net'], $actual['tax'], 2));
    }

    public static function amounts(): array
    {
        return [
            'standard tax' => ['115.00', '1', '15.00', ['net' => '100.00', 'tax' => '15.00', 'total' => '115.00']],
            'fractional quantity' => ['11.50', '0.5', '15.00', ['net' => '5.00', 'tax' => '0.75', 'total' => '5.75']],
            'half cent rounds up' => ['0.67', '0.5', '0.00', ['net' => '0.34', 'tax' => '0.00', 'total' => '0.34']],
            'zero price' => ['0.00', '1', '15.00', ['net' => '0.00', 'tax' => '0.00', 'total' => '0.00']],
            'large amount' => ['9999999999.99', '10000', '0.00', ['net' => '99999999999900.00', 'tax' => '0.00', 'total' => '99999999999900.00']],
        ];
    }
}
