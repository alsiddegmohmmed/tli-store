<?php

namespace App\Http\Controllers;

use App\Domain\Stock\RecordStockCount;
use App\Http\Requests\StockCountRequest;
use App\Models\StockCount;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockCountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('stock/counts', ['counts' => StockCount::with('author:id,name')->orderByDesc('id')->paginate(20)]);
    }

    public function store(StockCountRequest $request, RecordStockCount $action): JsonResponse
    {
        $count = $action->execute($request->user(), $request->countData());

        return response()->json(['id' => $count->id, 'url' => route('stock.counts.show', $count)]);
    }

    public function show(StockCount $count): Response
    {
        return Inertia::render('stock/count', ['count' => $count->load('author:id,name')]);
    }
}
