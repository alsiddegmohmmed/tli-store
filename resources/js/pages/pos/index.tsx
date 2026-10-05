import { Head, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import Money from '@/components/money';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBasketQuote } from '@/features/pos/use-basket-quote';
import { requestJson } from '@/lib/request-json';
import { products as lookup } from '@/routes/pos';
import type { BasketLine, Product, SharedProps, Station } from '@/types/store';

type Baskets = Record<string, BasketLine[]>;
function restore(
    key: string,
    stations: Station[],
): { station: string; baskets: Baskets } {
    try {
        const state = JSON.parse(sessionStorage.getItem(key) ?? 'null');
        if (
            stations.some((station) => String(station.id) === state?.station) &&
            state?.baskets &&
            typeof state.baskets === 'object'
        ) {
            const baskets: Baskets = {};
            for (const station of stations) {
                const lines = state.baskets[String(station.id)];
                if (Array.isArray(lines))
                    baskets[String(station.id)] = lines
                        .filter(
                            (line: BasketLine) =>
                                Number.isInteger(line?.product?.id) &&
                                typeof line.product.name === 'string' &&
                                typeof line.product.unit === 'string' &&
                                typeof line.product.barcode === 'string' &&
                                typeof line.product.price === 'string' &&
                                /^\d+\.\d{2}$/.test(line.product.price) &&
                                typeof line.quantity === 'string',
                        )
                        .slice(0, 100);
            }
            return { station: state.station, baskets };
        }
    } catch {
        /* A corrupt/unavailable browser draft is safe to ignore. */
    }
    return { station: String(stations[0]?.id ?? ''), baskets: {} };
}

export default function Pos({ stations }: { stations: Station[] }) {
    const { auth } = usePage<SharedProps>().props;
    const storageKey = `tli:pos:${auth.user.id}`;
    const [initial] = useState(() => restore(storageKey, stations));
    const [station, setStation] = useState(initial.station);
    const [baskets, setBaskets] = useState<Baskets>(initial.baskets);
    const basket = baskets[station] ?? [];
    const [barcode, setBarcode] = useState('');
    const [search, setSearch] = useState('');
    const [matches, setMatches] = useState<Product[]>([]);
    const [searching, setSearching] = useState(false);
    const [error, setError] = useState('');
    const [storageError, setStorageError] = useState(false);
    const scanner = useRef<HTMLInputElement>(null);
    const {
        quote,
        pending,
        error: quoteError,
        retry,
    } = useBasketQuote(station, basket);

    useEffect(() => {
        try {
            sessionStorage.setItem(
                storageKey,
                JSON.stringify({ station, baskets }),
            );
        } catch {
            setStorageError(true);
        }
    }, [storageKey, station, baskets]);

    function changeBasket(update: (current: BasketLine[]) => BasketLine[]) {
        setBaskets((current) => ({
            ...current,
            [station]: update(current[station] ?? []),
        }));
    }
    function add(product: Product) {
        if (
            basket.length >= 100 &&
            !basket.some((line) => line.product.id === product.id)
        ) {
            setError('السلة ممتلئة. الحد الأقصى 100 صنف مختلف.');
            scanner.current?.focus();
            return;
        }
        changeBasket((current) => {
            if (current.some((line) => line.product.id === product.id)) {
                return current.map((line) =>
                    line.product.id === product.id
                        ? {
                              product,
                              quantity: Math.min(
                                  10000,
                                  Number(line.quantity || 0) + 1,
                              ).toFixed(3),
                          }
                        : line,
                );
            }
            if (current.length >= 100) return current;
            return [...current, { product, quantity: '1' }];
        });
        setError('');
        setMatches([]);
        scanner.current?.focus();
    }
    async function scan(event: FormEvent) {
        event.preventDefault();
        const value = barcode.trim();
        if (!value || !station) return;
        setBarcode('');
        setError('');
        try {
            const data = await requestJson<{ products: Product[] }>(
                lookup.url({ query: { barcode: value } }),
            );
            add(data.products[0]);
        } catch (failure) {
            setError(
                failure instanceof Error
                    ? failure.message
                    : 'تعذر البحث عن الصنف.',
            );
        }
    }
    async function find(event: FormEvent) {
        event.preventDefault();
        setSearching(true);
        setError('');
        try {
            const data = await requestJson<{ products: Product[] }>(
                lookup.url({ query: { search } }),
            );
            setMatches(data.products);
            if (data.products.length === 0) setError('لا توجد أصناف بهذا الاسم.');
        } catch (failure) {
            setError(
                failure instanceof Error ? failure.message : 'تعذر البحث.',
            );
        } finally {
            setSearching(false);
        }
    }
    return (
        <>
            <Head title="نقطة البيع" />
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-2xl font-bold">نقطة البيع</h1>
                <div className="flex items-center gap-3">
                    <Label htmlFor="station">الصندوق</Label>
                    <select
                        id="station"
                        className="h-10 rounded-md border bg-card px-3"
                        value={station}
                        onChange={(e) => {
                            setStation(e.target.value);
                            setMatches([]);
                            setError('');
                            scanner.current?.focus();
                        }}
                    >
                        <option value="" disabled>
                            اختر الصندوق
                        </option>
                        {stations.map((item) => (
                            <option value={item.id} key={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            <p className="rounded-md border bg-card p-3 text-sm text-muted-foreground">
                مرحلة التجهيز: يمكنك تكوين السلة ومعاينة الحساب. حفظ البيع
                والدفع وإصدار الفاتورة لم تُفعّل بعد.
            </p>
            {!station && (
                <p role="alert" className="text-destructive">
                    لا يوجد صندوق متاح. اطلب من المسؤول تهيئة الصناديق.
                </p>
            )}
            {storageError && (
                <p role="alert" className="text-destructive">
                    تعذر حفظ السلة في المتصفح. احتفظ بهذه الصفحة مفتوحة.
                </p>
            )}
            {(error || quoteError) && (
                <p
                    role="alert"
                    className="rounded-md border border-destructive p-3 text-destructive"
                >
                    {error || quoteError}
                </p>
            )}
            {quoteError && (
                <Button type="button" variant="outline" onClick={retry}>
                    إعادة حساب الإجمالي
                </Button>
            )}
            <div className="grid items-start gap-4 lg:grid-cols-[1fr_20rem]">
                <div className="space-y-4">
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <form
                                onSubmit={(e) => {
                                    void scan(e);
                                }}
                                className="flex items-end gap-2"
                            >
                                <div className="flex-1 space-y-2">
                                    <Label htmlFor="barcode">
                                        مسح الباركود
                                    </Label>
                                    <Input
                                        id="barcode"
                                        ref={scanner}
                                        autoFocus
                                        dir="ltr"
                                        autoComplete="off"
                                        maxLength={64}
                                        value={barcode}
                                        onChange={(e) =>
                                            setBarcode(e.target.value)
                                        }
                                        placeholder="امسح الصنف ثم اضغط Enter"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={!station || !barcode.trim()}
                                >
                                    إضافة
                                </Button>
                            </form>
                            <form
                                onSubmit={(e) => {
                                    void find(e);
                                }}
                                className="flex items-end gap-2"
                            >
                                <div className="flex-1">
                                    <FormField
                                        label="البحث باسم الصنف"
                                        minLength={2}
                                        maxLength={60}
                                        required
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={searching || !station}
                                >
                                    {searching ? 'جارٍ البحث…' : 'بحث'}
                                </Button>
                            </form>
                            {matches.length > 0 && (
                                <ul
                                    aria-label="نتائج البحث"
                                    className="space-y-2"
                                >
                                    {matches.map((product) => (
                                        <li key={product.id}>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="h-auto w-full justify-between p-3"
                                                onClick={() => add(product)}
                                            >
                                                <span>
                                                    {product.name} —{' '}
                                                    {product.unit}
                                                </span>
                                                <Money amount={product.price} />
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>السلة ({basket.length})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {basket.length === 0 ? (
                                <p className="py-8 text-center text-muted-foreground">
                                    امسح الباركود أو ابحث عن صنف لإضافته.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-start text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="p-2 text-start">
                                                    الصنف
                                                </th>
                                                <th className="p-2 text-start">
                                                    السعر
                                                </th>
                                                <th className="p-2 text-start">
                                                    الكمية
                                                </th>
                                                <th className="p-2 text-start">
                                                    الإجمالي
                                                </th>
                                                <th className="p-2">
                                                    <span className="sr-only">
                                                        حذف
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {basket.map((line) => {
                                                const amounts =
                                                    quote?.lines.find(
                                                        (item) =>
                                                            item.product_id ===
                                                            line.product.id,
                                                    );
                                                return (
                                                    <tr
                                                        key={line.product.id}
                                                        className="border-b"
                                                    >
                                                        <td className="p-2">
                                                            <p className="font-medium">
                                                                {
                                                                    line.product
                                                                        .name
                                                                }
                                                            </p>
                                                            <bdi
                                                                dir="ltr"
                                                                className="text-xs text-muted-foreground"
                                                            >
                                                                {
                                                                    line.product
                                                                        .barcode
                                                                }
                                                            </bdi>
                                                        </td>
                                                        <td className="p-2">
                                                            <Money
                                                                amount={
                                                                    amounts?.unit_price ??
                                                                    line.product
                                                                        .price
                                                                }
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            <Input
                                                                type="number"
                                                                dir="ltr"
                                                                aria-label={`كمية ${line.product.name}`}
                                                                className="w-24"
                                                                min="0.001"
                                                                max="10000"
                                                                step="0.001"
                                                                value={
                                                                    line.quantity
                                                                }
                                                                onChange={(e) =>
                                                                    changeBasket(
                                                                        (
                                                                            current,
                                                                        ) =>
                                                                            current.map(
                                                                                (
                                                                                    item,
                                                                                ) =>
                                                                                    item
                                                                                        .product
                                                                                        .id ===
                                                                                    line
                                                                                        .product
                                                                                        .id
                                                                                        ? {
                                                                                              ...item,
                                                                                              quantity:
                                                                                                  e
                                                                                                      .target
                                                                                                      .value,
                                                                                          }
                                                                                        : item,
                                                                            ),
                                                                    )
                                                                }
                                                            />
                                                        </td>
                                                        <td className="p-2">
                                                            {amounts ? (
                                                                <Money
                                                                    amount={
                                                                        amounts.total
                                                                    }
                                                                />
                                                            ) : (
                                                                '—'
                                                            )}
                                                        </td>
                                                        <td className="p-2">
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                aria-label={`حذف ${line.product.name}`}
                                                                onClick={() =>
                                                                    changeBasket(
                                                                        (
                                                                            current,
                                                                        ) =>
                                                                            current.filter(
                                                                                (
                                                                                    item,
                                                                                ) =>
                                                                                    item
                                                                                        .product
                                                                                        .id !==
                                                                                    line
                                                                                        .product
                                                                                        .id,
                                                                            ),
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 />
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
                <Card className="lg:sticky lg:top-4">
                    <CardHeader>
                        <CardTitle>ملخص الحساب</CardTitle>
                    </CardHeader>
                    <CardContent
                        className="space-y-4"
                        aria-live="polite"
                        aria-busy={pending}
                    >
                        <dl className="space-y-3">
                            {(
                                [
                                    ['net', 'قبل الضريبة'],
                                    ['tax', 'الضريبة'],
                                    ['total', 'الإجمالي'],
                                ] as const
                            ).map(([key, label]) => (
                                <div
                                    key={key}
                                    className={`flex justify-between gap-2 ${key === 'total' ? 'border-t pt-4 text-xl font-bold' : 'text-sm'}`}
                                >
                                    <dt>{label}</dt>
                                    <dd>
                                        {quote ? (
                                            <Money amount={quote.totals[key]} />
                                        ) : basket.length === 0 ? (
                                            <Money amount="0.00" />
                                        ) : (
                                            '—'
                                        )}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        {pending && (
                            <p
                                className="text-sm text-muted-foreground"
                                role="status"
                            >
                                جارٍ حساب الإجمالي…
                            </p>
                        )}
                        <Button disabled className="w-full">
                            الدفع — قيد التطوير
                        </Button>
                        <p className="text-xs text-muted-foreground">
                            هذه معاينة للسلة؛ لا تنشئ عملية بيع ولا تغيّر المخزون.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
