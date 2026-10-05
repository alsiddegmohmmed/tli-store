import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export default function Pagination({
    page,
    label,
}: {
    page: Omit<Paginated<unknown>, 'data'>;
    label: string;
}) {
    return (
        <nav aria-label={label} className="flex items-center gap-3">
            {page.prev_page_url && (
                <Button asChild variant="outline">
                    <Link href={page.prev_page_url}>السابق</Link>
                </Button>
            )}
            <span className="text-sm text-muted-foreground">
                صفحة {page.current_page} من {page.last_page}
            </span>
            {page.next_page_url && (
                <Button asChild variant="outline">
                    <Link href={page.next_page_url}>التالي</Link>
                </Button>
            )}
        </nav>
    );
}
