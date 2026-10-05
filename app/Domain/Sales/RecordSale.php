<?php

namespace App\Domain\Sales;

use App\Domain\Accounting\JournalPoster;
use App\Domain\Stock\StockAvailability;
use App\Models\InventoryBalance;
use App\Models\PosStation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-type SaleInput array{operation_id: string, station_id: int, items: list<array{product_id: int, quantity: numeric-string}>, quote_fingerprint: string, cash_tender: numeric-string, card_amount: numeric-string, card_confirmed: bool, card_reference?: ?string} */
class RecordSale
{
    public function __construct(private BasketCalculator $baskets, private PaymentCalculator $payments, private JournalPoster $journals, private StockAvailability $availability) {}

    /** @param SaleInput $data */
    public function execute(User $user, array $data): Sale
    {
        // Official issuance is not connected yet. This slice permits development records only.
        abort_unless(app()->environment('local', 'testing'), 503, 'حفظ البيع متاح للتجربة فقط حتى اكتمال الفوترة الرسمية.');
        $hash = $this->requestHash($data);
        $existing = Sale::where('operation_id', $data['operation_id'])->first();
        if ($existing) {
            return $this->replay($existing, $user, $hash);
        }

        try {
            return DB::transaction(function () use ($user, $data, $hash): Sale {
                // Always lock station first, then products in ID order. One station's saves serialize.
                $station = PosStation::whereKey($data['station_id'])->lockForUpdate()->firstOrFail();
                $existing = Sale::where('operation_id', $data['operation_id'])->first();
                if ($existing) {
                    return $this->replay($existing, $user, $hash);
                }
                if (! $station->is_active) {
                    throw ValidationException::withMessages(['station_id' => 'هذا الصندوق غير متاح.']);
                }
                $products = Product::whereIn('id', array_column($data['items'], 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $quote = $this->baskets->calculate($data['items'], $products);
                if (! hash_equals($quote['fingerprint'], $data['quote_fingerprint'])) {
                    throw ValidationException::withMessages(['items' => 'تغيرت بيانات السلة. أعد حساب الإجمالي وراجع الدفع قبل الحفظ.']);
                }
                $payment = $this->payments->calculate($quote['totals']['total'], $data['cash_tender'], $data['card_amount']);
                if (bccomp($payment['card_amount'], '0', 2) > 0 && ! $data['card_confirmed']) {
                    throw ValidationException::withMessages(['card_confirmed' => 'تأكد من نجاح عملية البطاقة على جهاز الدفع أولًا.']);
                }
                $balances = InventoryBalance::whereIn('product_id', $products->keys())->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');
                $this->availability->assertAvailable($quote['lines'], $balances);
                $sale = Sale::create(['operation_id' => $data['operation_id'], 'request_hash' => $hash,
                    'cashier_id' => $user->id, 'station_id' => $station->id, ...$quote['totals'],
                    'cash_tender' => $payment['cash_tender'], 'change' => $payment['change'], 'recorded_at' => now()]);
                foreach ($quote['lines'] as $line) {
                    $saleLine = $sale->lines()->create($line);
                    $balance = $balances->get($line['product_id']);
                    $balance->update(['quantity' => bcsub($balance->quantity, $line['quantity'], 3)]);
                    DB::table('stock_movements')->insert(['product_id' => $line['product_id'], 'sale_line_id' => $saleLine->id,
                        'quantity' => bcsub('0', $line['quantity'], 3), 'created_at' => now()]);
                }
                if (bccomp($payment['cash_applied'], '0', 2) > 0) {
                    $sale->payments()->create(['method' => 'cash', 'amount' => $payment['cash_applied']]);
                }
                if (bccomp($payment['card_amount'], '0', 2) > 0) {
                    $sale->payments()->create(['method' => 'card', 'amount' => $payment['card_amount'],
                        'reference' => $data['card_reference'] ?? null, 'manually_confirmed' => true]);
                }
                $this->journals->post($sale->id, $user->id, now('Asia/Riyadh')->toDateString(), 'تسجيل بيع داخلي رقم '.$sale->id, [
                    ['account_code' => 'cash', 'debit' => $payment['cash_applied']],
                    ['account_code' => 'card_clearing', 'debit' => $payment['card_amount']],
                    ['account_code' => 'sales', 'credit' => $sale->net],
                    ['account_code' => 'output_vat', 'credit' => $sale->tax],
                ]);

                return $sale;
            }, 3);
        } catch (QueryException $error) {
            // The unique operation key also protects simultaneous requests through different stations.
            if ($error->getCode() !== '23505' || ! str_contains($error->getMessage(), 'sales_operation_id_unique')) {
                throw $error;
            }
            $existing = Sale::where('operation_id', $data['operation_id'])->firstOrFail();

            return $this->replay($existing, $user, $hash);
        }
    }

    private function replay(Sale $sale, User $user, string $hash): Sale
    {
        abort_unless($sale->cashier_id === $user->id, 409, 'محاولة الحفظ مرتبطة بحساب آخر. راجع المسؤول.');
        abort_unless(hash_equals($sale->request_hash, $hash), 409, 'هذه المحاولة محفوظة ببيانات مختلفة. راجع سجل البيع قبل المتابعة.');

        return $sale;
    }

    /** @param SaleInput $data */
    private function requestHash(array $data): string
    {
        $items = array_map(fn ($item) => ['product_id' => (int) $item['product_id'], 'quantity' => bcadd($item['quantity'], '0', 3)], $data['items']);
        usort($items, fn ($a, $b) => $a['product_id'] <=> $b['product_id']);

        return hash('sha256', json_encode([(int) $data['station_id'], $items, $data['quote_fingerprint'],
            bcadd($data['cash_tender'], '0', 2), bcadd($data['card_amount'], '0', 2), (bool) $data['card_confirmed'], $data['card_reference'] ?? null], JSON_THROW_ON_ERROR));
    }
}
