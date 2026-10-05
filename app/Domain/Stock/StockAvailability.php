<?php

namespace App\Domain\Stock;

use App\Models\InventoryBalance;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StockAvailability
{
    /** @param list<array{product_id: int, name: string, quantity: numeric-string}> $lines
     * @param  Collection<int, InventoryBalance>  $balances
     */
    public function assertAvailable(array $lines, Collection $balances): void
    {
        foreach ($lines as $line) {
            $balance = $balances->get($line['product_id']);
            if (! $balance || bccomp($balance->quantity, $line['quantity'], 3) < 0) {
                throw ValidationException::withMessages(['items' => 'المخزون غير كافٍ للصنف «'.$line['name'].'». اطلب من المحاسب مراجعة الرصيد.']);
            }
        }
    }
}
