<?php

namespace App\Providers;

use App\Http\Middleware\ActiveAccount;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('manage', fn ($u, $permission) => $u->canManage($permission));
        RateLimiter::for('enquiries', fn ($r) => Limit::perHour(5)->by($r->ip()));
        RateLimiter::for('uploads', fn ($r) => Limit::perMinute(10)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('calculator', fn ($r) => Limit::perMinute(60)->by($r->ip()));
        RateLimiter::for('totp', fn ($r) => Limit::perMinute(5)->by($r->user()?->id ?? $r->ip()));
        Livewire::addPersistentMiddleware([ActiveAccount::class]);
    }
}
