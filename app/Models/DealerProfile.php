<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class DealerProfile extends Record
{
    protected $table = 'dealer_profiles';

    protected $casts = ['verified' => 'boolean', 'photos' => 'array', 'social_urls' => 'array', 'submitted_at' => 'datetime'];

    use SoftDeletes;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function documents()
    {
        return $this->hasMany(DealerDocument::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentPlan()
    {
        return $this->subscriptions()->with('plan')->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now())->latest('expires_at')->first()?->plan ?? SubscriptionPlan::where('slug', 'free')->first();
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved')->whereHas('user', fn ($u) => $u->where('active', true));
    }
}
