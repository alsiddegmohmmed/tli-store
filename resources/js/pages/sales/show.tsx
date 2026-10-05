import { Head, Link } from '@inertiajs/react';
import Money from '@/components/money';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/sales';
import type { SaleSummary } from '@/types/store';
import DateTime from '@/components/date-time';

type Sale = SaleSummary & {
    net: string;
    tax: string;
    cash_tender: string;
    change: string;
    fiscal_status: string;
    lines: {
        id: number;
        name: string;
        barcode: string;
        unit: string;
        quantity: string;
        unit_price: string;
        total: string;
    }[];
    payments: {
        id: number;
        method: string;
        amount: string;
        reference: string | null;
        manually_confirmed: boolean;
    }[];
};
export default function SaleDetail({
    sale,
    journal,
}: {
    sale: Sale;
    journal: { name: string; debit: string; credit: string }[];
}) {
    return (
        <>
            <Head title={`بيع ${sale.id}`} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold">سجل البيع {sale.id}</h1>
                <Link href={index()} className="underline">
                    العودة للمبيعات
                </Link>
            </div>
            <p className="rounded-md border bg-card p-3 text-sm">
                هذا سجل داخلي؛ لم تصدر فاتورة رسمية ولم تُنفذ طباعة. التكلفة
                والربح غير محسوبين.
            </p>
            <p className="text-sm text-muted-foreground">
                <DateTime value={sale.recorded_at} /> — {sale.cashier.name} —{' '}
                {sale.station.name}
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>الأصناف المسجلة</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full text-start text-sm">
                            <thead>
                                <tr className="border-b">
                                    {[
                                        'الصنف',
                                        'الباركود',
                                        'الكمية',
                                        'السعر',
                                        'الإجمالي',
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
                                {sale.lines.map((line) => (
                                    <tr key={line.id} className="border-b">
                                        <td className="p-3">
                                            {line.name} — {line.unit}
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">{line.barcode}</bdi>
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">{line.quantity}</bdi>
                                        </td>
                                        <td className="p-3">
                                            <Money amount={line.unit_price} />
                                        </td>
                                        <td className="p-3">
                                            <Money amount={line.total} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>المبالغ</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-3">
                            {(
                                [
                                    ['net', 'قبل الضريبة'],
                                    ['tax', 'الضريبة'],
                                    ['total', 'الإجمالي'],
                                    ['cash_tender', 'النقد المستلم'],
                                    ['change', 'الباقي للعميل'],
                                ] as const
                            ).map(([key, label]) => (
                                <div key={key} className="flex justify-between">
                                    <dt>{label}</dt>
                                    <dd>
                                        <Money amount={sale[key]} />
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>المدفوعات</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {sale.payments.map((payment) => (
                            <div key={payment.id}>
                                <div className="flex justify-between">
                                    <span>
                                        {payment.method === 'cash'
                                            ? 'نقدي'
                                            : 'بطاقة — تأكيد يدوي'}
                                    </span>
                                    <Money amount={payment.amount} />
                                </div>
                                {payment.reference && (
                                    <p className="text-sm text-muted-foreground">
                                        مرجع:{' '}
                                        <bdi dir="ltr">{payment.reference}</bdi>
                                    </p>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
            {journal.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>القيد المالي</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-start text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-3 text-start">
                                            الحساب
                                        </th>
                                        <th className="p-3 text-start">مدين</th>
                                        <th className="p-3 text-start">دائن</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {journal.map((line, i) => (
                                        <tr key={i} className="border-b">
                                            <td className="p-3">{line.name}</td>
                                            <td className="p-3">
                                                <Money amount={line.debit} />
                                            </td>
                                            <td className="p-3">
                                                <Money amount={line.credit} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}
        </>
    );
}
