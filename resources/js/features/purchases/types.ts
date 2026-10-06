import type { Supplier } from '@/types/store';

export type PurchaseBillInput = {
    supplier_id: number;
    reference: string;
    bill_date: string;
    description: string;
    total: string;
    printed_tax: string | null;
    confirmed_unpaid: boolean;
};
export type PurchaseBill = {
    id: number;
    operation_id: string;
    supplier_id: number;
    supplier_snapshot: Pick<
        Supplier,
        'name' | 'tax_number' | 'commercial_registration' | 'phone' | 'city'
    >;
    reference: string;
    bill_date: string;
    description: string;
    total: string;
    printed_tax: string | null;
    recorded_at: string;
    author?: { name: string };
};
