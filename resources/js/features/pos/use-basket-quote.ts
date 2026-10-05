import { useServerPreview } from '@/hooks/use-server-preview';
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
    const result = useServerPreview<Quote>(
        quoteRoute.url(),
        payload,
        !!stationId && basket.length > 0,
    );
    return {
        quote: result.data,
        error: result.error,
        pending: result.pending,
        retry: result.retry,
    };
}
