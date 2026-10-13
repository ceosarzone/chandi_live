<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

class ConfiguredProxies extends TrustProxies
{
    protected function proxies()
    {
        return config('chandi.trusted_proxies', []);
    }
}
