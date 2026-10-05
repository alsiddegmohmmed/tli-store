<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:60']]);
        $search = $filters['search'] ?? '';

        return Inertia::render('products/index', [
            'search' => $search,
            'products' => Product::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                    ->where('name', 'like', '%'.$search.'%')->orWhere('barcode', 'like', '%'.$search.'%')))
                ->orderBy('id', 'desc')->paginate(20)->withQueryString(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return to_route('products.index')->with('success', 'تمت إضافة الصنف.');
    }
}
