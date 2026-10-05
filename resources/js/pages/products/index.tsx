import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import Money from '@/components/money';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, store } from '@/routes/products';
import type { Product, SharedProps } from '@/types/store';

type Props = {
    search: string;
    products: {
        data: Product[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};
export default function Products({ products, search }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const [query, setQuery] = useState(search);
    const form = useForm({
        barcode: '',
        name: '',
        unit: 'حبة',
        price: '',
        tax_rate: '15',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store.url(), { onSuccess: () => form.reset() });
    }
    return (
        <>
            <Head title="الأصناف" />
            <h1 className="text-2xl font-bold">الأصناف</h1>
            <p className="text-sm text-muted-foreground">
                الأسعار المدخلة تشمل الضريبة. إعدادات المعاينة لا تمثل إعدادًا
                مكتملًا للفوترة الرسمية.
            </p>
            {auth.canManageProducts && (
                <Card>
                    <CardHeader>
                        <CardTitle>إضافة صنف</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
                        >
                            <FormField
                                label="الباركود"
                                dir="ltr"
                                required
                                value={form.data.barcode}
                                onChange={(e) =>
                                    form.setData('barcode', e.target.value)
                                }
                                error={form.errors.barcode}
                            />
                            <FormField
                                label="اسم الصنف"
                                required
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                error={form.errors.name}
                            />
                            <FormField
                                label="الوحدة"
                                required
                                value={form.data.unit}
                                onChange={(e) =>
                                    form.setData('unit', e.target.value)
                                }
                                error={form.errors.unit}
                            />
                            <FormField
                                label="السعر شامل الضريبة"
                                type="number"
                                dir="ltr"
                                min="0"
                                step="0.01"
                                required
                                value={form.data.price}
                                onChange={(e) =>
                                    form.setData('price', e.target.value)
                                }
                                error={form.errors.price}
                            />
                            <FormField
                                label="نسبة الضريبة %"
                                type="number"
                                dir="ltr"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                value={form.data.tax_rate}
                                onChange={(e) =>
                                    form.setData('tax_rate', e.target.value)
                                }
                                error={form.errors.tax_rate}
                            />
                            <Button disabled={form.processing} type="submit">
                                {form.processing ? 'جارٍ الحفظ…' : 'إضافة الصنف'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}
            <Card>
                <CardHeader>
                    <CardTitle>قائمة الأصناف ({products.total})</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(
                                index.url(),
                                { search: query },
                                { preserveState: true },
                            );
                        }}
                        className="flex items-end gap-2"
                    >
                        <div className="max-w-md flex-1">
                            <FormField
                                label="بحث بالاسم أو الباركود"
                                maxLength={60}
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            بحث
                        </Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="w-full text-start text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="p-3 text-start">الباركود</th>
                                    <th className="p-3 text-start">الصنف</th>
                                    <th className="p-3 text-start">الوحدة</th>
                                    <th className="p-3 text-start">السعر</th>
                                    <th className="p-3 text-start">الضريبة</th>
                                </tr>
                            </thead>
                            <tbody>
                                {products.data.map((product) => (
                                    <tr className="border-b" key={product.id}>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {product.barcode}
                                            </bdi>
                                        </td>
                                        <td className="p-3">{product.name}</td>
                                        <td className="p-3">{product.unit}</td>
                                        <td className="p-3">
                                            <Money amount={product.price} />
                                        </td>
                                        <td className="p-3">
                                            <bdi>{product.tax_rate}%</bdi>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {products.data.length === 0 && (
                        <p className="py-4 text-muted-foreground">
                            لا توجد أصناف مطابقة.{' '}
                            {auth.canManageProducts
                                ? 'يمكنك إضافة الأصناف من النموذج أعلاه.'
                                : 'اطلب من المحاسب إضافة الأصناف.'}
                        </p>
                    )}
                    <nav
                        aria-label="صفحات الأصناف"
                        className="flex items-center gap-3"
                    >
                        {products.prev_page_url && (
                            <Button asChild variant="outline">
                                <Link href={products.prev_page_url}>
                                    السابق
                                </Link>
                            </Button>
                        )}
                        <span className="text-sm text-muted-foreground">
                            صفحة {products.current_page} من {products.last_page}
                        </span>
                        {products.next_page_url && (
                            <Button asChild variant="outline">
                                <Link href={products.next_page_url}>
                                    التالي
                                </Link>
                            </Button>
                        )}
                    </nav>
                </CardContent>
            </Card>
        </>
    );
}
