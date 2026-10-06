<?php

namespace App\Domain\Purchasing;

use App\Domain\Accounting\JournalPoster;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-type BillInput array{operation_id: string, supplier_id: int, reference: string, bill_date: string, description: string, total: numeric-string, printed_tax: numeric-string|null, confirmed_unpaid: bool} */
class RecordPurchaseBill
{
    public function __construct(private JournalPoster $journals) {}

    /** @param BillInput $data */
    public function execute(User $user, array $data): PurchaseBill
    {
        abort_unless($user->can('manage-purchases'), 403);
        $canonical = ['supplier_id' => $data['supplier_id'], 'reference' => $data['reference'], 'bill_date' => $data['bill_date'],
            'description' => $data['description'], 'total' => bcadd($data['total'], '0', 2),
            'printed_tax' => $data['printed_tax'] === null ? null : bcadd($data['printed_tax'], '0', 2), 'confirmed_unpaid' => $data['confirmed_unpaid']];
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
        $existing = PurchaseBill::where('operation_id', $data['operation_id'])->first();
        if ($existing) {
            return $this->replay($existing, $user, $hash);
        }
        if (! $data['confirmed_unpaid'] || bccomp($canonical['total'], '0', 2) <= 0
            || ($canonical['printed_tax'] !== null && (bccomp($canonical['printed_tax'], '0', 2) < 0 || bccomp($canonical['printed_tax'], $canonical['total'], 2) > 0))) {
            throw ValidationException::withMessages(['total' => 'راجع إجمالي المستند والضريبة، وأكد أن الفاتورة لم تُدفع ولم تُسجل سابقًا.']);
        }
        try {
            return DB::transaction(function () use ($user, $data, $canonical, $hash): PurchaseBill {
                $supplier = Supplier::whereKey($data['supplier_id'])->lockForUpdate()->firstOrFail();
                $existing = PurchaseBill::where('operation_id', $data['operation_id'])->first();
                if ($existing) {
                    return $this->replay($existing, $user, $hash);
                }
                if (! $supplier->is_active) {
                    throw ValidationException::withMessages(['supplier_id' => 'المورد غير نشط. راجع سجله قبل إضافة فاتورة جديدة.']);
                }
                $key = mb_strtolower(trim($data['reference']));
                if (PurchaseBill::where('supplier_id', $supplier->id)->where('reference_key', $key)->exists()) {
                    throw ValidationException::withMessages(['reference' => 'رقم المستند مسجل لهذا المورد. راجع سجل الفواتير؛ لا تضف المبلغ مرة أخرى.']);
                }
                $bill = PurchaseBill::create(['operation_id' => $data['operation_id'], 'request_hash' => $hash, 'supplier_id' => $supplier->id,
                    'supplier_snapshot' => $supplier->only(['name', 'tax_number', 'commercial_registration', 'phone', 'city']),
                    'created_by' => $user->id, 'reference' => $data['reference'], 'reference_key' => $key, 'bill_date' => $data['bill_date'],
                    'description' => $data['description'], 'total' => $canonical['total'], 'printed_tax' => $canonical['printed_tax'], 'recorded_at' => now()]);
                $this->journals->post(['purchase_bill_id' => $bill->id], $user->id, $data['bill_date'], 'فاتورة مورد '.$bill->id.' — بانتظار التصنيف', [
                    ['account_code' => 'purchase_review', 'debit' => $bill->total],
                    ['account_code' => 'supplier_payable', 'credit' => $bill->total],
                ]);

                return $bill;
            }, 3);
        } catch (QueryException $error) {
            if ($error->getCode() !== '23505' || ! str_contains($error->getMessage(), 'purchase_bills_operation_id_unique')) {
                throw $error;
            }

            return $this->replay(PurchaseBill::where('operation_id', $data['operation_id'])->firstOrFail(), $user, $hash);
        }
    }

    private function replay(PurchaseBill $bill, User $user, string $hash): PurchaseBill
    {
        abort_unless($bill->created_by === $user->id && hash_equals($bill->request_hash, $hash), 409,
            'هذه المحاولة محفوظة ببيانات مختلفة. راجع سجل الفواتير قبل المتابعة.');

        return $bill;
    }
}
