import { Head, Link } from '@inertiajs/react';
import DateTime from '@/components/date-time';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as stockIndex } from '@/routes/stock';
import { show } from '@/routes/stock/counts';
import type { StockCount } from '@/types/store';

export default function Counts({ counts }: { counts: Paginated<StockCount> }) {
    return (
        <>
            <Head title="تسويات الجرد" />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold">تسويات الجرد</h1>
                <Button asChild variant="outline">
                    <Link href={stockIndex()}>العودة للمخزون</Link>
                </Button>
            </div>
            <p className="text-sm text-muted-foreground">
                محاضر محفوظة لتصحيح الكمية. لا تتضمن تقييم المخزون أو قيدًا ماليًا؛
                المشتريات لها مسار منفصل.
            </p>
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <div className="overflow-x-auto">
                        <table className="w-full text-start text-sm">
                            <thead>
                                <tr className="border-b">
                                    {[
                                        'المحضر',
                                        'الصنف',
                                        'الباركود',
                                        'المعدود',
                                        'الفرق',
                                        'التاريخ',
                                        'المسؤول',
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
                                {counts.data.map((count) => (
                                    <tr key={count.id} className="border-b">
                                        <td className="p-3">
                                            <Link
                                                className="underline"
                                                href={show(count.id)}
                                            >
                                                {count.reference}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            {count.product_snapshot.name}
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {count.product_snapshot.barcode}
                                            </bdi>
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {count.counted_quantity}
                                            </bdi>{' '}
                                            {count.product_snapshot.unit}
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {count.difference}
                                            </bdi>
                                        </td>
                                        <td className="p-3">
                                            <DateTime
                                                value={count.recorded_at}
                                            />
                                        </td>
                                        <td className="p-3">
                                            {count.author.name}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {counts.data.length === 0 && (
                        <p className="text-muted-foreground">
                            لا توجد تسويات جرد مسجلة بعد.
                        </p>
                    )}
                    <Pagination page={counts} label="صفحات تسويات الجرد" />
                </CardContent>
            </Card>
        </>
    );
}
