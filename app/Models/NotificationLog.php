<?php

namespace App\Models;

class NotificationLog extends Record
{
    public function alert()
    {
        return $this->belongsTo(PriceAlert::class, 'price_alert_id');
    }

    public function snapshot()
    {
        return $this->belongsTo(MarketPriceSnapshot::class, 'market_price_snapshot_id');
    }

    protected $table = 'notification_logs';

    protected $casts = [];
}
