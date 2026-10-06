<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\ReadDocumentJournal;
use App\Domain\Sales\RecordSale;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function store(StoreSaleRequest $request, RecordSale $action): JsonResponse
    {
        $sale = $action->execute($request->user(), $request->saleData())->refresh();

        return response()->json(['id' => $sale->id, 'station_id' => $sale->station_id, 'total' => $sale->total,
            'change' => $sale->change, 'fiscal_status' => $sale->fiscal_status, 'url' => route('sales.show', $sale)]);
    }

    public function index(Request $request): Response
    {
        $sales = Sale::query()->when(! $request->user()->can('manage-products'), fn ($query) => $query->where('cashier_id', $request->user()->id))
            ->with(['cashier:id,name', 'station:id,name'])->orderByDesc('id')->paginate(20);

        return Inertia::render('sales/index', ['sales' => $sales]);
    }

    public function show(Request $request, Sale $sale, ReadDocumentJournal $journals): Response
    {
        Gate::authorize('view', $sale);
        $sale->load(['cashier:id,name', 'station:id,name', 'lines', 'payments']);
        $journal = $request->user()->can('manage-products') ? $journals->execute(['sale_id' => $sale->id]) : [];

        return Inertia::render('sales/show', ['sale' => $sale, 'journal' => $journal]);
    }
}
