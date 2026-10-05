<?php

namespace App\Http\Controllers;

use App\Domain\Stock\RecordOpeningStock;
use App\Http\Requests\OpeningStockRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:60']])['search'] ?? '';
        $products = Product::query()->leftJoin('inventory_balances', 'products.id', '=', 'inventory_balances.product_id')
            ->leftJoin('stock_openings', 'products.id', '=', 'stock_openings.product_id')
            ->select(['products.id', 'products.name', 'products.barcode', 'products.unit', 'inventory_balances.quantity',
                'stock_openings.reference', 'stock_openings.opening_date'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('products.name', 'like', '%'.$search.'%')->orWhere('products.barcode', 'like', '%'.$search.'%')))
            ->orderBy('products.id')->paginate(20)->withQueryString();

        return Inertia::render('stock/index', ['products' => $products, 'search' => $search, 'today' => now('Asia/Riyadh')->toDateString()]);
    }

    public function store(OpeningStockRequest $request, RecordOpeningStock $action): RedirectResponse
    {
        $action->execute($request->user(), $request->openingData());

        return back()->with('success', 'سُجلت الكمية الافتتاحية ومحضرها. لم تُحسب قيمة المخزون بعد.');
    }
}
