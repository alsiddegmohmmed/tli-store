import { useState } from 'react';
import FormField from '@/components/form-field';
import Money from '@/components/money';
import { Button } from '@/components/ui/button';
import { useServerPreview } from '@/hooks/use-server-preview';
import { paymentPreview } from '@/routes/pos';
import type {
    BasketLine,
    CheckoutInput,
    PaymentQuote,
    Quote,
} from '@/types/store';

type Props = {
    station: string;
    shiftId: number;
    basket: BasketLine[];
    quote: Quote;
    disabled: boolean;
    onSubmit: (input: CheckoutInput) => Promise<void>;
    onCancel: () => void;
};
export default function PaymentPanel({
    station,
    shiftId,
    basket,
    quote,
    disabled,
    onSubmit,
    onCancel,
}: Props) {
    const [cash, setCash] = useState(quote.totals.total);
    const [card, setCard] = useState('0.00');
    const [confirmed, setConfirmed] = useState(false);
    const [reference, setReference] = useState('');
    const input: CheckoutInput = {
        station_id: Number(station),
        shift_id: shiftId,
        items: basket.map((line) => ({
            product_id: line.product.id,
            quantity: line.quantity,
        })),
        quote_fingerprint: quote.fingerprint,
        cash_tender: cash,
        card_amount: card,
        card_confirmed: confirmed,
        card_reference: reference || null,
    };
    const { data, pending, error, retry } = useServerPreview<PaymentQuote>(
        paymentPreview.url(),
        JSON.stringify({
            station_id: input.station_id,
            shift_id: shiftId,
            items: input.items,
            quote_fingerprint: input.quote_fingerprint,
            cash_tender: cash,
            card_amount: card,
        }),
        !disabled,
    );
    const needsCardConfirmation = data && data.card_amount !== '0.00';
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                if (data && !pending && !disabled) void onSubmit(input);
            }}
        >
            <fieldset disabled={disabled} className="space-y-3">
                <p className="text-sm font-medium">تسجيل الدفع</p>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            setCash(quote.totals.total);
                            setCard('0.00');
                            setConfirmed(false);
                        }}
                    >
                        نقدي
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            setCash('0.00');
                            setCard(quote.totals.total);
                            setConfirmed(false);
                        }}
                    >
                        بطاقة
                    </Button>
                </div>
                <FormField
                    label="النقد المستلم"
                    type="number"
                    dir="ltr"
                    min="0"
                    step="0.01"
                    required
                    value={cash}
                    onChange={(e) => setCash(e.target.value)}
                />
                <FormField
                    label="مبلغ البطاقة"
                    type="number"
                    dir="ltr"
                    min="0"
                    step="0.01"
                    required
                    value={card}
                    onChange={(e) => {
                        setCard(e.target.value);
                        setConfirmed(false);
                    }}
                />
                {needsCardConfirmation && (
                    <>
                        <p className="text-xs text-muted-foreground">
                            جهاز الدفع منفصل عن النظام. سجل نتيجة العملية بعد
                            التأكد من نجاحها على الجهاز.
                        </p>
                        <FormField
                            label="مرجع عملية البطاقة (اختياري)"
                            dir="ltr"
                            maxLength={64}
                            value={reference}
                            onChange={(e) => setReference(e.target.value)}
                        />
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={confirmed}
                                onChange={(e) => setConfirmed(e.target.checked)}
                                required
                            />
                            تأكدت من نجاح عملية البطاقة على جهاز الدفع
                        </label>
                    </>
                )}
                {data && (
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <dt>النقد المسجل للبيع</dt>
                            <dd>
                                <Money amount={data.cash_applied} />
                            </dd>
                        </div>
                        <div className="flex justify-between font-bold">
                            <dt>الباقي للعميل</dt>
                            <dd>
                                <Money amount={data.change} />
                            </dd>
                        </div>
                    </dl>
                )}
                {pending && (
                    <p role="status" className="text-sm text-muted-foreground">
                        جارٍ مراجعة الدفع والمخزون…
                    </p>
                )}
                {error && (
                    <>
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                        <Button type="button" variant="outline" onClick={retry}>
                            إعادة المراجعة
                        </Button>
                    </>
                )}
                <Button
                    type="submit"
                    className="w-full"
                    disabled={
                        !data ||
                        pending ||
                        (!!needsCardConfirmation && !confirmed)
                    }
                >
                    حفظ البيع التجريبي
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    onClick={onCancel}
                >
                    العودة للسلة
                </Button>
            </fieldset>
        </form>
    );
}
