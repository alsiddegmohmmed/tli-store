import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import Money from '@/components/money';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { useRecordedSubmission } from '@/hooks/use-recorded-submission';
import { store } from '@/routes/shifts/cash-transfers';
import type { CashTransferInput, SharedProps } from '@/types/store';

export default function CashTransferForm({
    shiftId,
    closed,
}: {
    shiftId: number;
    closed: boolean;
}) {
    const { auth } = usePage<SharedProps>().props;
    const [input, setInput] = useState<CashTransferInput>({
        direction: 'in',
        amount: '',
        reference: '',
        reason: '',
    });
    const transfer = useRecordedSubmission<
        CashTransferInput,
        { id: number; url: string }
    >({
        storageKey: `tli:cash-transfer:${auth.user.id}:${shiftId}`,
        url: store.url(shiftId),
        validateInput: (value) =>
            (value.direction === 'in' || value.direction === 'out') &&
            typeof value.amount === 'string' &&
            typeof value.reference === 'string' &&
            typeof value.reason === 'string',
        onConfirmed: (result) => router.visit(result.url),
    });
    if (closed && !transfer.pending && !transfer.blocked) return null;
    return (
        <Card>
            <CardHeader>
                <CardTitle>تحويل نقد بين الصندوق والخزنة الرئيسية</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    سجّل النقد المنقول فعليًا مع مرجعه وسببه. هذا النموذج للتحويل
                    بين الصندوق والخزنة؛ لا تستخدمه لدفع مصروف أو مورد.
                </p>
                {transfer.error && (
                    <p role="alert" className="text-destructive">
                        {transfer.error}
                    </p>
                )}
                {transfer.pending && (
                    <div className="space-y-3">
                        <p role="status">
                            {transfer.saving
                                ? 'جارٍ حفظ التحويل…'
                                : 'نتيجة التحويل غير مؤكدة. استعد نفس المحاولة قبل تسجيل تحويل آخر.'}
                        </p>
                        <p>
                            {transfer.pending.input.direction === 'in'
                                ? 'من الخزنة إلى الصندوق'
                                : 'من الصندوق إلى الخزنة'}{' '}
                            — <Money amount={transfer.pending.input.amount} /> —{' '}
                            {transfer.pending.input.reference}
                        </p>
                        <p className="text-sm">
                            رقم المحاولة:{' '}
                            <bdi dir="ltr">{transfer.pending.operation_id}</bdi>
                        </p>
                        <Button
                            disabled={transfer.saving}
                            onClick={() => void transfer.retry()}
                        >
                            استعادة نفس محاولة التحويل
                        </Button>
                    </div>
                )}
                {!closed && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            void transfer.submit(input);
                        }}
                    >
                        <fieldset
                            disabled={
                                transfer.saving ||
                                !!transfer.pending ||
                                transfer.blocked
                            }
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="cash-transfer-direction">
                                    اتجاه التحويل
                                </Label>
                                <select
                                    id="cash-transfer-direction"
                                    required
                                    className="h-10 w-full rounded-md border bg-card px-3"
                                    value={input.direction}
                                    onChange={(event) =>
                                        setInput({
                                            ...input,
                                            direction: event.target
                                                .value as CashTransferInput['direction'],
                                        })
                                    }
                                >
                                    <option value="in">
                                        من الخزنة إلى الصندوق
                                    </option>
                                    <option value="out">
                                        من الصندوق إلى الخزنة
                                    </option>
                                </select>
                            </div>
                            <FormField
                                label="المبلغ المنقول"
                                type="number"
                                min="0.01"
                                step="0.01"
                                dir="ltr"
                                required
                                value={input.amount}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        amount: event.target.value,
                                    })
                                }
                            />
                            <FormField
                                label="مرجع التحويل"
                                required
                                maxLength={120}
                                value={input.reference}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        reference: event.target.value,
                                    })
                                }
                            />
                            <FormField
                                label="سبب التحويل"
                                required
                                maxLength={500}
                                value={input.reason}
                                onChange={(event) =>
                                    setInput({
                                        ...input,
                                        reason: event.target.value,
                                    })
                                }
                            />
                            <Button type="submit">
                                {input.direction === 'in'
                                    ? 'تأكيد النقد المنقول إلى الصندوق'
                                    : 'تأكيد النقد المنقول إلى الخزنة'}
                            </Button>
                        </fieldset>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
