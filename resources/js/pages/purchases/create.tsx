import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type { PurchaseBillInput } from '@/features/purchases/types';
import { useRecordedSubmission } from '@/hooks/use-recorded-submission';
import { index, store } from '@/routes/purchases';
import type { SharedProps, Supplier } from '@/types/store';

export default function CreatePurchaseBill({
    supplier,
    today,
}: {
    supplier: Supplier;
    today: string;
}) {
    const { auth } = usePage<SharedProps>().props;
    const recording = useRecordedSubmission<PurchaseBillInput, { url: string }>(
        {
            storageKey: `tli:purchase-bill:${auth.user.id}:${supplier.id}`,
            url: store.url(),
            validateInput: (input) =>
                input.supplier_id === supplier.id &&
                ['reference', 'bill_date', 'description', 'total'].every(
                    (key) =>
                        typeof input[key as keyof PurchaseBillInput] ===
                        'string',
                ) &&
                (input.printed_tax === null ||
                    typeof input.printed_tax === 'string') &&
                typeof input.confirmed_unpaid === 'boolean',
            onConfirmed: (result) => router.visit(result.url),
        },
    );
    const [input, setInput] = useState<PurchaseBillInput>({
        supplier_id: supplier.id,
        reference: '',
        bill_date: today,
        description: '',
        total: '',
        printed_tax: null,
        confirmed_unpaid: false,
    });
    const data = recording.pending?.input ?? input;
    const locked =
        recording.saving ||
        !!recording.pending ||
        recording.blocked ||
        !supplier.is_active;
    return (
        <>
            <Head title="فاتورة مورد جديدة" />
            <h1 className="text-2xl font-bold">
                فاتورة مورد جديدة — {supplier.name}
            </h1>
            <Link
                href={index({ query: { supplier_id: supplier.id } })}
                className="underline"
            >
                فواتير هذا المورد
            </Link>
            <p className="text-sm text-muted-foreground">
                سجل فاتورة غير مدفوعة من مستند المورد. الاستلام المخزني والدفع
                منفصلان ولم يُفعّلا بعد. المبلغ يبقى بانتظار التصنيف المحاسبي؛ لا
                تُحسب تكلفة المخزون أو ضريبة قابلة للاسترداد.
            </p>
            {!supplier.is_active && (
                <p role="alert" className="text-destructive">
                    المورد غير نشط. يمكن استعادة محاولة محفوظة، لكن لا يمكن إضافة
                    فاتورة جديدة.
                </p>
            )}
            {recording.error && (
                <p role="alert" className="text-destructive">
                    {recording.error}
                </p>
            )}
            {recording.pending && (
                <Card>
                    <CardContent className="space-y-3 pt-6">
                        <p role="status">
                            {recording.saving
                                ? 'جارٍ حفظ الفاتورة…'
                                : 'نتيجة الحفظ تحتاج استعادة نفس المحاولة. لا تنشئ فاتورة ثانية.'}
                        </p>
                        <p>
                            مرجع المستند:{' '}
                            <bdi dir="ltr">
                                {recording.pending.input.reference}
                            </bdi>
                        </p>
                        <Button
                            disabled={recording.saving}
                            onClick={() => void recording.retry()}
                        >
                            استعادة نفس المحاولة
                        </Button>
                    </CardContent>
                </Card>
            )}
            <Card>
                <CardHeader>
                    <CardTitle>بيانات مستند المورد</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            void recording.submit(input);
                        }}
                    >
                        <fieldset
                            disabled={locked}
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            <FormField
                                label="رقم مستند المورد"
                                required
                                maxLength={120}
                                dir="ltr"
                                value={data.reference}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        reference: event.target.value,
                                    })
                                }
                            />
                            <FormField
                                label="تاريخ الفاتورة"
                                required
                                type="date"
                                max={today}
                                value={data.bill_date}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        bill_date: event.target.value,
                                    })
                                }
                            />
                            <FormField
                                label="البيان"
                                required
                                maxLength={500}
                                value={data.description}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        description: event.target.value,
                                    })
                                }
                            />
                            <FormField
                                label="إجمالي المستند شامل الضريبة (ريال)"
                                required
                                inputMode="decimal"
                                dir="ltr"
                                value={data.total}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        total: event.target.value,
                                    })
                                }
                            />
                            <FormField
                                label="الضريبة كما في المستند (اختياري)"
                                inputMode="decimal"
                                dir="ltr"
                                value={data.printed_tax ?? ''}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        printed_tax: event.target.value || null,
                                    })
                                }
                            />
                            <p className="text-sm text-muted-foreground">
                                اترك الضريبة فارغة إن لم تتأكد منها. أدخل صفرًا
                                فقط إذا تحققت أن ضريبة المستند صفر.
                            </p>
                            <div className="flex items-start gap-2 sm:col-span-2">
                                <input
                                    id="bill-confirmation"
                                    type="checkbox"
                                    required
                                    checked={data.confirmed_unpaid}
                                    onChange={(event) =>
                                        setInput({
                                            ...input,
                                            confirmed_unpaid:
                                                event.target.checked,
                                        })
                                    }
                                />
                                <Label htmlFor="bill-confirmation">
                                    تحققت أنه لم يُدفع أي مبلغ من الفاتورة ولم
                                    تُسجل سابقًا في هذا النظام أو الأرصدة
                                    الافتتاحية.
                                </Label>
                            </div>
                            <Button type="submit">
                                {recording.saving
                                    ? 'جارٍ الحفظ…'
                                    : 'تسجيل فاتورة المورد'}
                            </Button>
                        </fieldset>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
