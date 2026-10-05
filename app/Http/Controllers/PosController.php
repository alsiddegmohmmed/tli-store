<?php

namespace App\Http\Controllers;

use App\Domain\Sales\BasketCalculator;
use App\Domain\Sales\PaymentCalculator;
use App\Domain\Stock\StockAvailability;
use App\Http\Requests\PaymentPreviewRequest;
use App\Http\Requests\QuoteRequest;
use App\Models\InventoryBalance;
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
            'canRecordSales' => app()->environment('local', 'testing'),
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

    public function quote(QuoteRequest $request, BasketCalculator $calculator): JsonResponse
    {
        $data = $request->validated();
        $products = Product::where('is_active', true)->whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');

        return response()->json($calculator->calculate($data['items'], $products));
    }

    public function paymentPreview(PaymentPreviewRequest $request, BasketCalculator $basket, PaymentCalculator $payments, StockAvailability $availability): JsonResponse
    {
        $data = $request->validated();
        $products = Product::whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
        $quote = $basket->calculate($data['items'], $products);
        if (! hash_equals($quote['fingerprint'], $data['quote_fingerprint'])) {
            throw ValidationException::withMessages(['items' => 'تغيرت بيانات السلة. أعد حساب الإجمالي وراجع الدفع.']);
        }

        $availability->assertAvailable($quote['lines'], InventoryBalance::whereIn('product_id', $products->keys())->get()->keyBy('product_id'));

        return response()->json($payments->calculate($quote['totals']['total'], $data['cash_tender'], $data['card_amount']));
    }
}
