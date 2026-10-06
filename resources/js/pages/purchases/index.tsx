import { Head, Link } from '@inertiajs/react';
import Money from '@/components/money';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Card, CardContent } from '@/components/ui/card';
import type { PurchaseBill } from '@/features/purchases/types';
import { create, index, show } from '@/routes/purchases';
import { index as suppliersIndex } from '@/routes/suppliers';
import type { Supplier } from '@/types/store';

export default function Purchases({
    bills,
    supplier,
}: {
    bills: Paginated<PurchaseBill>;
    supplier: Supplier | null;
}) {
    return (
        <>
            <Head title="فواتير الموردين" />
            <h1 className="text-2xl font-bold">
                فواتير الموردين{supplier ? ` — ${supplier.name}` : ''}
            </h1>
            <div className="flex flex-wrap gap-4">
                {supplier?.is_active && (
                    <Link href={create(supplier.id)} className="underline">
                        فاتورة جديدة لهذا المورد
                    </Link>
                )}
                {supplier && (
                    <Link href={index()} className="underline">
                        كل فواتير الموردين
                    </Link>
                )}
                <Link href={suppliersIndex()} className="underline">
                    اختيار مورد
                </Link>
            </div>
            <p className="text-sm text-muted-foreground">
                الفاتورة تسجل المبلغ المستحق فقط. استلام البضاعة والدفع والتصنيف
                النهائي خطوات منفصلة. هذا السجل لا يثبت الأرصدة التاريخية أو أهلية
                خصم الضريبة.
            </p>
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <div className="overflow-x-auto">
                        <table className="w-full text-start text-sm">
                            <thead>
                                <tr className="border-b">
                                    {[
                                        'السجل',
                                        'المورد',
                                        'رقم المستند',
                                        'التاريخ',
                                        'الإجمالي',
                                        'الضريبة المسجلة',
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
                                {bills.data.map((bill) => (
                                    <tr key={bill.id} className="border-b">
                                        <td className="p-3">
                                            <Link
                                                href={show(bill.id)}
                                                className="underline"
                                            >
                                                {bill.id}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            {bill.supplier_snapshot.name}
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {bill.reference}
                                            </bdi>
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {bill.bill_date}
                                            </bdi>
                                        </td>
                                        <td className="p-3">
                                            <Money amount={bill.total} />
                                        </td>
                                        <td className="p-3">
                                            {bill.printed_tax === null ? (
                                                'غير مؤكدة'
                                            ) : (
                                                <Money
                                                    amount={bill.printed_tax}
                                                />
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {bills.data.length === 0 && (
                        <p className="text-muted-foreground">
                            لم تُسجل فواتير مطابقة. اختر موردًا لإضافة فاتورته.
                        </p>
                    )}
                    <Pagination page={bills} label="صفحات فواتير الموردين" />
                </CardContent>
            </Card>
        </>
    );
}
