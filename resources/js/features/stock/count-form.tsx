import { useState } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { StockCountInput } from '@/types/store';

export type CountProduct = {
    id: number;
    name: string;
    unit: string;
    quantity: string;
    revision: number;
};
export default function CountForm({
    product,
    disabled,
    onSubmit,
    onCancel,
    onRefresh,
}: {
    product: CountProduct;
    disabled: boolean;
    onSubmit: (input: StockCountInput) => Promise<void>;
    onCancel: () => void;
    onRefresh: () => void;
}) {
    const [quantity, setQuantity] = useState('');
    const [reference, setReference] = useState('');
    const [reason, setReason] = useState('');
    return (
        <Card>
            <CardHeader>
                <CardTitle>تسوية الجرد: {product.name}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <p>
                    الرصيد المسجل قبل الجرد:{' '}
                    <bdi dir="ltr">{product.quantity}</bdi> {product.unit}
                </p>
                <p className="text-sm text-muted-foreground">
                    أوقف حركة هذا الصنف أثناء العد. أدخل الموجود فعليًا؛ تُسجل
                    حركة بالفرق مع الاحتفاظ بالسجلات السابقة. هذا ليس توريد
                    مشتريات ولا تسوية مالية.
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        void onSubmit({
                            product_id: product.id,
                            expected_quantity: product.quantity,
                            expected_revision: product.revision,
                            counted_quantity: quantity,
                            reference,
                            reason,
                        });
                    }}
                >
                    <fieldset
                        disabled={disabled}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <FormField
                            label="الكمية الموجودة فعليًا"
                            type="number"
                            dir="ltr"
                            min="0"
                            step="0.001"
                            required
                            value={quantity}
                            onChange={(event) =>
                                setQuantity(event.target.value)
                            }
                        />
                        <FormField
                            label="مرجع محضر الجرد"
                            maxLength={120}
                            required
                            value={reference}
                            onChange={(event) =>
                                setReference(event.target.value)
                            }
                        />
                        <FormField
                            label="سبب التسوية"
                            maxLength={500}
                            required
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                        />
                        <div className="flex flex-wrap items-end gap-2">
                            <Button type="submit">تأكيد الكمية المعدودة</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setQuantity('');
                                    onRefresh();
                                }}
                            >
                                تحديث الرصيد وإعادة العد
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onCancel}
                            >
                                إلغاء
                            </Button>
                        </div>
                    </fieldset>
                </form>
            </CardContent>
        </Card>
    );
}
