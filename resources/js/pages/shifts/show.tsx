import { Head, Link, useForm } from '@inertiajs/react';
import DateTime from '@/components/date-time';
import FormField from '@/components/form-field';
import Money from '@/components/money';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SalesTable from '@/features/sales/sales-table';
import CashTransferForm from '@/features/shifts/cash-transfer-form';
import { close } from '@/routes/shifts';
import { show as transferShow } from '@/routes/shifts/cash-transfers';
import { index as posIndex } from '@/routes/pos';
import type {
    CashierShift,
    SaleSummary,
    ShiftTotals,
    ShiftCashTransfer,
} from '@/types/store';

export default function Shift({
    shift,
    summary,
    sales,
    canClose,
    canTransfer,
    transfers,
}: {
    shift: CashierShift;
    summary: ShiftTotals;
    sales: Paginated<SaleSummary>;
    canClose: boolean;
    canTransfer: boolean;
    transfers: Paginated<ShiftCashTransfer>;
}) {
    const form = useForm({ counted_cash: '', closing_note: '' });
    return (
        <>
            <Head title={`الوردية ${shift.id}`} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold">
                    الوردية {shift.id} — {shift.closed_at ? 'مغلقة' : 'مفتوحة'}
                </h1>
                {!shift.closed_at && canClose && (
                    <Button asChild>
                        <Link href={posIndex()}>متابعة البيع</Link>
                    </Button>
                )}
            </div>
            <p className="text-sm text-muted-foreground">
                {shift.cashier.name} — {shift.station.name} — بدأت{' '}
                <DateTime value={shift.opened_at} />
            </p>
            <p className="rounded-md border bg-card p-3 text-sm">
                المطابقة: رصيد البداية + المبيعات النقدية بعد خصم باقي العميل +
                النقد من الخزنة − النقد إلى الخزنة. البطاقة منفصلة. لا تشمل
                مرتجعات أو دفع مصروفات؛ هذه العمليات لم تُنفّذ بعد.
            </p>
            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {shift.closed_at
                                ? 'المطابقة المحفوظة'
                                : 'ملخص حتى الآن'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-3">
                            <div className="flex justify-between gap-3">
                                <dt>عدد المبيعات</dt>
                                <dd>{summary.sale_count}</dd>
                            </div>
                            {(
                                [
                                    ['رصيد البداية', shift.opening_cash],
                                    ['إجمالي المبيعات', summary.sales_total],
                                    ['مبيعات نقدية', summary.cash_sales],
                                    ['مبيعات بطاقة', summary.card_sales],
                                    ['من الخزنة إلى الصندوق', summary.cash_in],
                                    ['من الصندوق إلى الخزنة', summary.cash_out],
                                    ['النقد المتوقع', summary.expected_cash],
                                ] as const
                            ).map(([label, amount]) => (
                                <div
                                    key={label}
                                    className="flex justify-between gap-3"
                                >
                                    <dt>{label}</dt>
                                    <dd>
                                        <Money amount={amount} />
                                    </dd>
                                </div>
                            ))}
                            {shift.closed_at && (
                                <>
                                    <div className="flex justify-between gap-3">
                                        <dt>النقد المعدود</dt>
                                        <dd>
                                            <Money
                                                amount={shift.counted_cash!}
                                            />
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-3 border-t pt-3 font-bold">
                                        <dt>
                                            {shift.difference === '0.00'
                                                ? 'النقد مطابق'
                                                : shift.difference?.startsWith(
                                                        '-',
                                                    )
                                                  ? 'عجز نقدي'
                                                  : 'زيادة نقدية'}
                                        </dt>
                                        <dd>
                                            <Money amount={shift.difference!} />
                                        </dd>
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        أُغلقت{' '}
                                        <DateTime value={shift.closed_at} />
                                    </div>
                                    {shift.closing_note && (
                                        <p className="whitespace-pre-wrap">
                                            {shift.closing_note}
                                        </p>
                                    )}
                                </>
                            )}
                        </dl>
                    </CardContent>
                </Card>
                {!shift.closed_at && canClose && (
                    <Card>
                        <CardHeader>
                            <CardTitle>عدّ النقد وإغلاق الوردية</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.post(close.url(shift.id), {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <fieldset
                                    disabled={form.processing}
                                    className="space-y-4"
                                >
                                    <p className="text-sm">
                                        عدّ النقد الموجود فعلًا. يحفظ الإغلاق نتيجة
                                        المطابقة ويوقف المبيعات الجديدة على هذه
                                        الوردية. لا ينشئ تحويلًا أو قيد تسوية
                                        للفارق.
                                    </p>
                                    <FormField
                                        label="النقد الموجود فعليًا"
                                        required
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        dir="ltr"
                                        value={form.data.counted_cash}
                                        onChange={(event) =>
                                            form.setData(
                                                'counted_cash',
                                                event.target.value,
                                            )
                                        }
                                        error={form.errors.counted_cash}
                                    />
                                    <FormField
                                        label="ملاحظة الإغلاق (اختياري)"
                                        maxLength={500}
                                        value={form.data.closing_note}
                                        onChange={(event) =>
                                            form.setData(
                                                'closing_note',
                                                event.target.value,
                                            )
                                        }
                                        error={form.errors.closing_note}
                                    />
                                    <Button type="submit">
                                        {form.processing
                                            ? 'جارٍ الإغلاق…'
                                            : 'حفظ العد وإغلاق الوردية'}
                                    </Button>
                                </fieldset>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
            {canTransfer && (
                <CashTransferForm
                    key={shift.id}
                    shiftId={shift.id}
                    closed={!!shift.closed_at}
                />
            )}
            <Card>
                <CardHeader>
                    <CardTitle>تحويلات النقد للوردية</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    {transfers.data.length === 0 ? (
                        <p className="text-muted-foreground">
                            لا توجد تحويلات نقدية لهذه الوردية.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-start text-sm">
                                <thead>
                                    <tr className="border-b">
                                        {[
                                            'التحويل',
                                            'الاتجاه',
                                            'المبلغ',
                                            'المرجع',
                                            'سجّل بواسطة',
                                            'الوقت',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                className="p-3 text-start"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {transfers.data.map((transfer) => (
                                        <tr
                                            key={transfer.id}
                                            className="border-b"
                                        >
                                            <td className="p-3">
                                                <Link
                                                    href={transferShow([
                                                        shift.id,
                                                        transfer.id,
                                                    ])}
                                                    className="underline"
                                                >
                                                    فتح التحويل {transfer.id}
                                                </Link>
                                            </td>
                                            <td className="p-3">
                                                {transfer.direction === 'in'
                                                    ? 'من الخزنة إلى الصندوق'
                                                    : 'من الصندوق إلى الخزنة'}
                                            </td>
                                            <td className="p-3">
                                                <Money
                                                    amount={transfer.amount}
                                                />
                                            </td>
                                            <td className="p-3">
                                                {transfer.reference}
                                            </td>
                                            <td className="p-3">
                                                {transfer.author.name}
                                            </td>
                                            <td className="p-3">
                                                <DateTime
                                                    value={transfer.recorded_at}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination page={transfers} label="صفحات تحويلات النقد" />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>مبيعات الوردية</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <SalesTable sales={sales.data} />
                    <Pagination page={sales} label="صفحات مبيعات الوردية" />
                </CardContent>
            </Card>
        </>
    );
}
