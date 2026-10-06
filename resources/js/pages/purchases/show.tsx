import { Head, Link } from '@inertiajs/react';
import DateTime from '@/components/date-time';
import Money from '@/components/money';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import JournalTable from '@/features/accounting/journal-table';
import type { JournalLine } from '@/features/accounting/journal-table';
import type { PurchaseBill } from '@/features/purchases/types';
import { index } from '@/routes/purchases';

export default function ShowPurchaseBill({
    bill,
    journal,
}: {
    bill: PurchaseBill;
    journal: JournalLine[];
}) {
    return (
        <>
            <Head title={`فاتورة المورد ${bill.id}`} />
            <h1 className="text-2xl font-bold">
                فاتورة المورد {bill.id} — محفوظة
            </h1>
            <Link
                href={index({ query: { supplier_id: bill.supplier_id } })}
                className="underline"
            >
                فواتير هذا المورد
            </Link>
            <Card>
                <CardHeader>
                    <CardTitle>{bill.supplier_snapshot.name}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-2xl font-bold">
                        <Money amount={bill.total} />
                    </p>
                    <dl className="space-y-3">
                        <div>
                            <dt>رقم مستند المورد</dt>
                            <dd>
                                <bdi dir="ltr">{bill.reference}</bdi>
                            </dd>
                        </div>
                        <div>
                            <dt>تاريخ الفاتورة</dt>
                            <dd>
                                <bdi dir="ltr">{bill.bill_date}</bdi>
                            </dd>
                        </div>
                        <div>
                            <dt>البيان</dt>
                            <dd className="whitespace-pre-wrap">
                                {bill.description}
                            </dd>
                        </div>
                        <div>
                            <dt>الرقم الضريبي وقت التسجيل</dt>
                            <dd>
                                <bdi dir="ltr">
                                    {bill.supplier_snapshot.tax_number ??
                                        'غير مسجل لدينا'}
                                </bdi>
                            </dd>
                        </div>
                        <div>
                            <dt>الضريبة كما في المستند</dt>
                            <dd>
                                {bill.printed_tax === null ? (
                                    'غير مؤكدة'
                                ) : (
                                    <Money amount={bill.printed_tax} />
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt>سجل الفاتورة</dt>
                            <dd>
                                {bill.author?.name} —{' '}
                                <DateTime value={bill.recorded_at} />
                            </dd>
                        </div>
                        <div>
                            <dt>هوية المحاولة</dt>
                            <dd>
                                <bdi dir="ltr">{bill.operation_id}</bdi>
                            </dd>
                        </div>
                    </dl>
                    <p className="text-sm text-muted-foreground">
                        الفاتورة محفوظة للقراءة. المبلغ بانتظار التصنيف
                        المحاسبي. لم تُسجل دفعة أو حركة مخزون، ولم تُثبت ضريبة
                        قابلة للاسترداد. تعديل المورد لاحقًا لا يغير بيانات هذا
                        المستند.
                    </p>
                </CardContent>
            </Card>
            <JournalTable lines={journal} />
        </>
    );
}
