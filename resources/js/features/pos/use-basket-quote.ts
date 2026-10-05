import { useEffect, useState } from 'react';
import { requestJson } from '@/lib/request-json';
import { quote as quoteRoute } from '@/routes/pos';
import type { BasketLine, Quote } from '@/types/store';

export function useBasketQuote(stationId: string, basket: BasketLine[]) {
    const payload = JSON.stringify({
        station_id: Number(stationId),
        items: basket.map((line) => ({
            product_id: line.product.id,
            quantity: line.quantity,
        })),
    });
    const [result, setResult] = useState<{
        payload: string;
        quote?: Quote;
        error?: string;
    } | null>(null);
    const [attempt, setAttempt] = useState(0);
    useEffect(() => {
        if (!stationId || basket.length === 0) return;
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void requestJson<Quote>(quoteRoute.url(), {
                method: 'POST',
                body: payload,
                signal: controller.signal,
            })
                .then((quote) => {
                    if (!controller.signal.aborted)
                        setResult({ payload, quote });
                })
                .catch((error: unknown) => {
                    if (!controller.signal.aborted)
                        setResult({
                            payload,
                            error:
                                error instanceof Error
                                    ? error.message
                                    : 'تعذر حساب الإجمالي.',
                        });
                });
        }, 180);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [payload, stationId, basket.length, attempt]);
    const current =
        basket.length > 0 && result?.payload === payload ? result : null;
    return {
        quote: current?.quote,
        error: current?.error,
        pending: !!stationId && basket.length > 0 && !current,
        retry: () => {
            setResult(null);
            setAttempt((value) => value + 1);
        },
    };
}
