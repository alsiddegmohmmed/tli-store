import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, openings } from '@/routes/stock';

type Row = {
    id: number;
    name: string;
    barcode: string;
    unit: string;
    quantity: string | null;
    reference: string | null;
    opening_date: string | null;
};
export default function Stock({
    products,
    search,
    today,
}: {
    products: Paginated<Row>;
    search: string;
    today: string;
}) {
    const [query, setQuery] = useState(search);
    const [selected, setSelected] = useState<Row | null>(null);
    const form = useForm({
        product_id: 0,
        quantity: '',
        opening_date: today,
        reference: '',
    });
    function select(row: Row) {
        setSelected(row);
        form.clearErrors();
        form.setData({
            product_id: row.id,
            quantity: '',
            opening_date: today,
            reference: '',
        });
    }
    return (
        <>
            <Head title="المخزون" />
            <h1 className="text-2xl font-bold">المخزون</h1>
            <p className="text-sm text-muted-foreground">
                سجل الكمية من محضر جرد افتتاحي موثق، مرة واحدة لكل صنف. تقييم
                المخزون والتكلفة والربح لم تُفعّل بعد. توريد المشتريات والتسويات
                ستضاف في مراحل لاحقة.
            </p>
            {selected && (
                <Card>
                    <CardHeader>
                        <CardTitle>الرصيد الافتتاحي: {selected.name}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-4 sm:grid-cols-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(openings.url(), {
                                    onSuccess: () => {
                                        setSelected(null);
                                        form.reset();
                                    },
                                });
                            }}
                        >
                            <FormField
                                label="الكمية الافتتاحية"
                                type="number"
                                dir="ltr"
                                min="0.001"
                                step="0.001"
                                required
                                value={form.data.quantity}
                                error={form.errors.quantity}
                                onChange={(e) =>
                                    form.setData('quantity', e.target.value)
                                }
                            />
                            <FormField
                                label="تاريخ الجرد"
                                type="date"
                                dir="ltr"
                                max={today}
                                required
                                value={form.data.opening_date}
                                error={form.errors.opening_date}
                                onChange={(e) =>
                                    form.setData('opening_date', e.target.value)
                                }
                            />
                            <FormField
                                label="مرجع محضر الجرد"
                                maxLength={120}
                                required
                                value={form.data.reference}
                                error={form.errors.reference}
                                onChange={(e) =>
                                    form.setData('reference', e.target.value)
                                }
                            />
                            {form.errors.product_id && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {form.errors.product_id}
                                </p>
                            )}
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing
                                        ? 'جارٍ الحفظ…'
                                        : 'تأكيد الرصيد الافتتاحي'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={form.processing}
                                    onClick={() => setSelected(null)}
                                >
                                    إلغاء
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <form
                        className="flex items-end gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(
                                index.url(),
                                { search: query },
                                { preserveState: true },
                            );
                        }}
                    >
                        <div className="max-w-md flex-1">
                            <FormField
                                label="بحث بالاسم أو الباركود"
                                maxLength={60}
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                        </div>
                        <Button variant="outline" type="submit">
                            بحث
                        </Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="w-full text-start text-sm">
                            <thead>
                                <tr className="border-b">
                                    {[
                                        'الصنف',
                                        'الباركود',
                                        'الوحدة',
                                        'الكمية المتاحة',
                                        'مرجع الافتتاح',
                                        '',
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
                                {products.data.map((row) => (
                                    <tr key={row.id} className="border-b">
                                        <td className="p-3">{row.name}</td>
                                        <td className="p-3">
                                            <bdi dir="ltr">{row.barcode}</bdi>
                                        </td>
                                        <td className="p-3">{row.unit}</td>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {row.quantity ?? 'لم يُسجل'}
                                            </bdi>
                                        </td>
                                        <td className="p-3">
                                            {row.reference ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            {!row.reference && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() => select(row)}
                                                >
                                                    تسجيل الرصيد
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {products.data.length === 0 && (
                        <p className="text-muted-foreground">
                            لا توجد أصناف مطابقة.
                        </p>
                    )}
                    <Pagination page={products} label="صفحات المخزون" />
                </CardContent>
            </Card>
        </>
    );
}
