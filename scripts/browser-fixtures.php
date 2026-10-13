<?php

use App\Models\MarketDataProvider;
use App\Models\Role;
use App\Models\User;
use App\Services\Market\DemoProvider;
use App\Services\Market\FetchMarket;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

// Discards ONLY the explicitly dedicated test database. Never usable against the application database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.browser');
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('browser') || config('database.default') !== 'mariadb' || config('database.connections.mariadb.database') !== 'chandi_live_test') {
    throw new RuntimeException('Browser fixtures require the dedicated MariaDB test database.');
}
Cache::flush();
Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
$users = [];
foreach (['admin' => 'super_admin', 'dealer' => 'dealer'] as $kind => $role) {
    $password = Str::random(24);
    $email = $kind.'-browser@example.test';
    $u = User::create(['name' => 'Browser Test '.ucfirst($kind), 'email' => $email, 'password' => $password]);
    $u->forceFill(['role_id' => Role::where('name', $role)->value('id'), 'email_verified_at' => now()])->save();
    $users[$kind] = ['email' => $email, 'password' => $password];
}
$provider = MarketDataProvider::where('slug', 'demo')->first();
$provider->update(['enabled' => true]);
$fetch = app(FetchMarket::class);
foreach (app(DemoProvider::class)->historical('', '') as $v) {
    $fetch->store($provider, $v);
}
$path = '/tmp/chandi-browser-credentials.json';
file_put_contents($path, json_encode($users));
chmod($path, 0600);
echo "Test-only fixtures prepared. Credentials kept in a private temporary file.\n";
