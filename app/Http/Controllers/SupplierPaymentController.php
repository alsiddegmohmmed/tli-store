<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\ReadDocumentJournal;
use App\Domain\Purchasing\RecordSupplierPayment;
use App\Http\Requests\SupplierPaymentRequest;
use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPaymentController extends Controller
{
    public function store(SupplierPaymentRequest $request, PurchaseBill $bill, RecordSupplierPayment $action): JsonResponse
    {
        $payment = $action->execute($request->user(), $bill->id, $request->paymentData());

        return response()->json(['id' => $payment->id, 'url' => route('purchases.payments.show', [$bill, $payment])]);
    }

    public function show(PurchaseBill $bill, SupplierPayment $payment, ReadDocumentJournal $journals): Response
    {
        abort_unless($payment->purchase_bill_id === $bill->id, 404);

        return Inertia::render('purchases/payment', ['bill' => $bill, 'payment' => $payment->load('author:id,name'),
            'journal' => $journals->execute(['supplier_payment_id' => $payment->id])]);
    }
}
