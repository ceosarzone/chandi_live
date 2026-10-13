<?php

namespace App\Models;

class SponsoredPlacement extends Record
{
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    protected $table = 'sponsored_placements';

    protected $casts = ['starts_at' => 'datetime', 'expires_at' => 'datetime'];
}
