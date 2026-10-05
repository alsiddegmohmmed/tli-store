import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { store } from '@/routes/login';

export default function Login() {
    const form = useForm({ email: '', password: '' });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store.url(), { onFinish: () => form.reset('password') });
    }
    return (
        <main className="flex min-h-screen items-center justify-center p-4">
            <Head title="تسجيل الدخول" />
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle className="text-xl">
                        تي إل آي — تسجيل الدخول
                    </CardTitle>
                    <p className="text-sm text-muted-foreground">
                        استخدم حساب الموظف للدخول إلى النظام.
                    </p>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-5">
                        <FormField
                            label="البريد الإلكتروني"
                            type="email"
                            dir="ltr"
                            autoComplete="username"
                            autoFocus
                            required
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            error={form.errors.email}
                        />
                        <FormField
                            label="كلمة المرور"
                            type="password"
                            dir="ltr"
                            autoComplete="current-password"
                            required
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            error={form.errors.password}
                        />
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={form.processing}
                        >
                            {form.processing ? 'جارٍ الدخول…' : 'تسجيل الدخول'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </main>
    );
}
