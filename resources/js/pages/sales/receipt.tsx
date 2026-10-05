import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import SaleReceipt from '@/features/sales/sale-receipt';
import { show } from '@/routes/sales';
import type { SaleDetail } from '@/types/store';

export default function Receipt({
    sale,
    paperWidth,
}: {
    sale: SaleDetail;
    paperWidth: '58' | '80';
}) {
    const [width, setWidth] = useState(paperWidth);
    const [openedPrint, setOpenedPrint] = useState(false);
    return (
        <>
            <Head title={`إيصال تجريبي ${sale.id}`} />
            <main
                className={`receipt-page receipt-paper-${width} space-y-4 p-4`}
            >
                <div className="mx-auto max-w-xl space-y-3 print:hidden">
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            onClick={() => {
                                setOpenedPrint(true);
                                window.print();
                            }}
                        >
                            فتح نافذة الطباعة
                        </Button>
                        <Label htmlFor="receipt-width">عرض الورق</Label>
                        <select
                            id="receipt-width"
                            className="rounded-md border bg-card p-2"
                            value={width}
                            onChange={(event) =>
                                setWidth(event.target.value as '58' | '80')
                            }
                        >
                            <option value="80">80 مم</option>
                            <option value="58">58 مم</option>
                        </select>
                        <Link href={show(sale.id)} className="underline">
                            العودة لسجل البيع
                        </Link>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        اختر الطابعة ومقاس الورق من نافذة الطباعة. هذه نسخة
                        تجريبية؛ الفوترة الرسمية لم تُفعّل بعد.
                    </p>
                    {openedPrint && (
                        <p role="status" className="text-sm">
                            تحقق من خروج الإيصال من الطابعة. يمكنك إعادة فتح
                            نافذة الطباعة لنفس البيع عند الحاجة.
                        </p>
                    )}
                </div>
                <SaleReceipt sale={sale} />
            </main>
        </>
    );
}
