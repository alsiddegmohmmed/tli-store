import { Head } from '@inertiajs/react';
import type { SaleSummary } from '@/types/store';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Card, CardContent } from '@/components/ui/card';
import SalesTable from '@/features/sales/sales-table';

export default function Sales({ sales }: { sales: Paginated<SaleSummary> }) {
    return (
        <>
            <Head title="المبيعات" />
            <h1 className="text-2xl font-bold">المبيعات</h1>
            <p className="text-sm text-muted-foreground">
                سجلات بيع داخلية. لم تصدر فواتير رسمية لهذه العمليات.
            </p>
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <SalesTable sales={sales.data} />
                    <Pagination page={sales} label="صفحات المبيعات" />
                </CardContent>
            </Card>
        </>
    );
}
