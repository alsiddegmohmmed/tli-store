<?php

namespace App\Domain\Sales;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BasketCalculator
{
    public function __construct(private InclusiveLineCalculator $lines) {}

    /**
     * @param  list<array{product_id: int, quantity: numeric-string}>  $items
     * @param  Collection<int, Product>  $products
     * @return array{lines: list<array{product_id: int, name: string, barcode: string, unit: string, quantity: numeric-string, unit_price: numeric-string, tax_rate: numeric-string, net: numeric-string, tax: numeric-string, total: numeric-string}>, totals: array{net: numeric-string, tax: numeric-string, total: numeric-string}, fingerprint: string}
     */
    public function calculate(array $items, Collection $products): array
    {
        usort($items, fn ($a, $b) => $a['product_id'] <=> $b['product_id']);
        $totals = ['net' => '0.00', 'tax' => '0.00', 'total' => '0.00'];
        $lines = [];
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages(['items' => 'أحد الأصناف غير متاح. راجع السلة.']);
            }
            $quantity = bcadd($item['quantity'], '0', 3);
            $amounts = $this->lines->calculate($product->price, $quantity, $product->tax_rate);
            $lines[] = ['product_id' => $product->id, 'name' => $product->name, 'barcode' => $product->barcode,
                'unit' => $product->unit, 'quantity' => $quantity, 'unit_price' => $product->price, 'tax_rate' => $product->tax_rate, ...$amounts];
            foreach (array_keys($totals) as $key) {
                $totals[$key] = bcadd($totals[$key], $amounts[$key], 2);
            }
        }
        $fingerprint = hash('sha256', json_encode([$lines, $totals], JSON_THROW_ON_ERROR));

        return compact('lines', 'totals', 'fingerprint');
    }
}
