<?php

namespace Tests\Unit;

use App\Domain\Sales\PaymentCalculator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentCalculatorTest extends TestCase
{
    #[DataProvider('payments')]
    public function test_applied_payments_and_change_reconcile(string $cash, string $card, string $applied, string $change): void
    {
        $actual = (new PaymentCalculator)->calculate('115.00', $cash, $card);
        $this->assertSame($applied, $actual['cash_applied']);
        $this->assertSame($change, $actual['change']);
        $this->assertSame('115.00', bcadd($actual['cash_applied'], $actual['card_amount'], 2));
    }

    public static function payments(): array
    {
        return [['120', '0', '115.00', '5.00'], ['0', '115', '0.00', '0.00'], ['100', '50', '65.00', '35.00']];
    }

    #[DataProvider('invalidPayments')]
    public function test_invalid_tenders_are_rejected(string $cash, string $card): void
    {
        $this->expectException(ValidationException::class);
        (new PaymentCalculator)->calculate('115.00', $cash, $card);
    }

    public static function invalidPayments(): array
    {
        return [['114.99', '0'], ['0', '115.01'], ['1', '115']];
    }
}
