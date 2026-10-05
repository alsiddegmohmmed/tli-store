<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email', 'role']),
                'canManageProducts' => $request->user()?->can('manage-products') ?? false,
                'canManageCompany' => $request->user()?->can('manage-company') ?? false,
            ],
            'flash' => ['success' => fn () => $request->session()->get('success')],
        ];
    }
}
