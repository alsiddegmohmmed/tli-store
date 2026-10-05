export type Product = {
    id: number;
    barcode: string;
    name: string;
    unit: string;
    price: string;
    tax_rate: string;
    is_active: boolean;
};
export type Station = { id: number; code: string; name: string };
export type SharedProps = {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            role: 'owner' | 'accountant' | 'cashier';
        };
        canManageProducts: boolean;
    };
    flash: { success?: string };
    [key: string]: unknown;
};
export type BasketLine = { product: Product; quantity: string };
export type Quote = {
    fingerprint: string;
    lines: {
        product_id: number;
        quantity: string;
        unit_price: string;
        net: string;
        tax: string;
        total: string;
    }[];
    totals: { net: string; tax: string; total: string };
};

export type PaymentQuote = {
    total: string;
    cash_tender: string;
    cash_applied: string;
    card_amount: string;
    change: string;
};
export type CheckoutInput = {
    station_id: number;
    items: { product_id: number; quantity: string }[];
    quote_fingerprint: string;
    cash_tender: string;
    card_amount: string;
    card_confirmed: boolean;
    card_reference: string | null;
};
export type SavedSale = {
    id: number;
    station_id: number;
    total: string;
    change: string;
    fiscal_status: string;
    url: string;
};
export type SaleSummary = {
    id: number;
    total: string;
    recorded_at: string;
    cashier: { name: string };
    station: { name: string };
};
