<?php

return ['provider' => env('MARKET_PROVIDER', 'disabled'), 'demo' => (bool) env('MARKET_DEMO', false), 'license_confirmed' => (bool) env('MARKET_LICENSE_CONFIRMED', false), 'key' => env('METALS_DEV_API_KEY'), 'stale_minutes' => (int) env('MARKET_STALE_MINUTES', 180), 'fx_stale_hours' => (int) env('MARKET_FX_STALE_HOURS', 48), 'cache_seconds' => (int) env('MARKET_CACHE_SECONDS', 60), 'failure_notify_after' => (int) env('MARKET_FAILURE_NOTIFY_AFTER', 3), 'admin_email' => env('MARKET_ADMIN_EMAIL'), 'timeout' => 10];
