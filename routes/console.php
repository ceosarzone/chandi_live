<?php

use App\Jobs\RefreshMarket;
use App\Models\AnalyticsEvent;
use App\Models\City;
use App\Models\DealerProfile;
use App\Models\MarketDataProvider;
use App\Models\ProviderRequest;
use App\Models\Role;
use App\Models\SponsoredPlacement;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Audit;
use App\Services\Market\DemoProvider;
use App\Services\Market\FetchMarket;
use App\Services\Market\GoldToday;
use App\Services\Market\ReferenceHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('chandi:admin {email} {--name=Administrator}', function () {
    if (! filter_var($this->argument('email'), FILTER_VALIDATE_EMAIL)) {
        return $this->error('Invalid email.');
    }
    $password = $this->secret('New administrator password (minimum 12 characters)');
    if (strlen($password) < 12) {
        return $this->error('Password must contain at least 12 characters.');
    }if (User::where('email', $this->argument('email'))->exists()) {
        return $this->error('Account exists. Use an authorized administrator to manage access.');
    }
    $u = User::create(['email' => $this->argument('email'), 'name' => $this->option('name'), 'password' => $password]);
    $u->forceFill(['role_id' => Role::where('name', 'super_admin')->value('id'), 'email_verified_at' => now()])->save();
    Audit::record('admin.bootstrapped', $u);
    $this->info('Administrator created. Password was not stored in plaintext.');
});
Artisan::command('market:fetch', function (FetchMarket $fetch) {
    $fetch->run();
    $this->info('Fetch processed. Review provider logs for outcome.');
});
Artisan::command('market:history {from} {to}', function (FetchMarket $fetch) {
    try {
        $from = Carbon::parse($this->argument('from'));
        $to = Carbon::parse($this->argument('to'));
        if ($from->gt($to) || $from->diffInDays($to) > 29 || $to->endOfDay()->isFuture()) {
            throw new RuntimeException;
        }
        $fetch->run($from->toDateString(), $to->toDateString());
        $this->info('History processed. Review provider logs.');
    } catch (Throwable $e) {
        $this->error('Use a past date window of no more than 30 days.');
    }
});
Artisan::command('chandi:demo {--email=dealer@example.test}', function (FetchMarket $fetch) {
    if (! app()->environment('local', 'testing') || ! config('market.demo')) {
        return $this->error('Requires local/testing environment and MARKET_DEMO=true.');
    }
    $p = MarketDataProvider::where('slug', 'demo')->firstOrFail();
    $p->update(['enabled' => true]);
    foreach (app(DemoProvider::class)->historical('', '') as $v) {
        $fetch->store($p, $v);
    }
    $u = User::where('email', $this->option('email'))->first();
    if (! $u) {
        $password = Str::random(20);
        $u = User::create(['name' => 'Development Dealer', 'email' => $this->option('email'), 'password' => $password]);
        $u->forceFill(['role_id' => Role::where('name', 'dealer')->value('id'), 'email_verified_at' => now()])->save();
        $d = DealerProfile::create(['user_id' => $u->id, 'city_id' => City::where('slug', 'lahore')->value('id'), 'business_name' => 'DEMO · Silver Studio', 'slug' => 'demo-silver-studio', 'representative' => 'Fictional development dealer', 'phone' => '+923000000000', 'address' => 'Fictional location, not a real shop', 'description' => 'This is a fictional development dealer. It cannot fulfil orders. Use this account to explore local workflows.', 'status' => 'approved', 'verified' => false]);
        $this->info('Development dealer: '.$u->email);
        $this->info('Generated one-time password: '.$password);
    }
    $this->info('Deterministic fixture data installed, explicitly marked demo. No badge granted.');
});
Artisan::command('chandi:expire', function () {
    Subscription::where('status', 'active')->where('expires_at', '<=', now())->update(['status' => 'expired']);
    SponsoredPlacement::where('expires_at', '<=', now())->update(['active' => false]);
    $this->info('Expired entitlements updated.');
});
Artisan::command('chandi:prune', function () {
    AnalyticsEvent::where('created_at', '<', now()->subDays(90))->delete();
    ProviderRequest::where('created_at', '<', now()->subMonths(12))->delete();
    $this->info('Privacy analytics and operational request retention applied.');
});
Artisan::command('chandi:totp-reset {email}', function () {
    if (! $this->confirm('Reset administrator TOTP after independently verifying account ownership?')) {
        return;
    }
    $u = User::where('email', $this->argument('email'))->firstOrFail();
    $u->forceFill(['totp_enabled' => false, 'totp_secret' => null, 'totp_last_step' => null])->save();
    Audit::record('security.totp_cli_reset', $u);
    $this->info('TOTP reset.');
});
Schedule::job(new RefreshMarket)->everyMinute()->withoutOverlapping();
Schedule::command('chandi:expire')->hourly()->withoutOverlapping();
Schedule::command('chandi:prune')->daily()->withoutOverlapping();

Artisan::command('market:backfill {from} {to}', function (ReferenceHistory $history) {
    try {
        $result = $history->run($this->argument('from'), $this->argument('to'));
        $this->info(json_encode($result));

        return $result['failed'] ? 1 : 0;
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return 1;
    }
});
Artisan::command('market:references', function (GoldToday $psl, ReferenceHistory $history) {
    $psl->run();
    Cache::lock('market.archive.lock', 300)->get(function () use ($history) {
        if (Cache::has('market.archive.checked')) {
            return;
        }
        $result = $history->run(now('UTC')->subDays(7)->toDateString(), now('UTC')->subDay()->toDateString());
        if (! $result['failed']) {
            Cache::put('market.archive.checked', true, 86400);
        }
    });
    $this->info('Reference feeds checked.');
});
