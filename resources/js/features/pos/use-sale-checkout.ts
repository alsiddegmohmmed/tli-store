import { useRef, useState } from 'react';
import { RequestError, requestJson } from '@/lib/request-json';
import { sales as saleRoute } from '@/routes/pos';
import type { CheckoutInput, SavedSale } from '@/types/store';

type PendingSale = { operation_id: string; input: CheckoutInput };
function restore(key: string): { pending: PendingSale | null; error: string } {
    try {
        const stored = sessionStorage.getItem(key);
        if (!stored) return { pending: null, error: '' };
        const value = JSON.parse(stored) as PendingSale;
        if (
            typeof value.operation_id !== 'string' ||
            !Number.isInteger(value.input?.station_id) ||
            !Array.isArray(value.input.items)
        )
            throw new Error();
        return { pending: value, error: '' };
    } catch {
        return {
            pending: null,
            error: 'تعذر استعادة محاولة الحفظ. راجع المسؤول وسجل المبيعات قبل إنشاء بيع جديد.',
        };
    }
}

export function useSaleCheckout(
    userId: number,
    onSaved: (sale: SavedSale) => void,
) {
    const key = `tli:checkout:${userId}`;
    const [initial] = useState(() => restore(key));
    const [pending, setPending] = useState(initial.pending);
    const [error, setError] = useState(initial.error);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState<SavedSale | null>(null);
    const inFlight = useRef(false);
    const blocked = initial.error !== '';

    async function send(attempt: PendingSale) {
        if (inFlight.current || blocked) return;
        inFlight.current = true;
        setSaving(true);
        setError('');
        let committed = false;
        try {
            const sale = await requestJson<SavedSale>(saleRoute.url(), {
                method: 'POST',
                body: JSON.stringify({
                    operation_id: attempt.operation_id,
                    ...attempt.input,
                }),
            });
            committed = true;
            // Persist basket removal before discarding the operation identity.
            onSaved(sale);
            sessionStorage.removeItem(key);
            setPending(null);
            setSaved(sale);
        } catch (failure) {
            if (
                !committed &&
                failure instanceof RequestError &&
                failure.status === 422
            ) {
                try {
                    sessionStorage.removeItem(key);
                    setPending(null);
                } catch {
                    /* Keep the same attempt for recovery. */
                }
            }
            setError(
                committed
                    ? 'حُفظ البيع لكن تعذر تحديث السلة في المتصفح. أعد استعادة نفس المحاولة؛ لن ينشأ بيع آخر.'
                    : failure instanceof Error
                      ? failure.message
                      : 'تعذر معرفة نتيجة الحفظ. أعد استعادة نفس المحاولة.',
            );
        } finally {
            inFlight.current = false;
            setSaving(false);
        }
    }

    async function submit(input: CheckoutInput) {
        if (inFlight.current || blocked || pending) return;
        const attempt = { operation_id: crypto.randomUUID(), input };
        try {
            sessionStorage.setItem(key, JSON.stringify(attempt));
        } catch {
            setError('تعذر حفظ هوية المحاولة في المتصفح. لم يُرسل البيع.');
            return;
        }
        setPending(attempt);
        setSaved(null);
        await send(attempt);
    }
    return {
        pending,
        error,
        saving,
        saved,
        blocked,
        submit,
        retry: () => (pending ? send(pending) : Promise.resolve()),
    };
}
