import { Head, Link } from '@inertiajs/react';
import Money from '@/components/money';
import DateTime from '@/components/date-time';
import type { SaleSummary } from '@/types/store';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Card, CardContent } from '@/components/ui/card';
import { show } from '@/routes/sales';

export default function Sales({ sales }: { sales: Paginated<SaleSummary> }) {
    return (
        <>
            <Head title="المبيعات" />
            <h1 className="text-2xl font-bold">المبيعات</h1>
            <p className="text-sm text-muted-foreground">
                سجلات بيع داخلية. لم تصدر فواتير رسمية لهذه العمليات.
            </p>
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <div className="overflow-x-auto">
                        <table className="w-full text-start text-sm">
                            <thead>
                                <tr className="border-b">
                                    {[
                                        'البيع',
                                        'التاريخ',
                                        'الكاشير',
                                        'الصندوق',
                                        'الإجمالي',
                                    ].map((label) => (
                                        <th
                                            className="p-3 text-start"
                                            key={label}
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {sales.data.map((sale) => (
                                    <tr key={sale.id} className="border-b">
                                        <td className="p-3">
                                            <Link
                                                href={show(sale.id)}
                                                className="font-medium underline"
                                            >
                                                فتح البيع {sale.id}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <DateTime
                                                value={sale.recorded_at}
                                            />
                                        </td>
                                        <td className="p-3">
                                            {sale.cashier.name}
                                        </td>
                                        <td className="p-3">
                                            {sale.station.name}
                                        </td>
                                        <td className="p-3">
                                            <Money amount={sale.total} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {sales.data.length === 0 && (
                        <p className="text-muted-foreground">
                            لا توجد مبيعات مسجلة بعد.
                        </p>
                    )}
                    <Pagination page={sales} label="صفحات المبيعات" />
                </CardContent>
            </Card>
        </>
    );
}
