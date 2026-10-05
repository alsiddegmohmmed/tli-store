<?php

namespace App\Domain\Stock;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-type CountInput array{operation_id: string, product_id: int, expected_quantity: numeric-string, expected_revision: int, counted_quantity: numeric-string, reference: string, reason: string} */
class RecordStockCount
{
    /** @param CountInput $data */
    public function execute(User $user, array $data): StockCount
    {
        abort_unless($user->can('manage-products'), 403);
        $hash = hash('sha256', json_encode([$data['product_id'], bcadd($data['expected_quantity'], '0', 3), $data['expected_revision'],
            bcadd($data['counted_quantity'], '0', 3), $data['reference'], $data['reason']], JSON_THROW_ON_ERROR));
        $existing = StockCount::where('operation_id', $data['operation_id'])->first();
        if ($existing) {
            return $this->replay($existing, $user, $hash);
        }
        try {
            return DB::transaction(function () use ($user, $data, $hash): StockCount {
                // Same product/balance lock order as sale posting and opening stock.
                $product = Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
                $existing = StockCount::where('operation_id', $data['operation_id'])->first();
                if ($existing) {
                    return $this->replay($existing, $user, $hash);
                }
                $balance = InventoryBalance::whereKey($product->id)->lockForUpdate()->first();
                if (! $balance) {
                    throw ValidationException::withMessages(['product_id' => 'سجل الرصيد الافتتاحي الموثق أولًا. الكمية غير المسجلة لا تعني صفرًا.']);
                }
                if ($balance->revision !== $data['expected_revision'] || bccomp($balance->quantity, $data['expected_quantity'], 3) !== 0) {
                    throw ValidationException::withMessages(['expected_quantity' => 'تغير رصيد الصنف منذ فتح النموذج. حدّث الرصيد ثم أعد العد قبل التأكيد.']);
                }
                $difference = bcsub($data['counted_quantity'], $balance->quantity, 3);
                $count = StockCount::create(['operation_id' => $data['operation_id'], 'request_hash' => $hash, 'product_id' => $product->id,
                    'created_by' => $user->id, 'product_snapshot' => $product->only(['name', 'barcode', 'unit']),
                    'recorded_quantity' => $balance->quantity, 'counted_quantity' => bcadd($data['counted_quantity'], '0', 3),
                    'difference' => $difference, 'balance_revision' => $balance->revision, 'reference' => $data['reference'], 'reason' => $data['reason'], 'recorded_at' => now()]);
                $balance->recordQuantity($data['counted_quantity']);
                if (bccomp($difference, '0', 3) !== 0) {
                    DB::table('stock_movements')->insert(['product_id' => $product->id, 'stock_count_id' => $count->id,
                        'quantity' => $difference, 'created_at' => now()]);
                }

                return $count;
            }, 3);
        } catch (QueryException $error) {
            if ($error->getCode() !== '23505' || ! str_contains($error->getMessage(), 'stock_counts_operation_id_unique')) {
                throw $error;
            }

            return $this->replay(StockCount::where('operation_id', $data['operation_id'])->firstOrFail(), $user, $hash);
        }
    }

    private function replay(StockCount $count, User $user, string $hash): StockCount
    {
        abort_unless($count->created_by === $user->id && hash_equals($count->request_hash, $hash), 409, 'هذه المحاولة محفوظة ببيانات مختلفة أو بحساب آخر. راجع سجل الجرد قبل المتابعة.');

        return $count;
    }
}
