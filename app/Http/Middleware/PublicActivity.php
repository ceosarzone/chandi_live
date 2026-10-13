<?php

namespace App\Http\Middleware;

use App\Services\Analytics;
use Closure;

class PublicActivity
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if ($request->method() === 'GET' && $response->getStatusCode() === 200
            && str_contains($response->headers->get('Content-Type', ''), 'text/html')
            && ! preg_match('/bot|crawler|spider|slurp|headless|monitor|uptime/i', $request->userAgent() ?? '')
            && ! $request->is('admin*', 'dealer', 'dealer/*', 'dashboard', 'profile', 'account*', 'alerts*', 'login', 'register', 'forgot-password', 'reset-password/*', 'verify-email*', 'confirm-password', 'market*', 'health', 'up', 'robots.txt', 'sitemap.xml', 'livewire*', 'documents*')) {
            app(Analytics::class)->track('platform_visit');
        }

        return $response;
    }
}
