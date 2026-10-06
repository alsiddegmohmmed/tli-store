<?php

namespace App\Http\Requests;

use App\Domain\Evidence\RecordEvidence;
use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;

class DocumentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-purchases') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $document = $this->route('payment') ?? $this->route('bill');
        abort_unless($document instanceof PurchaseBill || $document instanceof SupplierPayment, 404);

        return ['file' => ['required', 'file', 'mimetypes:'.implode(',', array_keys(RecordEvidence::MIME_EXTENSIONS)), 'max:'.RecordEvidence::MAX_KILOBYTES],
            'kind' => ['required', 'in:'.implode(',', array_keys(RecordEvidence::kinds($document)))], 'paper_location' => ['nullable', 'string', 'max:200']];
    }

    public function messages(): array
    {
        return ['file.*' => 'اختر ملف PDF أو JPG أو PNG بحد أقصى 2 ميغابايت.', 'kind.*' => 'اختر نوع الإثبات المناسب.',
            'paper_location.*' => 'أدخل مكان حفظ الأصل الورقي بما لا يتجاوز 200 حرف، أو اتركه فارغًا إن لم تعرفه.'];
    }
}
