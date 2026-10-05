import { Head, Link } from '@inertiajs/react';
import DateTime from '@/components/date-time';
import Money from '@/components/money';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import JournalTable from '@/features/accounting/journal-table';
import type { JournalLine } from '@/features/accounting/journal-table';
import { show } from '@/routes/shifts';
import type { CashierShift, ShiftCashTransfer } from '@/types/store';

export default function CashTransfer({
    shift,
    transfer,
    journal,
}: {
    shift: CashierShift;
    transfer: ShiftCashTransfer;
    journal: JournalLine[];
}) {
    return (
        <>
            <Head title={`تحويل نقدي ${transfer.id}`} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold">
                    التحويل النقدي {transfer.id} — محفوظ
                </h1>
                <Link href={show(shift.id)} className="underline">
                    العودة إلى الوردية {shift.id}
                </Link>
            </div>
            <p className="text-sm text-muted-foreground">
                {shift.station.name} — {shift.cashier.name}
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>
                        {transfer.direction === 'in'
                            ? 'من الخزنة الرئيسية إلى الصندوق'
                            : 'من الصندوق إلى الخزنة الرئيسية'}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-2xl font-bold">
                        <Money amount={transfer.amount} />
                    </p>
                    <dl className="space-y-3">
                        <div>
                            <dt>مرجع التحويل</dt>
                            <dd className="whitespace-pre-wrap">
                                {transfer.reference}
                            </dd>
                        </div>
                        <div>
                            <dt>السبب</dt>
                            <dd className="whitespace-pre-wrap">
                                {transfer.reason}
                            </dd>
                        </div>
                        <div>
                            <dt>سجّل التحويل</dt>
                            <dd>
                                {transfer.author.name} —{' '}
                                <DateTime value={transfer.recorded_at} />
                            </dd>
                        </div>
                        <div>
                            <dt>رقم المحاولة</dt>
                            <dd>
                                <bdi dir="ltr">{transfer.operation_id}</bdi>
                            </dd>
                        </div>
                    </dl>
                    <p className="text-sm text-muted-foreground">
                        سجل محفوظ للقراءة. التحويل يدخل في مطابقة الوردية؛ لا
                        يمثل بيعًا أو مصروفًا.
                    </p>
                </CardContent>
            </Card>
            <JournalTable lines={journal} />
        </>
    );
}
