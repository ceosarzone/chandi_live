<?php

namespace App\Models;

class PriceAlert extends Record
{
    protected $table = 'price_alerts';

    protected $casts = ['expires_at' => 'datetime', 'last_reserved_at' => 'datetime', 'last_notified_at' => 'datetime', 'active' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
