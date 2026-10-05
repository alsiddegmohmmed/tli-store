<?php

namespace App\Http\Controllers;

use App\Domain\Shifts\RecordCashTransfer;
use App\Http\Requests\RecordCashTransferRequest;
use App\Models\CashierShift;
use App\Models\ShiftCashTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShiftCashTransferController extends Controller
{
    public function store(RecordCashTransferRequest $request, CashierShift $shift, RecordCashTransfer $action): JsonResponse
    {
        $transfer = $action->execute($request->user(), $shift->id, $request->transferData());

        return response()->json(['id' => $transfer->id, 'url' => route('shifts.cash-transfers.show', [$shift, $transfer])]);
    }

    public function show(Request $request, CashierShift $shift, ShiftCashTransfer $transfer): Response
    {
        Gate::authorize('view', $shift);
        abort_unless($transfer->shift_id === $shift->id, 404);
        $shift->load(['cashier:id,name', 'station:id,name']);
        $transfer->load('author:id,name');
        $journal = $request->user()->can('manage-cash') ? DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('ledger_accounts', 'journal_lines.account_code', '=', 'ledger_accounts.code')
            ->where('journal_entries.shift_cash_transfer_id', $transfer->id)->orderBy('journal_lines.id')
            ->get(['ledger_accounts.name', 'journal_lines.debit', 'journal_lines.credit']) : [];

        return Inertia::render('shifts/cash-transfer', ['shift' => $shift, 'transfer' => $transfer, 'journal' => $journal]);
    }
}
