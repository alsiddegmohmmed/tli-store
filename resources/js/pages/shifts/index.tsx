import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DateTime from '@/components/date-time';
import Money from '@/components/money';
import FormField from '@/components/form-field';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { useRecordedSubmission } from '@/hooks/use-recorded-submission';
import { store, show } from '@/routes/shifts';
import { index as posIndex } from '@/routes/pos';
import type { CashierShift, SharedProps, Station } from '@/types/store';

type OpeningInput = { station_id: number; opening_cash: string };
export default function Shifts({
    stations,
    shifts,
    activeShift,
}: {
    stations: Station[];
    shifts: Paginated<CashierShift>;
    activeShift: CashierShift | null;
}) {
    const { auth } = usePage<SharedProps>().props;
    const [station, setStation] = useState(String(stations[0]?.id ?? ''));
    const [cash, setCash] = useState('0.00');
    const opening = useRecordedSubmission<OpeningInput, { url: string }>({
        storageKey: `tli:shift-opening:${auth.user.id}`,
        url: store.url(),
        validateInput: (input) =>
            Number.isInteger(input.station_id) &&
            typeof input.opening_cash === 'string',
        onConfirmed: (result) => router.visit(result.url),
    });
    return (
        <>
            <Head title="الورديات" />
            <h1 className="text-2xl font-bold">الورديات</h1>
            <p className="text-sm text-muted-foreground">
                وردية واحدة لكل كاشير وصندوق. رصيد البداية نقد معدود للمطابقة؛
                ليس إيرادًا ولا تحويلًا ماليًا.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>
                        {activeShift ? 'وردية مفتوحة' : 'فتح وردية'}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    {opening.error && (
                        <p role="alert" className="text-destructive">
                            {opening.error}
                        </p>
                    )}
                    {opening.pending && (
                        <div className="space-y-3">
                            <p role="status">
                                {opening.saving
                                    ? 'جارٍ حفظ الوردية…'
                                    : 'محاولة الفتح محفوظة. استعد نتيجتها قبل فتح وردية جديدة.'}
                            </p>
                            <p className="text-sm">
                                رقم المحاولة:{' '}
                                <bdi dir="ltr">
                                    {opening.pending.operation_id}
                                </bdi>
                            </p>
                            <Button
                                disabled={opening.saving}
                                onClick={() => void opening.retry()}
                            >
                                استعادة نفس محاولة الفتح
                            </Button>
                        </div>
                    )}
                    {activeShift ? (
                        <div className="flex flex-wrap items-center gap-3">
                            <span>
                                {activeShift.station.name} — الوردية{' '}
                                {activeShift.id}
                            </span>
                            <Button asChild>
                                <Link href={posIndex()}>متابعة البيع</Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={show(activeShift.id)}>
                                    المطابقة والإغلاق
                                </Link>
                            </Button>
                        </div>
                    ) : (
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                void opening.submit({
                                    station_id: Number(station),
                                    opening_cash: cash,
                                });
                            }}
                        >
                            <fieldset
                                disabled={
                                    opening.saving ||
                                    !!opening.pending ||
                                    opening.blocked
                                }
                                className="grid gap-4 sm:max-w-lg"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="opening-station">
                                        الصندوق
                                    </Label>
                                    <select
                                        id="opening-station"
                                        required
                                        className="h-10 w-full rounded-md border bg-card px-3"
                                        value={station}
                                        onChange={(event) =>
                                            setStation(event.target.value)
                                        }
                                    >
                                        <option value="" disabled>
                                            اختر الصندوق
                                        </option>
                                        {stations.map((item) => (
                                            <option
                                                key={item.id}
                                                value={item.id}
                                            >
                                                {item.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <FormField
                                    label="النقد المعدود عند البداية"
                                    type="number"
                                    dir="ltr"
                                    min="0"
                                    step="0.01"
                                    required
                                    value={cash}
                                    onChange={(event) =>
                                        setCash(event.target.value)
                                    }
                                />
                                <Button disabled={!station} type="submit">
                                    فتح الوردية
                                </Button>
                            </fieldset>
                        </form>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>سجل الورديات</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="overflow-x-auto">
                        <table className="w-full text-start text-sm">
                            <thead>
                                <tr className="border-b">
                                    {[
                                        'الوردية',
                                        'الكاشير',
                                        'الصندوق',
                                        'البداية',
                                        'النقد عند البداية',
                                        'الحالة',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            className="p-3 text-start"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {shifts.data.map((shift) => (
                                    <tr key={shift.id} className="border-b">
                                        <td className="p-3">
                                            <Link
                                                href={show(shift.id)}
                                                className="underline"
                                            >
                                                فتح الوردية {shift.id}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            {shift.cashier.name}
                                        </td>
                                        <td className="p-3">
                                            {shift.station.name}
                                        </td>
                                        <td className="p-3">
                                            <DateTime value={shift.opened_at} />
                                        </td>
                                        <td className="p-3">
                                            <Money
                                                amount={shift.opening_cash}
                                            />
                                        </td>
                                        <td className="p-3">
                                            {shift.closed_at
                                                ? 'مغلقة'
                                                : 'مفتوحة'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {shifts.data.length === 0 && (
                        <p className="text-muted-foreground">
                            لا توجد ورديات بعد.
                        </p>
                    )}
                    <Pagination page={shifts} label="صفحات الورديات" />
                </CardContent>
            </Card>
        </>
    );
}
