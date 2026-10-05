import { useRef, useState } from 'react';
import { RequestError, requestJson } from '@/lib/request-json';

type Attempt<Input> = { operation_id: string; input: Input };
type Options<Input, Result> = {
    storageKey: string;
    url: string;
    validateInput: (input: Input) => boolean;
    onConfirmed: (result: Result) => void;
};
function restore<Input>(
    key: string,
    validate: Options<Input, unknown>['validateInput'],
): { pending: Attempt<Input> | null; error: string } {
    try {
        const stored = sessionStorage.getItem(key);
        if (!stored) return { pending: null, error: '' };
        const value = JSON.parse(stored) as Attempt<Input>;
        if (
            typeof value.operation_id !== 'string' ||
            !value.input ||
            !validate(value.input)
        )
            throw new Error();
        return { pending: value, error: '' };
    } catch {
        return {
            pending: null,
            error: 'تعذر استعادة محاولة الحفظ. راجع المسؤول والسجل قبل إنشاء عملية جديدة.',
        };
    }
}

// Persist the exact attempt before sending; uncertain responses retain its identity.
export function useRecordedSubmission<Input, Result>({
    storageKey,
    url,
    validateInput,
    onConfirmed,
}: Options<Input, Result>) {
    const [initial] = useState(() => restore(storageKey, validateInput));
    const [pending, setPending] = useState(initial.pending);
    const [error, setError] = useState(initial.error);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState<Result | null>(null);
    const inFlight = useRef(false);
    const blocked = initial.error !== '';

    async function send(attempt: Attempt<Input>) {
        if (inFlight.current || blocked) return;
        inFlight.current = true;
        setSaving(true);
        setError('');
        let committed = false;
        try {
            const result = await requestJson<Result>(url, {
                method: 'POST',
                body: JSON.stringify({
                    operation_id: attempt.operation_id,
                    ...attempt.input,
                }),
            });
            committed = true;
            // Apply local recovery effects before discarding the operation identity.
            onConfirmed(result);
            sessionStorage.removeItem(storageKey);
            setPending(null);
            setSaved(result);
        } catch (failure) {
            if (
                !committed &&
                failure instanceof RequestError &&
                failure.status === 422
            ) {
                try {
                    sessionStorage.removeItem(storageKey);
                    setPending(null);
                } catch {
                    /* Retain recovery identity. */
                }
            }
            setError(
                committed
                    ? 'حُفظت العملية لكن تعذر تحديث الصفحة. استعد نفس المحاولة؛ لن تنشأ عملية أخرى.'
                    : failure instanceof Error
                      ? failure.message
                      : 'تعذر معرفة نتيجة الحفظ. استعد نفس المحاولة.',
            );
        } finally {
            inFlight.current = false;
            setSaving(false);
        }
    }
    async function submit(input: Input) {
        if (inFlight.current || blocked || pending) return;
        const attempt = { operation_id: crypto.randomUUID(), input };
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(attempt));
        } catch {
            setError('تعذر حفظ هوية المحاولة في المتصفح. لم تُرسل العملية.');
            return;
        }
        setPending(attempt);
        setSaved(null);
        await send(attempt);
    }
    return {
        pending,
        error,
        saving,
        saved,
        blocked,
        submit,
        retry: () => (pending ? send(pending) : Promise.resolve()),
    };
}
