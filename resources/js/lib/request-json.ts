export async function requestJson<T>(
    url: string,
    options: RequestInit = {},
): Promise<T> {
    const xsrf = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    headers.set('Content-Type', 'application/json');
    if (xsrf) headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrf));
    let response: Response;
    try {
        response = await fetch(url, {
            ...options,
            credentials: 'same-origin',
            headers,
        });
    } catch (error) {
        if (options.signal?.aborted) throw error;
        throw new Error('تعذر الاتصال بالنظام. تحقق من الشبكة وحاول مرة أخرى.');
    }
    if (response.status === 401 || response.status === 419) {
        throw new Error(
            'انتهت الجلسة. سجّل الدخول مجددًا؛ السلة محفوظة في هذا المتصفح.',
        );
    }
    if (response.status >= 500)
        throw new Error('تعذر الاتصال بالنظام. حاول مرة أخرى.');
    const data = await response.json().catch(() => {
        throw new Error('تعذر قراءة استجابة النظام. حاول مرة أخرى.');
    });
    if (!response.ok) {
        const message =
            Object.values(data.errors ?? {}).flat()[0] ?? data.message;
        throw new Error(
            typeof message === 'string'
                ? message
                : 'تعذر تنفيذ الطلب. راجع البيانات وحاول مرة أخرى.',
        );
    }
    return data as T;
}
