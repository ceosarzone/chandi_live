<?php

namespace App\Models;

class MarketDataProvider extends Record
{
    protected $table = 'market_data_providers';

    protected $casts = ['last_success_at' => 'datetime', 'last_attempt_at' => 'datetime', 'enabled' => 'boolean'];
}
