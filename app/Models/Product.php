<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Record
{
    protected $table = 'products';

    protected $casts = ['published_at' => 'datetime'];

    use SoftDeletes;

    public function dealer()
    {
        return $this->belongsTo(DealerProfile::class, 'dealer_profile_id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published')->whereHas('dealer', fn ($d) => $d->approved())->whereRaw("(SELECT COUNT(*) FROM products AS listed WHERE listed.dealer_profile_id = products.dealer_profile_id AND listed.deleted_at IS NULL AND listed.status = 'published' AND listed.id <= products.id) <= COALESCE((SELECT plan.product_limit FROM subscriptions AS membership JOIN subscription_plans AS plan ON plan.id = membership.subscription_plan_id WHERE membership.dealer_profile_id = products.dealer_profile_id AND membership.status = 'active' AND membership.starts_at <= ? AND membership.expires_at > ? ORDER BY membership.expires_at DESC LIMIT 1), (SELECT product_limit FROM subscription_plans WHERE slug = 'free' LIMIT 1), 0)", [now(), now()]);
    }
}
