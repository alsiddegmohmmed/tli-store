import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import Money from '@/components/money';
import RecordedSubmissionFeedback from '@/components/recorded-submission-feedback';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useRecordedSubmission } from '@/hooks/use-recorded-submission';
import { store } from '@/routes/purchases/payments';
import type { SharedProps } from '@/types/store';
import type { SupplierPaymentInput, BillBalance } from './types';

export default function SupplierPaymentForm({
    billId,
    billDate,
    today,
    balance,
}: {
    billId: number;
    billDate: string;
    today: string;
    balance: BillBalance;
}) {
    const { auth } = usePage<SharedProps>().props;
    const recording = useRecordedSubmission<
        SupplierPaymentInput,
        { url: string }
    >({
        storageKey: `tli:supplier-payment:${auth.user.id}:${billId}`,
        url: store.url(billId),
        validateInput: (input) =>
            [
                'payment_date',
                'amount',
                'reference',
                'funding_reference',
                'actual_payer',
            ].every(
                (key) =>
                    typeof input[key as keyof SupplierPaymentInput] ===
                    'string',
            ) &&
            (input.method === 'bank' || input.method === 'cash_reserve') &&
            typeof input.confirmed_paid_from_company === 'boolean',
        onConfirmed: (result) => router.visit(result.url),
    });
    const [input, setInput] = useState<SupplierPaymentInput>({
        payment_date: today,
        amount: '',
        method: 'bank',
        reference: '',
        funding_reference: '',
        actual_payer: '',
        confirmed_paid_from_company: false,
    });
    const data = recording.pending?.input ?? input;
    const locked =
        recording.saving ||
        !!recording.pending ||
        recording.blocked ||
        balance.remaining === '0.00';
    return (
        <div className="space-y-4">
            <RecordedSubmissionFeedback
                error={recording.error}
                pending={!!recording.pending}
                saving={recording.saving}
                retry={() => void recording.retry()}
            >
                {recording.pending && (
                    <p>
                        مرجع الدفع:{' '}
                        <bdi dir="ltr">{recording.pending.input.reference}</bdi>{' '}
                        — <Money amount={recording.pending.input.amount} />
                    </p>
                )}
            </RecordedSubmissionFeedback>
            <Card>
                <CardHeader>
                    <CardTitle>تسجيل سداد للمورد</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex flex-wrap items-center gap-4">
                        <p>
                            مدفوع: <Money amount={balance.paid} />
                        </p>
                        <p>
                            متبقي: <Money amount={balance.remaining} />
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={recording.saving}
                            onClick={() =>
                                router.reload({
                                    only: ['balance', 'payments'],
                                })
                            }
                        >
                            تحديث الدفعات والمتبقي
                        </Button>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        هذا تسجيل لدفع تم فعليًا، ولا ينفذ تحويلًا بنكيًا. اختر أموال
                        الشركة فقط. أموال الموظف أو المالك الشخصية والعهد وصندوق
                        الكاشير تحتاج مسارًا منفصلًا. الأرصدة الافتتاحية للخزنة
                        والبنوك غير مؤكدة؛ المتبقي يخص هذه الفاتورة.
                    </p>
                    {balance.remaining === '0.00' && (
                        <p role="status">
                            سُدد كامل المبلغ المسجل على الفاتورة.
                        </p>
                    )}
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
                                label="تاريخ السداد"
                                type="date"
                                required
                                min={billDate}
                                max={today}
                                value={data.payment_date}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        payment_date: event.target.value,
                                    })
                                }
                            />
                            <FormField
                                label="مبلغ السداد (ريال)"
                                inputMode="decimal"
                                dir="ltr"
                                required
                                value={data.amount}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        amount: event.target.value,
                                    })
                                }
                            />
                            <div className="space-y-2">
                                <Label htmlFor="supplier-payment-method">
                                    طريقة السداد من أموال الشركة
                                </Label>
                                <select
                                    id="supplier-payment-method"
                                    className="h-10 w-full rounded-md border bg-card px-3"
                                    value={data.method}
                                    onChange={(event) =>
                                        setInput({
                                            ...input,
                                            method: event.target
                                                .value as SupplierPaymentInput['method'],
                                        })
                                    }
                                >
                                    <option value="bank">
                                        حساب بنكي للشركة
                                    </option>
                                    <option value="cash_reserve">
                                        الخزنة الرئيسية للشركة
                                    </option>
                                </select>
                            </div>
                            <FormField
                                label="مرجع إثبات الدفع"
                                required
                                dir="ltr"
                                maxLength={120}
                                value={data.reference}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        reference: event.target.value,
                                    })
                                }
                            />
                            <FormField
                                label="مرجع مصدر أموال الشركة"
                                required
                                maxLength={120}
                                value={data.funding_reference}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        funding_reference: event.target.value,
                                    })
                                }
                            />
                            <FormField
                                label="من دفع فعليًا"
                                required
                                maxLength={120}
                                value={data.actual_payer}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        actual_payer: event.target.value,
                                    })
                                }
                            />
                            <div className="flex items-start gap-2 sm:col-span-2">
                                <input
                                    id="company-payment-confirmation"
                                    type="checkbox"
                                    required
                                    checked={data.confirmed_paid_from_company}
                                    onChange={(event) =>
                                        setInput({
                                            ...input,
                                            confirmed_paid_from_company:
                                                event.target.checked,
                                        })
                                    }
                                />
                                <Label htmlFor="company-payment-confirmation">
                                    تحققت أن الدفع تم فعليًا من أموال الشركة، ولم
                                    يُسجل سابقًا على هذه الفاتورة.
                                </Label>
                            </div>
                            <Button type="submit">
                                {recording.saving
                                    ? 'جارٍ الحفظ…'
                                    : 'تسجيل السداد'}
                            </Button>
                        </fieldset>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
