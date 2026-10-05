<?php

namespace App\Domain\Shifts;

use App\Models\CashierShift;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class ShiftTotals
{
    /** @return array{sale_count: int, sales_total: numeric-string, cash_sales: numeric-string, card_sales: numeric-string, cash_in: numeric-string, cash_out: numeric-string, expected_cash: numeric-string} */
    public function calculate(CashierShift $shift): array
    {
        $sales = Sale::where('shift_id', $shift->id)->selectRaw('COUNT(*) AS sale_count, COALESCE(SUM(total), 0) AS sales_total')->firstOrFail();
        $payments = DB::table('sale_payments')->join('sales', 'sale_payments.sale_id', '=', 'sales.id')->where('sales.shift_id', $shift->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN method = 'cash' THEN amount ELSE 0 END), 0) AS cash_sales, COALESCE(SUM(CASE WHEN method = 'card' THEN amount ELSE 0 END), 0) AS card_sales")->first();

        /** @var numeric-string $cash */
        $cash = (string) $payments->cash_sales;
        /** @var numeric-string $card */
        $card = (string) $payments->card_sales;
        /** @var numeric-string $gross */
        $gross = (string) $sales->getAttribute('sales_total');
        $transfers = DB::table('shift_cash_transfers')->where('shift_id', $shift->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) AS cash_in, COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) AS cash_out")->first();
        /** @var numeric-string $cashIn */
        $cashIn = (string) $transfers->cash_in;
        /** @var numeric-string $cashOut */
        $cashOut = (string) $transfers->cash_out;

        return ['sale_count' => (int) $sales->getAttribute('sale_count'), 'sales_total' => bcadd($gross, '0', 2),
            'cash_sales' => bcadd($cash, '0', 2), 'card_sales' => bcadd($card, '0', 2), 'cash_in' => bcadd($cashIn, '0', 2), 'cash_out' => bcadd($cashOut, '0', 2),
            'expected_cash' => bcsub(bcadd(bcadd($shift->opening_cash, $cash, 2), $cashIn, 2), $cashOut, 2)];
    }
}
