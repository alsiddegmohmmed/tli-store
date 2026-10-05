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
        canManageCompany: boolean;
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
    shift_id?: number;
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

export type CompanyProfile = {
    legal_name: string;
    vat_number: string;
    commercial_registration: string;
    street: string;
    building_number: string;
    district: string;
    city: string;
    postal_code: string;
};
export type SellerSnapshot = CompanyProfile & {
    country_code: string;
    profile_updated_at: string;
};
export type SaleDetail = SaleSummary & {
    shift_id: number | null;
    net: string;
    tax: string;
    cash_tender: string;
    change: string;
    fiscal_status: string;
    seller_snapshot: SellerSnapshot | null;
    lines: {
        id: number;
        name: string;
        barcode: string;
        unit: string;
        quantity: string;
        unit_price: string;
        tax_rate: string;
        net: string;
        tax: string;
        total: string;
    }[];
    payments: {
        id: number;
        method: string;
        amount: string;
        reference: string | null;
        manually_confirmed: boolean;
    }[];
};

export type CashierShift = {
    id: number;
    station_id: number;
    cashier_id: number;
    opening_cash: string;
    opened_at: string;
    closed_at: string | null;
    counted_cash: string | null;
    difference: string | null;
    closing_note: string | null;
    station: { id: number; name: string };
    cashier: { id: number; name: string };
};
export type ShiftTotals = {
    sale_count: number;
    sales_total: string;
    cash_sales: string;
    card_sales: string;
    expected_cash: string;
};
