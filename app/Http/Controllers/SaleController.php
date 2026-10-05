<?php

namespace App\Http\Controllers;

use App\Domain\Sales\RecordSale;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function show(Request $request, Sale $sale): Response
    {
        abort_unless($request->user()->can('manage-products') || $sale->cashier_id === $request->user()->id, 403);
        $sale->load(['cashier:id,name', 'station:id,name', 'lines', 'payments']);
        $journal = $request->user()->can('manage-products') ? DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('ledger_accounts', 'journal_lines.account_code', '=', 'ledger_accounts.code')
            ->where('journal_entries.sale_id', $sale->id)->orderBy('journal_lines.id')
            ->get(['ledger_accounts.name', 'journal_lines.debit', 'journal_lines.credit']) : [];

        return Inertia::render('sales/show', ['sale' => $sale, 'journal' => $journal]);
    }
}
