<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\ReadDocumentJournal;
use App\Domain\Purchasing\RecordPurchaseBill;
use App\Http\Requests\PurchaseBillRequest;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseBillController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['supplier_id' => ['nullable', 'integer', 'exists:suppliers,id']]);

        return Inertia::render('purchases/index', ['bills' => PurchaseBill::query()->when($filters['supplier_id'] ?? null,
            fn ($query, $id) => $query->where('supplier_id', $id))->orderByDesc('id')->paginate(20)->withQueryString(),
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

    public function show(PurchaseBill $bill, ReadDocumentJournal $journals): Response
    {
        return Inertia::render('purchases/show', ['bill' => $bill->load('author:id,name'), 'journal' => $journals->execute(['purchase_bill_id' => $bill->id])]);
    }
}
