import { createInertiaApp } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

void createInertiaApp({
    title: (title) => (title ? `${title} — تي إل آي` : 'تي إل آي'),
    layout: (name) =>
        name.startsWith('auth/') || name === 'sales/receipt' ? null : AppLayout,
    strictMode: true,
});
