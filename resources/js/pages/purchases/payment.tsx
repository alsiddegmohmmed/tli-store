import { Head, Link } from '@inertiajs/react';
import DateTime from '@/components/date-time';
import Money from '@/components/money';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import JournalTable from '@/features/accounting/journal-table';
import type { JournalLine } from '@/features/accounting/journal-table';
import type { PurchaseBill, SupplierPayment } from '@/features/purchases/types';
import { show } from '@/routes/purchases';

export default function Payment({
    bill,
    payment,
    journal,
}: {
    bill: PurchaseBill;
    payment: SupplierPayment;
    journal: JournalLine[];
}) {
    return (
        <>
            <Head title={`سداد المورد ${payment.id}`} />
            <h1 className="text-2xl font-bold">
                سداد المورد {payment.id} — محفوظ
            </h1>
            <Link href={show(bill.id)} className="underline">
                العودة إلى الفاتورة {bill.id}
            </Link>
            <Card>
                <CardHeader>
                    <CardTitle>{bill.supplier_snapshot.name}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-2xl font-bold">
                        <Money amount={payment.amount} />
                    </p>
                    <dl className="space-y-3">
                        <div>
                            <dt>تاريخ السداد</dt>
                            <dd>
                                <bdi dir="ltr">{payment.payment_date}</bdi>
                            </dd>
                        </div>
                        <div>
                            <dt>طريقة السداد</dt>
                            <dd>
                                {payment.method === 'bank'
                                    ? 'حساب بنكي للشركة'
                                    : 'الخزنة الرئيسية للشركة'}
                            </dd>
                        </div>
                        <div>
                            <dt>مرجع إثبات الدفع</dt>
                            <dd>
                                <bdi dir="ltr">{payment.reference}</bdi>
                            </dd>
                        </div>
                        <div>
                            <dt>مرجع مصدر أموال الشركة</dt>
                            <dd>{payment.funding_reference}</dd>
                        </div>
                        <div>
                            <dt>من دفع فعليًا</dt>
                            <dd>{payment.actual_payer}</dd>
                        </div>
                        <div>
                            <dt>سجل السداد</dt>
                            <dd>
                                {payment.author?.name} —{' '}
                                <DateTime value={payment.recorded_at} />
                            </dd>
                        </div>
                        <div>
                            <dt>هوية المحاولة</dt>
                            <dd>
                                <bdi dir="ltr">{payment.operation_id}</bdi>
                            </dd>
                        </div>
                    </dl>
                    <p className="text-sm text-muted-foreground">
                        السداد يخفض المبلغ المستحق على الفاتورة. لم تنشأ مشتريات
                        أو مصروفات أو حركة مخزون إضافية. هذا سجل لدفع أكدته؛
                        التطبيق لا يحول الأموال. التصحيحات المرتبطة بالسداد لم
                        تُفعّل بعد.
                    </p>
                </CardContent>
            </Card>
            <JournalTable lines={journal} />
        </>
    );
}
