<?php

namespace App\Domain\Evidence;

use App\Models\DocumentEvidence;
use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RecordEvidence
{
    public const MAX_KILOBYTES = 2048;

    public const MIME_EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** @return array<string, string> */
    public static function kinds(PurchaseBill|SupplierPayment $document): array
    {
        return $document instanceof PurchaseBill
            ? ['tax_invoice' => 'فاتورة ضريبية', 'non_tax_receipt' => 'مستند غير ضريبي', 'other' => 'مستند آخر']
            : ['bank_proof' => 'إثبات دفع بنكي', 'cash_voucher' => 'سند صرف نقدي', 'other' => 'مستند آخر'];
    }

    /** @return array{purchase_bill_id: int}|array{supplier_payment_id: int} */
    public static function source(PurchaseBill|SupplierPayment $document): array
    {
        return $document instanceof PurchaseBill
            ? ['purchase_bill_id' => $document->id]
            : ['supplier_payment_id' => $document->id];
    }

    public function execute(User $user, PurchaseBill|SupplierPayment $document, UploadedFile $file, string $kind, ?string $paperLocation): DocumentEvidence
    {
        abort_unless($user->can('manage-purchases'), 403);
        $mime = $file->getMimeType();
        if (! $file->isValid() || ! isset(self::MIME_EXTENSIONS[$mime]) || $file->getSize() <= 0 || $file->getSize() > self::MAX_KILOBYTES * 1024) {
            throw ValidationException::withMessages(['file' => 'ارفع ملف PDF أو JPG أو PNG صالحًا، بحد أقصى 2 ميغابايت.']);
        }
        if (! isset(self::kinds($document)[$kind])) {
            throw ValidationException::withMessages(['kind' => 'اختر نوع الإثبات المناسب للمستند.']);
        }
        $hash = hash_file('sha256', $file->path());
        if ($hash === false) {
            throw ValidationException::withMessages(['file' => 'تعذر قراءة الملف. أعد اختياره وحاول مرة أخرى.']);
        }
        $path = null;
        try {
            return DB::transaction(function () use ($user, $document, $file, $kind, $paperLocation, $mime, $hash, &$path): DocumentEvidence {
                $document->newQuery()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                $source = self::source($document);
                $existing = DocumentEvidence::where($source)->where('sha256', $hash)->first();
                if ($existing) {
                    return $existing;
                }
                $path = Storage::disk('local')->putFile('evidence', $file, 'private');
                if ($path === false) {
                    throw ValidationException::withMessages(['file' => 'تعذر حفظ الملف. لم يُسجل المرفق؛ حاول مرة أخرى.']);
                }

                return DocumentEvidence::create([...$source, 'created_by' => $user->id, 'kind' => $kind,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'storage_path' => $path,
                    'mime_type' => $mime, 'extension' => self::MIME_EXTENSIONS[$mime], 'size' => $file->getSize(),
                    'sha256' => $hash, 'paper_location' => $paperLocation, 'recorded_at' => now()]);
            });
        } catch (\Throwable $error) {
            if (is_string($path)) {
                try {
                    // A lost commit response must not delete a file whose row committed.
                    $saved = DocumentEvidence::where('storage_path', $path)->first();
                    if ($saved) {
                        return $saved;
                    }
                    Storage::disk('local')->delete($path);
                } catch (\Throwable) {
                    // Keep private bytes when database outcome cannot be established.
                }
            }
            throw $error;
        }
    }
}
