import { useRecordedSubmission } from '@/hooks/use-recorded-submission';
import { sales as saleRoute } from '@/routes/pos';
import type { CheckoutInput, SavedSale } from '@/types/store';

export function useSaleCheckout(
    userId: number,
    onSaved: (sale: SavedSale) => void,
) {
    return useRecordedSubmission<CheckoutInput, SavedSale>({
        storageKey: `tli:checkout:${userId}`,
        url: saleRoute.url(),
        validateInput: (input) =>
            Number.isInteger(input.station_id) && Array.isArray(input.items),
        onConfirmed: onSaved,
    });
}
