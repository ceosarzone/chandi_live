<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MarketOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('chandi.marketplace_enabled') && $request->is('register', 'become-a-dealer', 'dealers', 'dealers/*', 'products', 'products/*', 'dealer', 'dealer/*', 'dealer-policy', 'advertise', 'enquiries', 'whatsapp/*', 'contact-dealer/*', 'leads/*', 'silver-rate/*')) {
            abort(404);
        }

        return $next($request);
    }
}
