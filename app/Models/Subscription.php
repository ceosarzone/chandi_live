<?php

namespace App\Models;

class Subscription extends Record
{
    protected $table = 'subscriptions';

    protected $casts = ['starts_at' => 'datetime', 'expires_at' => 'datetime'];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
