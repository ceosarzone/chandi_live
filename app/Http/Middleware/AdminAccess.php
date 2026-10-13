<?php

namespace App\Http\Middleware;

use Closure;

class AdminAccess
{
    public function handle($r, Closure $next)
    {
        abort_unless($r->user()?->canManage('admin'), 403);
        if ($r->user()->totp_enabled && $r->session()->get('totp_user') !== $r->user()->id) {
            return redirect('/account/two-factor/challenge');
        }

return $next($r);
    }
}
