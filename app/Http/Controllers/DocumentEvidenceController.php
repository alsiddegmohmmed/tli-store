<?php

namespace App\Http\Controllers;

use App\Domain\Evidence\RecordEvidence;
use App\Http\Requests\DocumentEvidenceRequest;
use App\Models\DocumentEvidence;
use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentEvidenceController extends Controller
{
    public function bill(DocumentEvidenceRequest $request, PurchaseBill $bill, RecordEvidence $action): RedirectResponse
    {
        $this->record($request, $bill, $action);

        return to_route('purchases.show', $bill)->with('success', 'حُفظ المرفق أو استُعيد الملف المسجل نفسه. لم تتغير مبالغ الفاتورة.');
    }

    public function payment(DocumentEvidenceRequest $request, PurchaseBill $bill, SupplierPayment $payment, RecordEvidence $action): RedirectResponse
    {
        abort_unless($payment->purchase_bill_id === $bill->id, 404);
        $this->record($request, $payment, $action);

        return to_route('purchases.payments.show', [$bill, $payment])->with('success', 'حُفظ المرفق أو استُعيد الملف المسجل نفسه. لم تُنشأ دفعة أخرى.');
    }

    private function record(DocumentEvidenceRequest $request, PurchaseBill|SupplierPayment $document, RecordEvidence $action): void
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        /** @var array{kind: string, paper_location?: string|null} $data */
        $data = $request->validated();
        $action->execute($request->user(), $document, $file, $data['kind'], $data['paper_location'] ?? null);
    }

    public function download(DocumentEvidence $evidence): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($evidence->storage_path), 404, 'المرفق غير متاح. راجع المسؤول واستعادة الملفات الخاصة.');

        return Storage::disk('local')->download($evidence->storage_path, 'document-'.$evidence->id.'.'.$evidence->extension,
            ['Content-Type' => $evidence->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }
}
