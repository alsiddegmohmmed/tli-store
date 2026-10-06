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

export type SupplierPaymentInput = {
    payment_date: string;
    amount: string;
    method: 'cash_reserve' | 'bank';
    reference: string;
    funding_reference: string;
    actual_payer: string;
    confirmed_paid_from_company: boolean;
};
export type SupplierPayment = Omit<
    SupplierPaymentInput,
    'confirmed_paid_from_company'
> & {
    id: number;
    operation_id: string;
    purchase_bill_id: number;
    recorded_at: string;
    author?: { name: string };
};
export type BillBalance = { paid: string; remaining: string };
