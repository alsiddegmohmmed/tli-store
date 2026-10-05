import { Head, useForm } from '@inertiajs/react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { update } from '@/routes/company';
import type { CompanyProfile } from '@/types/store';

const fields = [
    ['legal_name', 'الاسم القانوني للشركة', 120, false],
    ['vat_number', 'الرقم الضريبي', 15, true],
    ['commercial_registration', 'رقم السجل التجاري', 20, true],
    ['street', 'الشارع', 120, false],
    ['building_number', 'رقم المبنى', 4, true],
    ['district', 'الحي', 120, false],
    ['city', 'المدينة', 120, false],
    ['postal_code', 'الرمز البريدي', 5, true],
] as const;

export default function Company({
    company,
}: {
    company: CompanyProfile | null;
}) {
    const form = useForm<CompanyProfile>(
        company ?? {
            legal_name: '',
            vat_number: '',
            commercial_registration: '',
            street: '',
            building_number: '',
            district: '',
            city: '',
            postal_code: '',
        },
    );
    return (
        <>
            <Head title="بيانات الشركة" />
            <h1 className="text-2xl font-bold">بيانات الشركة</h1>
            <p className="text-sm text-muted-foreground">
                انقل البيانات من شهادة التسجيل الضريبي والسجل التجاري والعنوان
                الوطني. المملكة العربية السعودية والريال السعودي هما نطاق هذا
                المتجر.
            </p>
            <p className="rounded-md border bg-card p-3 text-sm">
                حفظ البيانات لا يفعّل الفوترة الرسمية. يحتفظ كل بيع جديد بنسخة
                بيانات الشركة وقت حفظه؛ السجلات السابقة لن تتغير.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>هوية البائع والعنوان الوطني</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(update.url());
                        }}
                        className="space-y-4"
                    >
                        <fieldset
                            disabled={form.processing}
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            {fields.map(([key, label, maxLength, ltr]) => (
                                <FormField
                                    key={key}
                                    label={label}
                                    required
                                    maxLength={maxLength}
                                    dir={ltr ? 'ltr' : undefined}
                                    value={form.data[key]}
                                    onChange={(event) =>
                                        form.setData(key, event.target.value)
                                    }
                                    error={form.errors[key]}
                                />
                            ))}
                        </fieldset>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'جارٍ الحفظ…'
                                : 'حفظ بيانات الشركة'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
