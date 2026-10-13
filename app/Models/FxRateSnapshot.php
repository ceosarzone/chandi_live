<?php

namespace App\Models;

class FxRateSnapshot extends Record
{
    protected $table = 'fx_rate_snapshots';

    protected $casts = ['observed_at' => 'immutable_datetime', 'fetched_at' => 'immutable_datetime', 'is_demo' => 'boolean'];
}
