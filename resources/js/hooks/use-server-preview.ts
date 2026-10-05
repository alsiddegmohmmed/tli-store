import { useEffect, useState } from 'react';
import { requestJson } from '@/lib/request-json';

export function useServerPreview<T>(
    url: string,
    payload: string,
    enabled: boolean,
) {
    const key = `${url}:${payload}`;
    const [result, setResult] = useState<{
        key: string;
        data?: T;
        error?: string;
    } | null>(null);
    const [attempt, setAttempt] = useState(0);
    useEffect(() => {
        if (!enabled) return;
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void requestJson<T>(url, {
                method: 'POST',
                body: payload,
                signal: controller.signal,
            })
                .then((data) => {
                    if (!controller.signal.aborted) setResult({ key, data });
                })
                .catch((error: unknown) => {
                    if (!controller.signal.aborted)
                        setResult({
                            key,
                            error:
                                error instanceof Error
                                    ? error.message
                                    : 'تعذر حساب المعاينة.',
                        });
                });
        }, 180);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [url, payload, key, enabled, attempt]);
    const current = enabled && result?.key === key ? result : null;
    return {
        data: current?.data,
        error: current?.error,
        pending: enabled && !current,
        retry: () => {
            setResult(null);
            setAttempt((value) => value + 1);
        },
    };
}
