import DateTime from '@/components/date-time';
import Money from '@/components/money';
import type { SaleDetail } from '@/types/store';

export default function SaleReceipt({ sale }: { sale: SaleDetail }) {
    const seller = sale.seller_snapshot;
    return (
        <article
            className="sale-receipt space-y-3 bg-card p-3 text-card-foreground"
            aria-label={`إيصال تجريبي للبيع ${sale.id}`}
        >
            <header className="space-y-2 text-center">
                <h1 className="font-bold">إيصال بيع تجريبي</h1>
                <p className="font-bold">ليس فاتورة ضريبية — للاختبار فقط</p>
                {seller ? (
                    <>
                        <h2 className="font-bold">{seller.legal_name}</h2>
                        <p>
                            الرقم الضريبي:{' '}
                            <bdi dir="ltr">{seller.vat_number}</bdi>
                        </p>
                        <p>
                            السجل التجاري:{' '}
                            <bdi dir="ltr">
                                {seller.commercial_registration}
                            </bdi>
                        </p>
                        <p>
                            {seller.street}، مبنى{' '}
                            <bdi dir="ltr">{seller.building_number}</bdi>،{' '}
                            {seller.district}، {seller.city}،{' '}
                            <bdi dir="ltr">{seller.postal_code}</bdi>، السعودية
                        </p>
                    </>
                ) : (
                    <p>بيانات البائع لم تُسجل وقت حفظ هذا البيع.</p>
                )}
                <p>
                    سجل البيع <bdi dir="ltr">{sale.id}</bdi> —{' '}
                    {sale.station.name}
                </p>
                <p>
                    <DateTime value={sale.recorded_at} />
                </p>
            </header>
            <table className="w-full text-start">
                <caption className="sr-only">الأصناف والمبالغ المسجلة</caption>
                <thead>
                    <tr className="border-y">
                        <th className="py-2 text-start">الصنف</th>
                        <th className="py-2 text-end">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    {sale.lines.map((line) => (
                        <tr key={line.id} className="border-b align-top">
                            <td className="py-2 pe-2">
                                <p>{line.name}</p>
                                <p>
                                    <bdi dir="ltr">{line.quantity}</bdi>{' '}
                                    {line.unit} ×{' '}
                                    <Money amount={line.unit_price} />
                                </p>
                                <p>
                                    باركود: <bdi dir="ltr">{line.barcode}</bdi>
                                </p>
                                <p>
                                    ضريبة <bdi dir="ltr">{line.tax_rate}%</bdi>:{' '}
                                    <Money amount={line.tax} />
                                </p>
                            </td>
                            <td className="py-2 text-end">
                                <Money amount={line.total} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <dl className="space-y-2">
                {(
                    [
                        ['net', 'قبل الضريبة'],
                        ['tax', 'الضريبة'],
                        ['total', 'الإجمالي'],
                        ['cash_tender', 'النقد المستلم'],
                        ['change', 'الباقي للعميل'],
                    ] as const
                ).map(([key, label]) => (
                    <div
                        key={key}
                        className={`flex justify-between gap-2 ${key === 'total' ? 'font-bold' : ''}`}
                    >
                        <dt>{label}</dt>
                        <dd>
                            <Money amount={sale[key]} />
                        </dd>
                    </div>
                ))}
            </dl>
            <section
                className="space-y-2 border-t pt-2"
                aria-label="المدفوعات المسجلة"
            >
                {sale.payments.map((payment) => (
                    <div
                        key={payment.id}
                        className="flex justify-between gap-2"
                    >
                        <span>
                            {payment.method === 'cash'
                                ? 'نقدي'
                                : 'بطاقة — تأكيد يدوي'}
                        </span>
                        <Money amount={payment.amount} />
                    </div>
                ))}
            </section>
            <footer className="border-t pt-2 text-center">
                هذه نسخة من نفس البيع المحفوظ. لا تنشئ إعادة الطباعة بيعًا أو دفعة
                جديدة.
            </footer>
        </article>
    );
}
