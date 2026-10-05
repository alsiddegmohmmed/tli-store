<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = $filters['search'] ?? '';

        return Inertia::render('suppliers/index', ['search' => $search, 'suppliers' => Supplier::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'ilike', '%'.$search.'%')->orWhere('tax_number', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%')))
            ->orderByDesc('id')->paginate(20)->withQueryString()]);
    }

    public function store(SaveSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create([...$request->validated(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return to_route('suppliers.edit', $supplier)->with('success', 'حُفظ المورد. لم تُسجل فاتورة أو دفعة مالية.');
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('suppliers/edit', ['supplier' => $supplier]);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        return to_route('suppliers.edit', $supplier)->with('success', 'حُدّثت بيانات المورد.');
    }
}
