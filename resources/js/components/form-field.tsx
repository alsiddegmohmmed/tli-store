import { useId } from 'react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function FormField({
    label,
    error,
    id,
    ...props
}: ComponentProps<typeof Input> & { label: string; error?: string }) {
    const generatedId = useId();
    const inputId = id ?? generatedId;
    return (
        <div className="space-y-2">
            <Label htmlFor={inputId}>{label}</Label>
            <Input
                {...props}
                id={inputId}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${inputId}-error` : undefined}
            />
            {error && (
                <p
                    id={`${inputId}-error`}
                    className="text-sm text-destructive"
                    role="alert"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
