import { Head, Link } from '@inertiajs/react';
import DateTime from '@/components/date-time';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as stockIndex } from '@/routes/stock';
import { index as countsIndex } from '@/routes/stock/counts';
import type { StockCount } from '@/types/store';

export default function Count({ count }: { count: StockCount }) {
    const product = count.product_snapshot;
    return (
        <>
            <Head title={`محضر جرد ${count.id}`} />
            <h1 className="text-2xl font-bold">محضر جرد {count.id}</h1>
            <p role="status" className="rounded-md border bg-card p-3">
                هذا المحضر محفوظ.{' '}
                {count.difference === '0.000'
                    ? 'العد مطابق؛ لم تُنشأ حركة كمية.'
                    : 'حُفظت حركة الكمية بالفرق فقط.'}{' '}
                لا يوجد قيد مالي أو حساب تكلفة لهذه التسوية.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>{product.name}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p>
                        الباركود: <bdi dir="ltr">{product.barcode}</bdi> —
                        الوحدة: {product.unit}
                    </p>
                    <dl className="space-y-3">
                        {(
                            [
                                [
                                    'الرصيد المسجل قبل الجرد',
                                    count.recorded_quantity,
                                ],
                                ['الكمية المعدودة', count.counted_quantity],
                                [
                                    count.difference === '0.000'
                                        ? 'الفرق — مطابق'
                                        : count.difference.startsWith('-')
                                          ? 'الفرق — نقص'
                                          : 'الفرق — زيادة',
                                    count.difference,
                                ],
                            ] as const
                        ).map(([label, quantity]) => (
                            <div
                                key={label}
                                className="flex justify-between gap-3"
                            >
                                <dt>{label}</dt>
                                <dd>
                                    <bdi dir="ltr">{quantity}</bdi>{' '}
                                    {product.unit}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <p>مرجع المحضر: {count.reference}</p>
                    <p className="whitespace-pre-wrap">السبب: {count.reason}</p>
                    <p className="text-sm text-muted-foreground">
                        سجله {count.author.name} —{' '}
                        <DateTime value={count.recorded_at} />
                    </p>
                    <p className="text-sm text-muted-foreground">
                        هذه لقطة من وقت الجرد. الرصيد الحالي قد يتغير مع
                        المبيعات والتسويات التالية. التصحيح الجديد يحتاج محضرًا
                        جديدًا؛ المحضر المحفوظ لا يُعدّل.
                    </p>
                </CardContent>
            </Card>
            <div className="flex flex-wrap gap-3">
                <Button asChild>
                    <Link href={stockIndex()}>فتح المخزون</Link>
                </Button>
                <Button asChild variant="outline">
                    <Link href={countsIndex()}>سجل التسويات</Link>
                </Button>
            </div>
        </>
    );
}
