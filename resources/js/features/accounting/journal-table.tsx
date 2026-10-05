import Money from '@/components/money';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type JournalLine = { name: string; debit: string; credit: string };

export default function JournalTable({ lines }: { lines: JournalLine[] }) {
    if (lines.length === 0) return null;
    return (
        <Card>
            <CardHeader>
                <CardTitle>القيد المالي</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <table className="w-full text-start text-sm">
                        <thead>
                            <tr className="border-b">
                                {['الحساب', 'مدين', 'دائن'].map((label) => (
                                    <th key={label} className="p-3 text-start">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {lines.map((line, i) => (
                                <tr key={i} className="border-b">
                                    <td className="p-3">{line.name}</td>
                                    <td className="p-3">
                                        <Money amount={line.debit} />
                                    </td>
                                    <td className="p-3">
                                        <Money amount={line.credit} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
