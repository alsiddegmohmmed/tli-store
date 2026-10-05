<?php

namespace App\Http\Controllers;

use App\Domain\Shifts\CloseShift;
use App\Domain\Shifts\OpenShift;
use App\Domain\Shifts\ShiftTotals;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Models\CashierShift;
use App\Models\PosStation;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CashierShiftController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('shifts/index', [
            'shifts' => CashierShift::when(! $request->user()->can('manage-products'), fn ($query) => $query->where('cashier_id', $request->user()->id))
                ->with(['cashier:id,name', 'station:id,name'])->orderByDesc('id')->paginate(20),
            'stations' => PosStation::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'activeShift' => CashierShift::where('cashier_id', $request->user()->id)->whereNull('closed_at')->with('station:id,name')->first(),
        ]);
    }

    public function store(OpenShiftRequest $request, OpenShift $action): JsonResponse
    {
        $shift = $action->execute($request->user(), $request->openingData());

        return response()->json(['id' => $shift->id, 'station_id' => $shift->station_id, 'url' => route('shifts.show', $shift)]);
    }

    public function show(Request $request, CashierShift $shift, ShiftTotals $totals): Response
    {
        Gate::authorize('view', $shift);
        $shift->load(['cashier:id,name', 'station:id,name']);
        $summary = $shift->closed_at ? $shift->only(['sale_count', 'sales_total', 'cash_sales', 'card_sales', 'expected_cash']) : $totals->calculate($shift);

        return Inertia::render('shifts/show', ['shift' => $shift, 'summary' => $summary,
            'canClose' => $request->user()->can('close', $shift),
            'sales' => Sale::where('shift_id', $shift->id)->with(['cashier:id,name', 'station:id,name'])->orderByDesc('id')->paginate(20)]);
    }

    public function close(CloseShiftRequest $request, CashierShift $shift, CloseShift $action): RedirectResponse
    {
        /** @var array{counted_cash: numeric-string, closing_note?: ?string} $data */
        $data = $request->validated();
        $action->execute($request->user(), $shift->id, $data['counted_cash'], $data['closing_note'] ?? null);

        return to_route('shifts.show', $shift)->with('success', 'أُغلقت الوردية وحُفظت نتيجة المطابقة. لا يعني الإغلاق تحويل نقد أو تسوية بطاقة.');
    }
}
