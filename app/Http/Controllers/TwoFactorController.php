<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function setup(Request $r, Google2FA $totp)
    {
        abort_unless($r->user()->canManage('admin'), 403);
        if (! $r->session()->has('totp_pending')) {
            $r->session()->put('totp_pending', $totp->generateSecretKey());
        }

        return view('account.two-factor', ['secret' => $r->session()->get('totp_pending'), 'challenge' => false]);
    }

    public function enable(Request $r, Google2FA $totp)
    {
        abort_unless($r->user()->canManage('admin'), 403);
        $r->validate(['code' => 'required|digits:6', 'password' => 'required|current_password']);
        $secret = $r->session()->get('totp_pending');
        if (! $secret || ! $totp->verifyKey($secret, $r->code)) {
            throw ValidationException::withMessages(['code' => 'Invalid authenticator code.']);
        }
        $r->user()->forceFill(['totp_secret' => $secret, 'totp_enabled' => true, 'totp_last_step' => (int) floor(time() / 30)])->save();
        $r->session()->forget('totp_pending');
        $r->session()->put('totp_user', $r->user()->id);
        Audit::record('security.totp_enabled', $r->user());

        return redirect('/admin')->with('status', 'Two-factor authentication enabled.');
    }

    public function challenge()
    {
        return view('account.two-factor', ['secret' => null, 'challenge' => true]);
    }

    public function verify(Request $r, Google2FA $totp)
    {
        $r->validate(['code' => 'required|digits:6']);
        $u = $r->user();
        abort_unless($u->canManage('admin') && $u->totp_enabled && $u->totp_secret, 403);
        DB::transaction(function () use ($u, $r, $totp) {
            $locked = User::lockForUpdate()->findOrFail($u->id);
            $step = $totp->verifyKeyNewer($locked->totp_secret, $r->code, $locked->totp_last_step ?? 0, 1);
            if (! $step) {
                throw ValidationException::withMessages(['code' => 'Invalid or already used authenticator code.']);
            }$locked->forceFill(['totp_last_step' => $step])->save();
        });
        $r->session()->put('totp_user', $u->id);
        $r->session()->regenerate();

        return redirect('/admin');
    }

    public function disable(Request $r)
    {
        $r->validate(['password' => 'required|current_password']);
        abort_unless(! $r->user()->totp_enabled || $r->session()->get('totp_user') === $r->user()->id, 403);
        $r->user()->forceFill(['totp_enabled' => false, 'totp_secret' => null, 'totp_last_step' => null])->save();
        Audit::record('security.totp_disabled', $r->user());

        return back()->with('status', 'Two-factor authentication disabled.');
    }
}
