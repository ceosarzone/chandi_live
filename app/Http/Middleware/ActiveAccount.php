<?php

namespace App\Http\Middleware;

use Closure;

class ActiveAccount
{
    public function handle($r, Closure $next)
    {
        if ($r->user() && ! $r->user()->active) {
            auth()->logout();
            $r->session()->invalidate();
            abort(403, 'Account inactive.');
        }

return $next($r);
    }
}
