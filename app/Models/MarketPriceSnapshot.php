<?php

namespace App\Models;

class MarketPriceSnapshot extends Record
{
    protected $table = 'market_price_snapshots';

    protected $casts = ['observed_at' => 'immutable_datetime', 'fetched_at' => 'immutable_datetime', 'is_demo' => 'boolean'];

    public function provider()
    {
        return $this->belongsTo(MarketDataProvider::class, 'market_data_provider_id');
    }

    public function fx()
    {
        return $this->belongsTo(FxRateSnapshot::class, 'fx_rate_snapshot_id');
    }

    public function inrFx()
    {
        return $this->belongsTo(FxRateSnapshot::class, 'fx_inr_snapshot_id');
    }
}
