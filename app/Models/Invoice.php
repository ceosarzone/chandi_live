<?php

namespace App\Models;

class Invoice extends Record
{
    protected $table = 'invoices';

    protected $casts = ['due_at' => 'datetime'];

    public function dealer()
    {
        return $this->belongsTo(DealerProfile::class, 'dealer_profile_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
