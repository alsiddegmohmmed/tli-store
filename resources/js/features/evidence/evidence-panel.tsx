import { useForm } from '@inertiajs/react';
import { useId, useRef } from 'react';
import DateTime from '@/components/date-time';
import FormField from '@/components/form-field';
import Pagination from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { download } from '@/routes/evidence';

export type Evidence = {
    id: number;
    kind: string;
    original_name: string;
    paper_location: string | null;
    size: number;
    recorded_at: string;
    author: { name: string };
};

export default function EvidencePanel({
    files,
    kinds,
    uploadUrl,
}: {
    files: Paginated<Evidence>;
    kinds: Record<string, string>;
    uploadUrl: string;
}) {
    const id = useId();
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{
        file: File | null;
        kind: string;
        paper_location: string;
    }>({ file: null, kind: Object.keys(kinds)[0], paper_location: '' });
    return (
        <Card>
            <CardHeader>
                <CardTitle>المرفقات والإثباتات ({files.total})</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    PDF أو JPG أو PNG بحد أقصى 2 ميغابايت. المرفقات خاصة، وإضافة
                    ملف لا تغير المبالغ أو تثبت أهلية خصم الضريبة. تكرار نفس
                    الملف يستعيد المرفق الأصلي وبياناته.
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(uploadUrl, {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset('file', 'paper_location');
                                if (fileInput.current)
                                    fileInput.current.value = '';
                            },
                        });
                    }}
                >
                    <fieldset
                        disabled={form.processing}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <div className="space-y-2">
                            <Label htmlFor={`${id}-file`}>الملف</Label>
                            <input
                                ref={fileInput}
                                id={`${id}-file`}
                                type="file"
                                required
                                accept="application/pdf,image/jpeg,image/png"
                                className="w-full rounded-md border p-2"
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                aria-invalid={!!form.errors.file}
                                aria-describedby={
                                    form.errors.file
                                        ? `${id}-file-error`
                                        : undefined
                                }
                            />
                            {form.errors.file && (
                                <p
                                    id={`${id}-file-error`}
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {form.errors.file}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={`${id}-kind`}>نوع الإثبات</Label>
                            <select
                                id={`${id}-kind`}
                                className="h-10 w-full rounded-md border bg-card px-3"
                                value={form.data.kind}
                                onChange={(event) =>
                                    form.setData('kind', event.target.value)
                                }
                            >
                                {Object.entries(kinds).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            {form.errors.kind && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {form.errors.kind}
                                </p>
                            )}
                        </div>
                        <FormField
                            label="مكان حفظ الأصل الورقي (اختياري)"
                            maxLength={200}
                            value={form.data.paper_location}
                            onChange={(event) =>
                                form.setData(
                                    'paper_location',
                                    event.target.value,
                                )
                            }
                            error={form.errors.paper_location}
                        />
                        <Button type="submit">
                            {form.processing
                                ? 'جارٍ رفع الملف…'
                                : 'إضافة المرفق'}
                        </Button>
                    </fieldset>
                    {form.progress && (
                        <p role="status">
                            جارٍ رفع الملف… {form.progress.percentage}%
                        </p>
                    )}
                </form>
                {files.data.length === 0 && (
                    <p className="text-muted-foreground">
                        لم تُرفق ملفات لهذا السجل.
                    </p>
                )}
                <ul className="space-y-3">
                    {files.data.map((file) => (
                        <li
                            key={file.id}
                            className="space-y-1 rounded-md border p-3"
                        >
                            <a
                                href={download.url(file.id)}
                                className="break-all underline"
                            >
                                تنزيل المرفق {file.id} —{' '}
                                <bdi>{file.original_name}</bdi>
                            </a>
                            <p className="text-sm">
                                {kinds[file.kind]} — {file.author.name} —{' '}
                                <DateTime value={file.recorded_at} />
                            </p>
                            <p className="text-sm text-muted-foreground">
                                مكان الأصل:{' '}
                                {file.paper_location ?? 'غير مسجل لدينا'}
                            </p>
                        </li>
                    ))}
                </ul>
                <Pagination page={files} label="صفحات المرفقات" />
            </CardContent>
        </Card>
    );
}
