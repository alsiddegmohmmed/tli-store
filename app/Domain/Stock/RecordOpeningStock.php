<?php

namespace App\Domain\Stock;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockOpening;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-type OpeningInput array{product_id: int, quantity: numeric-string, opening_date: string, reference: string} */
class RecordOpeningStock
{
    /** @param OpeningInput $data */
    public function execute(User $user, array $data): StockOpening
    {
        abort_unless($user->can('manage-products'), 403);

        return DB::transaction(function () use ($user, $data): StockOpening {
            Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            if (StockOpening::where('product_id', $data['product_id'])->exists()) {
                throw ValidationException::withMessages(['product_id' => 'سُجل الرصيد الافتتاحي لهذا الصنف. التصحيحات تحتاج تسوية مخزون منفصلة.']);
            }
            $opening = StockOpening::create([...$data, 'created_by' => $user->id]);
            InventoryBalance::create(['product_id' => $data['product_id'], 'quantity' => $data['quantity']]);
            DB::table('stock_movements')->insert(['product_id' => $data['product_id'], 'stock_opening_id' => $opening->id,
                'quantity' => $data['quantity'], 'created_at' => now()]);

            return $opening;
        }, 3);
    }
}
