import { Link } from '@inertiajs/react';
import Money from '@/components/money';
import DateTime from '@/components/date-time';
import { show } from '@/routes/sales';
import type { SaleSummary } from '@/types/store';
export default function SalesTable({ sales }: { sales: SaleSummary[] }) {
    return (
        <>
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
                                <th className="p-3 text-start" key={label}>
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {sales.map((sale) => (
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
                                    <DateTime value={sale.recorded_at} />
                                </td>
                                <td className="p-3">{sale.cashier.name}</td>
                                <td className="p-3">{sale.station.name}</td>
                                <td className="p-3">
                                    <Money amount={sale.total} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {sales.length === 0 && (
                <p className="text-muted-foreground">
                    لا توجد مبيعات مسجلة بعد.
                </p>
            )}
        </>
    );
}
