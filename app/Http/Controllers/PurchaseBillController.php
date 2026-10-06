<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\ReadDocumentJournal;
use App\Domain\Evidence\RecordEvidence;
use App\Domain\Purchasing\BillBalance;
use App\Domain\Purchasing\RecordPurchaseBill;
use App\Http\Requests\PurchaseBillRequest;
use App\Models\DocumentEvidence;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseBillController extends Controller
{
    public function index(Request $request, BillBalance $balances): Response
    {
        $filters = $request->validate(['supplier_id' => ['nullable', 'integer', 'exists:suppliers,id']]);

        return Inertia::render('purchases/index', ['bills' => PurchaseBill::query()->withSum('payments as paid_amount', 'amount')->when($filters['supplier_id'] ?? null,
            fn ($query, $id) => $query->where('supplier_id', $id))->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(function (PurchaseBill $bill) use ($balances): array {
                /** @var numeric-string $paid */
                $paid = (string) ($bill->getAttribute('paid_amount') ?? '0');

                return [...$bill->toArray(), 'balance' => $balances->fromPaid($bill->total, $paid)];
            }),
            'supplier' => isset($filters['supplier_id']) ? Supplier::findOrFail($filters['supplier_id']) : null]);
    }

    public function create(Supplier $supplier): Response
    {
        return Inertia::render('purchases/create', ['supplier' => $supplier, 'today' => now('Asia/Riyadh')->toDateString()]);
    }

    public function store(PurchaseBillRequest $request, RecordPurchaseBill $action): JsonResponse
    {
        $bill = $action->execute($request->user(), $request->billData());

        return response()->json(['id' => $bill->id, 'url' => route('purchases.show', $bill)]);
    }

    public function show(PurchaseBill $bill, ReadDocumentJournal $journals, BillBalance $balances): Response
    {
        return Inertia::render('purchases/show', ['bill' => $bill->load('author:id,name'), 'journal' => $journals->execute(['purchase_bill_id' => $bill->id]),
            'balance' => $balances->calculate($bill), 'payments' => SupplierPayment::where('purchase_bill_id', $bill->id)->orderByDesc('id')->paginate(20)->withQueryString(),
            'today' => now('Asia/Riyadh')->toDateString(),
            'evidence' => DocumentEvidence::where(RecordEvidence::source($bill))->with('author:id,name')->orderByDesc('id')->paginate(10, ['*'], 'evidence_page')->withQueryString(),
            'evidenceKinds' => RecordEvidence::kinds($bill)]);
    }
}
