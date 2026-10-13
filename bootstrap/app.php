<?php

use App\Http\Middleware\ActiveAccount;
use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\ConfiguredProxies;
use App\Http\Middleware\MarketOnly;
use App\Http\Middleware\PublicActivity;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')->withMiddleware(function (Middleware $m): void {
    $m->trustHosts(at: fn () => ['^'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST)).'$'], subdomains: false);
    $m->replace(TrustProxies::class, ConfiguredProxies::class);
    $m->prepend(MarketOnly::class);
    $m->web(append: [SecurityHeaders::class, ActiveAccount::class, PublicActivity::class]);
    $m->alias(['admin' => AdminAccess::class]);
})->withExceptions(function (Exceptions $e): void {
    $e->dontFlash(['current_password', 'password', 'password_confirmation', 'api_key', 'code']);
})->create();
