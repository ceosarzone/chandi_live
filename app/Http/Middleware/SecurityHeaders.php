<?php

namespace App\Http\Middleware;

use Closure;

class SecurityHeaders
{
    public function handle($r, Closure $next)
    {
        $response = $next($r);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($r->is('admin*', 'dealer*', 'dashboard*', 'account*', 'alerts*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

return $response;
    }
}
