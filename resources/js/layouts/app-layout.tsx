import { index as shiftsIndex } from '@/routes/shifts';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { index as productsIndex } from '@/routes/products';
import { index as posIndex } from '@/routes/pos';
import { index as salesIndex } from '@/routes/sales';
import { index as stockIndex } from '@/routes/stock';
import { logout } from '@/routes';
import { edit as companyEdit } from '@/routes/company';
import { index as suppliersIndex } from '@/routes/suppliers';
import type { SharedProps } from '@/types/store';

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage<SharedProps>().props;
    const path = usePage().url.split('?')[0];
    return (
        <div className="min-h-screen">
            <header className="border-b bg-card">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-4 py-4">
                    <span className="text-lg font-bold">تي إل آي</span>
                    <nav
                        aria-label="التنقل الرئيسي"
                        className="flex flex-wrap gap-2"
                    >
                        <Button
                            asChild
                            variant={path === '/pos' ? 'default' : 'ghost'}
                        >
                            <Link
                                href={posIndex()}
                                aria-current={
                                    path === '/pos' ? 'page' : undefined
                                }
                            >
                                نقطة البيع
                            </Link>
                        </Button>
                        <Button
                            asChild
                            variant={
                                path.startsWith('/sales') ? 'default' : 'ghost'
                            }
                        >
                            <Link
                                href={salesIndex()}
                                aria-current={
                                    path.startsWith('/sales')
                                        ? 'page'
                                        : undefined
                                }
                            >
                                المبيعات
                            </Link>
                        </Button>
                        <Button
                            asChild
                            variant={
                                path.startsWith('/shifts') ? 'default' : 'ghost'
                            }
                        >
                            <Link
                                href={shiftsIndex()}
                                aria-current={
                                    path.startsWith('/shifts')
                                        ? 'page'
                                        : undefined
                                }
                            >
                                الورديات
                            </Link>
                        </Button>
                        {auth.canManageProducts && (
                            <Button
                                asChild
                                variant={
                                    path === '/stock' ? 'default' : 'ghost'
                                }
                            >
                                <Link
                                    href={stockIndex()}
                                    aria-current={
                                        path === '/stock' ? 'page' : undefined
                                    }
                                >
                                    المخزون
                                </Link>
                            </Button>
                        )}
                        <Button
                            asChild
                            variant={path === '/products' ? 'default' : 'ghost'}
                        >
                            <Link
                                href={productsIndex()}
                                aria-current={
                                    path === '/products' ? 'page' : undefined
                                }
                            >
                                الأصناف
                            </Link>
                        </Button>
                        {auth.canManagePurchases && (
                            <Button
                                asChild
                                variant={
                                    path.startsWith('/suppliers')
                                        ? 'default'
                                        : 'ghost'
                                }
                            >
                                <Link
                                    href={suppliersIndex()}
                                    aria-current={
                                        path.startsWith('/suppliers')
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    الموردون
                                </Link>
                            </Button>
                        )}
                        {auth.canManageCompany && (
                            <Button
                                asChild
                                variant={
                                    path === '/company' ? 'default' : 'ghost'
                                }
                            >
                                <Link
                                    href={companyEdit()}
                                    aria-current={
                                        path === '/company' ? 'page' : undefined
                                    }
                                >
                                    الشركة
                                </Link>
                            </Button>
                        )}
                    </nav>
                    <span className="ms-auto text-sm text-muted-foreground">
                        {auth.user.name}
                    </span>
                    <Button asChild variant="outline">
                        <Link href={logout()} as="button">
                            تسجيل الخروج
                        </Link>
                    </Button>
                </div>
            </header>
            <main className="mx-auto max-w-7xl space-y-4 px-4 py-6">
                {flash.success && (
                    <p
                        role="status"
                        className="rounded-md border border-success/30 bg-card p-3 text-success"
                    >
                        {flash.success}
                    </p>
                )}
                {children}
            </main>
        </div>
    );
}
