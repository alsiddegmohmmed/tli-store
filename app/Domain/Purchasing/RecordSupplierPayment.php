<?php

namespace App\Domain\Purchasing;

use App\Domain\Accounting\JournalPoster;
use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-type SupplierPaymentInput array{operation_id: string, payment_date: string, amount: numeric-string, method: string, reference: string, funding_reference: string, actual_payer: string, confirmed_paid_from_company: bool} */
class RecordSupplierPayment
{
    public function __construct(private BillBalance $balances, private JournalPoster $journals) {}

    /** @param SupplierPaymentInput $data */
    public function execute(User $user, int $billId, array $data): SupplierPayment
    {
        abort_unless($user->can('manage-purchases'), 403);
        $canonical = ['purchase_bill_id' => $billId, 'payment_date' => $data['payment_date'], 'amount' => bcadd($data['amount'], '0', 2),
            'method' => $data['method'], 'reference' => $data['reference'], 'funding_reference' => $data['funding_reference'],
            'actual_payer' => $data['actual_payer'], 'confirmed_paid_from_company' => $data['confirmed_paid_from_company']];
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
        $existing = SupplierPayment::where('operation_id', $data['operation_id'])->first();
        if ($existing) {
            return $this->replay($existing, $user, $hash);
        }
        if (! $data['confirmed_paid_from_company'] || bccomp($canonical['amount'], '0', 2) <= 0 || ! in_array($data['method'], ['cash_reserve', 'bank'], true)) {
            throw ValidationException::withMessages(['amount' => 'أدخل مبلغًا موجبًا وأكد الدفع الفعلي من أموال الشركة.']);
        }
        try {
            return DB::transaction(function () use ($user, $billId, $data, $canonical, $hash): SupplierPayment {
                // All allocations serialize on the bill, including payments from different sources.
                $bill = PurchaseBill::whereKey($billId)->lockForUpdate()->firstOrFail();
                $existing = SupplierPayment::where('operation_id', $data['operation_id'])->first();
                if ($existing) {
                    return $this->replay($existing, $user, $hash);
                }
                if ($data['payment_date'] < $bill->bill_date) {
                    throw ValidationException::withMessages(['payment_date' => 'تاريخ السداد أقدم من الفاتورة. الدفعات المقدمة تحتاج مسارًا منفصلًا.']);
                }
                if (bccomp($canonical['amount'], $this->balances->calculate($bill)['remaining'], 2) > 0) {
                    throw ValidationException::withMessages(['amount' => 'المبلغ يتجاوز المتبقي على الفاتورة. راجع الدفعات السابقة ولا تسجل الدفع مرتين.']);
                }
                $key = mb_strtolower(trim($data['reference']));
                if (SupplierPayment::where('purchase_bill_id', $billId)->where('method', $data['method'])->where('reference_key', $key)->exists()) {
                    throw ValidationException::withMessages(['reference' => 'مرجع الدفع مسجل على هذه الفاتورة بهذه الطريقة. راجع سجل الدفعات.']);
                }
                $payment = SupplierPayment::create(['operation_id' => $data['operation_id'], 'request_hash' => $hash, 'purchase_bill_id' => $billId,
                    'created_by' => $user->id, 'payment_date' => $data['payment_date'], 'amount' => $canonical['amount'], 'method' => $data['method'],
                    'reference' => $data['reference'], 'reference_key' => $key, 'funding_reference' => $data['funding_reference'],
                    'actual_payer' => $data['actual_payer'], 'recorded_at' => now()]);
                $this->journals->post(['supplier_payment_id' => $payment->id], $user->id, $data['payment_date'], 'سداد فاتورة المورد '.$billId, [
                    ['account_code' => 'supplier_payable', 'debit' => $payment->amount],
                    ['account_code' => $data['method'] === 'bank' ? 'company_bank' : 'cash_reserve', 'credit' => $payment->amount],
                ]);

                return $payment;
            }, 3);
        } catch (QueryException $error) {
            if ($error->getCode() !== '23505' || ! str_contains($error->getMessage(), 'supplier_payments_operation_id_unique')) {
                throw $error;
            }

            return $this->replay(SupplierPayment::where('operation_id', $data['operation_id'])->firstOrFail(), $user, $hash);
        }
    }

    private function replay(SupplierPayment $payment, User $user, string $hash): SupplierPayment
    {
        abort_unless($payment->created_by === $user->id && hash_equals($payment->request_hash, $hash), 409,
            'هذه المحاولة محفوظة ببيانات مختلفة. راجع سجل الدفعات قبل المتابعة.');

        return $payment;
    }
}
