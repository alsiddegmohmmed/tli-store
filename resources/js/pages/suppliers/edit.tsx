import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SupplierForm from '@/features/suppliers/supplier-form';
import { index } from '@/routes/suppliers';
import type { Supplier } from '@/types/store';

export default function EditSupplier({ supplier }: { supplier: Supplier }) {
    return (
        <>
            <Head title={`المورد: ${supplier.name}`} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold">{supplier.name}</h1>
                <Link href={index()} className="underline">
                    العودة إلى الموردين
                </Link>
            </div>
            <p className="text-sm text-muted-foreground">
                تعطيل المورد يحفظ سجله. تسجيل أو تعديل بياناته لا ينشئ فاتورة أو
                دفعة أو رصيدًا افتتاحيًا.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>بيانات المورد</CardTitle>
                </CardHeader>
                <CardContent>
                    <SupplierForm key={supplier.id} supplier={supplier} />
                </CardContent>
            </Card>
        </>
    );
}
