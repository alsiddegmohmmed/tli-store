<?php

namespace App\Http\Controllers;

use App\Domain\Sales\InclusiveLineCalculator;
use App\Http\Requests\QuoteRequest;
use App\Models\PosStation;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('pos/index', [
            'stations' => PosStation::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $input = $request->validate(['barcode' => ['nullable', 'string', 'max:64'], 'search' => ['nullable', 'string', 'min:2', 'max:60']]);
        if (! empty($input['barcode'])) {
            $product = Product::where('is_active', true)->where('barcode', $input['barcode'])->first();

            return $product ? response()->json(['products' => [$product]])
                : response()->json(['message' => 'لم يتم العثور على هذا الباركود. يمكنك البحث باسم الصنف.'], 404);
        }
        if (empty($input['search'])) {
            return response()->json(['products' => []]);
        }

        return response()->json(['products' => Product::where('is_active', true)
            ->where('name', 'like', '%'.$input['search'].'%')->orderBy('name')->limit(15)->get()]);
    }

    public function quote(QuoteRequest $request, InclusiveLineCalculator $calculator): JsonResponse
    {
        $data = $request->validated();
        $products = Product::where('is_active', true)->whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
        if ($products->count() !== count($data['items'])) {
            throw ValidationException::withMessages(['items' => 'أحد الأصناف غير متاح. راجع السلة.']);
        }
        $totals = ['net' => '0.00', 'tax' => '0.00', 'total' => '0.00'];
        $lines = [];
        foreach ($data['items'] as $item) {
            $product = $products[$item['product_id']];
            $amounts = $calculator->calculate($product->price, $item['quantity'], $product->tax_rate);
            $lines[] = ['product_id' => $product->id, 'quantity' => $item['quantity'], 'unit_price' => $product->price, ...$amounts];
            foreach (array_keys($totals) as $key) {
                $totals[$key] = bcadd($totals[$key], $amounts[$key], 2);
            }
        }

        return response()->json(['lines' => $lines, 'totals' => $totals]);
    }
}
