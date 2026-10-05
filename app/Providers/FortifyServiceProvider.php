<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => Inertia::render('auth/login'));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));
    }
}
