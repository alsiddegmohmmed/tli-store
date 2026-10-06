import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function RecordedSubmissionFeedback({
    error,
    pending,
    saving,
    retry,
    children,
}: {
    error: string;
    pending: boolean;
    saving: boolean;
    retry: () => void;
    children?: ReactNode;
}) {
    return (
        <>
            {error && (
                <p role="alert" className="text-destructive">
                    {error}
                </p>
            )}
            {pending && (
                <Card>
                    <CardContent className="space-y-3 pt-6">
                        <p role="status">
                            {saving
                                ? 'جارٍ الحفظ…'
                                : 'نتيجة الحفظ تحتاج استعادة نفس المحاولة. لا تنشئ عملية ثانية.'}
                        </p>
                        {children}
                        <Button disabled={saving} onClick={retry}>
                            استعادة نفس المحاولة
                        </Button>
                    </CardContent>
                </Card>
            )}
        </>
    );
}
