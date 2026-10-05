import {
    index as countsIndex,
    store as countStore,
} from '@/routes/stock/counts';
import CountForm from '@/features/stock/count-form';
import type { CountProduct } from '@/features/stock/count-form';
import { useRecordedSubmission } from '@/hooks/use-recorded-submission';
import type { SharedProps, StockCountInput } from '@/types/store';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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
    revision: number | null;
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
    const { auth } = usePage<SharedProps>().props;
    const [countProduct, setCountProduct] = useState<CountProduct | null>(null);
    const counting = useRecordedSubmission<StockCountInput, { url: string }>({
        storageKey: `tli:stock-count:${auth.user.id}`,
        url: countStore.url(),
        validateInput: (input) =>
            Number.isInteger(input.product_id) &&
            Number.isInteger(input.expected_revision) &&
            typeof input.counted_quantity === 'string',
        onConfirmed: (result) => router.visit(result.url),
    });
    const countLocked =
        counting.saving || !!counting.pending || counting.blocked;
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
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold">المخزون</h1>
                <Button asChild variant="outline">
                    <Link href={countsIndex()}>سجل تسويات الجرد</Link>
                </Button>
            </div>
            <p className="text-sm text-muted-foreground">
                سجل الكمية من محضر جرد افتتاحي موثق، مرة واحدة لكل صنف. تقييم
                المخزون والتكلفة والربح لم تُفعّل بعد. تسوية الجرد تصحح الكمية
                فقط؛ توريد المشتريات سيضاف لاحقًا.
            </p>
            {counting.error && (
                <p
                    role="alert"
                    className="rounded-md border border-destructive p-3 text-destructive"
                >
                    {counting.error}
                </p>
            )}
            {counting.pending && (
                <div className="space-y-3 rounded-md border bg-card p-3">
                    <p role="status">
                        {counting.saving
                            ? 'جارٍ حفظ الجرد…'
                            : 'المحاولة محفوظة. استعد نفس النتيجة قبل تسجيل جرد آخر.'}
                    </p>
                    <p className="text-sm">
                        الصنف رقم {counting.pending.input.product_id} — مرجع{' '}
                        {counting.pending.input.reference}
                    </p>
                    <p className="text-sm">
                        رقم المحاولة:{' '}
                        <bdi dir="ltr">{counting.pending.operation_id}</bdi>
                    </p>
                    <Button
                        disabled={counting.saving}
                        onClick={() => void counting.retry()}
                    >
                        استعادة نفس محاولة الجرد
                    </Button>
                </div>
            )}
            {countProduct && (
                <CountForm
                    key={countProduct.id}
                    product={countProduct}
                    disabled={countLocked}
                    onSubmit={counting.submit}
                    onCancel={() => setCountProduct(null)}
                    onRefresh={() =>
                        router.reload({
                            only: ['products'],
                            onSuccess: (page) => {
                                const current = (
                                    page.props.products as Paginated<Row>
                                ).data.find(
                                    (row) => row.id === countProduct.id,
                                );
                                if (
                                    current?.quantity != null &&
                                    current.revision != null
                                )
                                    setCountProduct({
                                        ...current,
                                        quantity: current.quantity,
                                        revision: current.revision,
                                    });
                            },
                        })
                    }
                />
            )}
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
                                    disabled={form.processing || countLocked}
                                >
                                    {form.processing
                                        ? 'جارٍ الحفظ…'
                                        : 'تأكيد الرصيد الافتتاحي'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={form.processing || countLocked}
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
                                                    disabled={countLocked}
                                                    onClick={() => {
                                                        setCountProduct(null);
                                                        select(row);
                                                    }}
                                                >
                                                    تسجيل الرصيد
                                                </Button>
                                            )}
                                            {row.quantity != null &&
                                                row.revision != null && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        disabled={
                                                            countLocked ||
                                                            form.processing
                                                        }
                                                        onClick={() => {
                                                            setSelected(null);
                                                            setCountProduct({
                                                                ...row,
                                                                quantity:
                                                                    row.quantity!,
                                                                revision:
                                                                    row.revision!,
                                                            });
                                                        }}
                                                    >
                                                        تسوية الجرد
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
