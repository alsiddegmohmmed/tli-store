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
