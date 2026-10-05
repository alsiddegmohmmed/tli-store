<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SaleReceiptController extends Controller
{
    public function show(Request $request, Sale $sale): Response
    {
        Gate::authorize('view', $sale);
        $input = $request->validate(['width' => ['sometimes', 'string', 'in:58,80']], ['width.*' => 'اختر عرض الورق 58 أو 80 مم.']);
        $sale->load(['cashier:id,name', 'station:id,name', 'lines', 'payments']);

        return Inertia::render('sales/receipt', ['sale' => $sale, 'paperWidth' => $input['width'] ?? '80']);
    }
}
