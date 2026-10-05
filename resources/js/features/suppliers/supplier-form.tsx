import { useForm } from '@inertiajs/react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/suppliers';
import type { Supplier } from '@/types/store';

export default function SupplierForm({ supplier }: { supplier?: Supplier }) {
    const form = useForm({
        name: supplier?.name ?? '',
        tax_number: supplier?.tax_number ?? '',
        commercial_registration: supplier?.commercial_registration ?? '',
        phone: supplier?.phone ?? '',
        city: supplier?.city ?? '',
        notes: supplier?.notes ?? '',
        is_active: supplier?.is_active ?? true,
    });
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                if (supplier)
                    form.put(update.url(supplier.id), { preserveScroll: true });
                else form.post(store.url());
            }}
        >
            <fieldset
                disabled={form.processing}
                className="grid gap-4 sm:grid-cols-2"
            >
                {(
                    [
                        ['name', 'اسم المورد', 120, false],
                        ['tax_number', 'الرقم الضريبي (اختياري)', 40, true],
                        [
                            'commercial_registration',
                            'السجل التجاري (اختياري)',
                            40,
                            true,
                        ],
                        ['phone', 'رقم الاتصال (اختياري)', 40, true],
                        ['city', 'المدينة (اختياري)', 120, false],
                    ] as const
                ).map(([key, label, maxLength, ltr]) => (
                    <FormField
                        key={key}
                        label={label}
                        required={key === 'name'}
                        maxLength={maxLength}
                        dir={ltr ? 'ltr' : undefined}
                        value={form.data[key]}
                        onChange={(event) =>
                            form.setData(key, event.target.value)
                        }
                        error={form.errors[key]}
                    />
                ))}
                <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="supplier-notes">ملاحظات (اختياري)</Label>
                    <textarea
                        id="supplier-notes"
                        rows={3}
                        maxLength={1000}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                        aria-invalid={!!form.errors.notes}
                        aria-describedby={
                            form.errors.notes
                                ? 'supplier-notes-error'
                                : undefined
                        }
                        className="w-full rounded-md border bg-card px-3 py-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                    {form.errors.notes && (
                        <p
                            id="supplier-notes-error"
                            role="alert"
                            className="text-sm text-destructive"
                        >
                            {form.errors.notes}
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <input
                        id="supplier-active"
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(event) =>
                            form.setData('is_active', event.target.checked)
                        }
                    />
                    <Label htmlFor="supplier-active">مورد نشط</Label>
                </div>
                {form.errors.is_active && (
                    <p role="alert" className="text-sm text-destructive">
                        {form.errors.is_active}
                    </p>
                )}
                <Button type="submit">
                    {form.processing
                        ? 'جارٍ الحفظ…'
                        : supplier
                          ? 'حفظ بيانات المورد'
                          : 'إضافة المورد'}
                </Button>
            </fieldset>
        </form>
    );
}
