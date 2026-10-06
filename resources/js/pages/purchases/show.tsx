import { Head, Link } from '@inertiajs/react';
import DateTime from '@/components/date-time';
import Money from '@/components/money';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import JournalTable from '@/features/accounting/journal-table';
import type { JournalLine } from '@/features/accounting/journal-table';
import type { PurchaseBill } from '@/features/purchases/types';
import { index, evidence as uploadEvidence } from '@/routes/purchases';
import EvidencePanel from '@/features/evidence/evidence-panel';
import type { Evidence } from '@/features/evidence/evidence-panel';
import { show as paymentShow } from '@/routes/purchases/payments';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import SupplierPaymentForm from '@/features/purchases/supplier-payment-form';
import type { BillBalance, SupplierPayment } from '@/features/purchases/types';

export default function ShowPurchaseBill({
    bill,
    journal,
    balance,
    payments,
    today,
    evidence,
    evidenceKinds,
}: {
    bill: PurchaseBill;
    journal: JournalLine[];
    balance: BillBalance;
    payments: Paginated<SupplierPayment>;
    today: string;
    evidence: Paginated<Evidence>;
    evidenceKinds: Record<string, string>;
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
            <a href="#supplier-payment" className="ms-4 underline">
                تسجيل سداد ومراجعة الدفعات
            </a>
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
                        المحاسبي. إنشاء الفاتورة لا يسجل دفعة أو حركة مخزون أو
                        ضريبة قابلة للاسترداد. الدفعات المرتبطة تظهر أدناه. تعديل
                        المورد لا يغير هذا المستند.
                    </p>
                </CardContent>
            </Card>
            <JournalTable lines={journal} />
            <EvidencePanel
                files={evidence}
                kinds={evidenceKinds}
                uploadUrl={uploadEvidence.url(bill.id)}
            />
            <div id="supplier-payment" className="scroll-mt-4">
                <SupplierPaymentForm
                    key={bill.id}
                    billId={bill.id}
                    billDate={bill.bill_date}
                    today={today}
                    balance={balance}
                />
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>الدفعات المرتبطة ({payments.total})</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="overflow-x-auto">
                        <table className="w-full text-start text-sm">
                            <thead>
                                <tr className="border-b">
                                    {[
                                        'السداد',
                                        'التاريخ',
                                        'المبلغ',
                                        'الطريقة',
                                        'المرجع',
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
                                {payments.data.map((payment) => (
                                    <tr key={payment.id} className="border-b">
                                        <td className="p-3">
                                            <Link
                                                href={paymentShow([
                                                    bill.id,
                                                    payment.id,
                                                ])}
                                                className="underline"
                                            >
                                                {payment.id}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {payment.payment_date}
                                            </bdi>
                                        </td>
                                        <td className="p-3">
                                            <Money amount={payment.amount} />
                                        </td>
                                        <td className="p-3">
                                            {payment.method === 'bank'
                                                ? 'بنك الشركة'
                                                : 'الخزنة الرئيسية'}
                                        </td>
                                        <td className="p-3">
                                            <bdi dir="ltr">
                                                {payment.reference}
                                            </bdi>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {payments.data.length === 0 && (
                        <p className="text-muted-foreground">
                            لم تُسجل دفعات على هذه الفاتورة.
                        </p>
                    )}
                    <Pagination page={payments} label="صفحات دفعات المورد" />
                </CardContent>
            </Card>
        </>
    );
}
