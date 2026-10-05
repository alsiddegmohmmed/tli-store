import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SupplierForm from '@/features/suppliers/supplier-form';
import { index, edit } from '@/routes/suppliers';
import type { Supplier } from '@/types/store';

export default function Suppliers({
    suppliers,
    search,
}: {
    suppliers: Paginated<Supplier>;
    search: string;
}) {
    const [query, setQuery] = useState(search);
    return (
        <>
            <Head title="الموردون" />
            <h1 className="text-2xl font-bold">الموردون</h1>
            <p className="text-sm text-muted-foreground">
                أدخل البيانات المعروفة فقط. ترك الرقم الضريبي فارغًا لا يعني أن
                المورد غير مسجل.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>إضافة مورد</CardTitle>
                </CardHeader>
                <CardContent>
                    <SupplierForm />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>قائمة الموردين ({suppliers.total})</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <form
                        className="flex items-end gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                index.url(),
                                { search: query },
                                { preserveState: true },
                            );
                        }}
                    >
                        <div className="max-w-md flex-1">
                            <FormField
                                label="بحث بالاسم أو الرقم الضريبي أو الاتصال"
                                maxLength={120}
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
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
                                    {[
                                        'المورد',
                                        'الرقم الضريبي',
                                        'الاتصال',
                                        'المدينة',
                                        'الحالة',
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
                                {suppliers.data.map((supplier) => (
                                    <tr key={supplier.id} className="border-b">
                                        <td className="p-3">
                                            <Link
                                                href={edit(supplier.id)}
                                                className="underline"
                                            >
                                                {supplier.name}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {supplier.tax_number ??
                                                    'غير مسجل لدينا'}
                                            </bdi>
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {supplier.phone ??
                                                    'غير مسجل لدينا'}
                                            </bdi>
                                        </td>
                                        <td className="p-3">
                                            {supplier.city ?? 'غير مسجلة لدينا'}
                                        </td>
                                        <td className="p-3">
                                            {supplier.is_active
                                                ? 'نشط'
                                                : 'غير نشط'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {suppliers.data.length === 0 && (
                        <p className="text-muted-foreground">
                            لا يوجد مورد مطابق. يمكنك إضافة مورد من النموذج أعلاه.
                        </p>
                    )}
                    <Pagination page={suppliers} label="صفحات الموردين" />
                </CardContent>
            </Card>
        </>
    );
}
