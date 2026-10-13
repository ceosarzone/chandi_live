<?php

namespace App\Http\Middleware;

use App\Services\Analytics;
use Closure;

class PublicActivity
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if ($request->method() === 'GET' && $response->getStatusCode() === 200 && ! $request->is('admin*', 'dealer', 'dealer/*', 'dashboard', 'profile', 'account*', 'alerts*', 'login', 'register', 'forgot-password', 'reset-password/*', 'verify-email*', 'confirm-password', 'market*', 'health', 'up', 'robots.txt', 'sitemap.xml', 'livewire*', 'documents*')) {
            app(Analytics::class)->track('platform_visit');
        }

return $response;
    }
}
