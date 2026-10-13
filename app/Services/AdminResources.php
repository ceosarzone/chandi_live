<?php

namespace App\Services;

use App\Models;

class AdminResources
{
    public static function all(): array
    {
        return [
            'dealers' => [Models\DealerProfile::class, 'dealers', ['business_name', 'status', 'verified', 'city_id'], [], ['business_name', 'status']],
            'products' => [Models\Product::class, 'products', ['title', 'status', 'purity', 'price', 'dealer_profile_id'], [], ['title', 'status']],
            'enquiries' => [Models\Enquiry::class, 'enquiries', ['buyer_name', 'status', 'dealer_profile_id', 'created_at'], [], ['buyer_name', 'status']],
            'providers' => [Models\MarketDataProvider::class, 'market', ['name', 'enabled', 'monthly_quota', 'refresh_minutes', 'failures', 'last_success_at'], ['enabled' => 'required|boolean', 'monthly_quota' => 'required|integer|min:1|max:1000000', 'refresh_minutes' => 'required|integer|min:1|max:1440'], ['name']],
            'snapshots' => [Models\MarketPriceSnapshot::class, 'market', ['price', 'is_demo', 'classification', 'observed_at', 'fetched_at'], [], ['classification']],
            'fx-rates' => [Models\FxRateSnapshot::class, 'market', ['base_currency', 'quote_currency', 'rate', 'observed_at'], [], ['base_currency', 'quote_currency']],
            'daily-history' => [Models\DailyMarketPrice::class, 'market', ['date', 'open', 'high', 'low', 'close', 'observations'], [], ['metal']],
            'provider-requests' => [Models\ProviderRequest::class, 'market', ['endpoint', 'status', 'created_at'], [], ['endpoint', 'status']],
            'market-errors' => [Models\MarketDataError::class, 'market', ['code', 'message', 'created_at'], [], ['code']],
            'jobs' => [Models\BackgroundJobStatus::class, 'market', ['name', 'status', 'message', 'completed_at'], [], ['name', 'status']],
            'quotes' => [Models\DealerPriceQuote::class, 'market_references', ['classification', 'pkr_per_gram', 'purity', 'source', 'observed_at', 'expires_at'], ['pkr_per_gram' => 'required|numeric|gt:0|max:100000000', 'purity' => 'required|numeric|gt:0|max:100', 'source' => 'required|string|max:255', 'observed_at' => 'required|date|before_or_equal:now', 'expires_at' => 'required|date|after:now', 'notes' => 'nullable|string|max:1000'], ['source', 'classification']],
            'cities' => [Models\City::class, 'products', ['name', 'slug', 'active'], ['country_id' => 'required|exists:countries,id', 'name' => 'required|string|max:100', 'slug' => 'required|alpha_dash|max:100', 'description' => 'required|string|min:50|max:2000', 'active' => 'required|boolean'], ['name', 'slug']],
            'categories' => [Models\ProductCategory::class, 'products', ['name', 'slug', 'active'], ['name' => 'required|string|max:100', 'slug' => 'required|alpha_dash|max:100', 'active' => 'required|boolean'], ['name']],
            'plans' => [Models\SubscriptionPlan::class, 'commercial', ['name', 'monthly_price', 'product_limit', 'advanced_analytics', 'sponsored', 'active'], ['name' => 'required|string|max:100', 'slug' => 'required|alpha_dash|max:100', 'monthly_price' => 'required|numeric|decimal:0,2|min:0|max:10000000', 'product_limit' => 'required|integer|min:0|max:10000', 'advanced_analytics' => 'required|boolean', 'sponsored' => 'required|boolean', 'active' => 'required|boolean', 'description' => 'nullable|string|max:2000'], ['name']],
            'subscriptions' => [Models\Subscription::class, 'commercial', ['dealer_profile_id', 'subscription_plan_id', 'status', 'starts_at', 'expires_at'], [], ['status']],
            'invoices' => [Models\Invoice::class, 'commercial', ['number', 'dealer_profile_id', 'amount', 'currency', 'status', 'due_at'], ['dealer_profile_id' => 'required|exists:dealer_profiles,id', 'subscription_plan_id' => 'required|exists:subscription_plans,id'], ['number', 'status']],
            'payments' => [Models\Payment::class, 'commercial', ['invoice_id', 'amount', 'reference', 'method', 'confirmed_at', 'recorded_by'], [], ['reference']],
            'placements' => [Models\SponsoredPlacement::class, 'commercial', ['dealer_profile_id', 'starts_at', 'expires_at', 'active'], ['active' => 'required|boolean'], []],
            'pages' => [Models\Page::class, 'cms', ['title', 'slug', 'published'], ['title' => 'required|string|max:150', 'slug' => 'required|alpha_dash|max:150', 'body' => 'required|string|max:50000', 'meta_description' => 'nullable|string|max:320', 'published' => 'required|boolean'], ['title', 'slug']],
            'articles' => [Models\BlogPost::class, 'cms', ['title', 'slug', 'type', 'published_at'], ['title' => 'required|string|max:150', 'slug' => 'required|alpha_dash|max:150', 'body' => 'required|string|max:50000', 'meta_description' => 'nullable|string|max:320', 'blog_category_id' => 'nullable|exists:blog_categories,id', 'type' => 'required|in:article,guide', 'published_at' => 'nullable|date'], ['title', 'slug', 'type']],
            'blog-categories' => [Models\BlogCategory::class, 'cms', ['name', 'slug'], ['name' => 'required|string|max:100', 'slug' => 'required|alpha_dash|max:100'], ['name']],
            'reports' => [Models\ContentReport::class, 'products', ['product_id', 'reason', 'status', 'created_at'], ['status' => 'required|in:open,reviewed,resolved,dismissed'], ['status', 'reason']],
            'roles' => [Models\Role::class, 'users', ['name', 'permissions'], [], ['name']],
            'users' => [Models\User::class, 'users', ['name', 'email', 'role_id', 'active', 'email_verified_at'], ['name' => 'required|string|max:150', 'role_id' => 'required|exists:roles,id', 'active' => 'required|boolean'], ['name', 'email']],
            'audit' => [Models\AuditLog::class, 'settings', ['user_id', 'action', 'subject_type', 'subject_id', 'created_at'], [], ['action']],
            'analytics' => [Models\AnalyticsEvent::class, 'enquiries', ['event', 'dealer_profile_id', 'product_id', 'created_at'], [], ['event']],
            'alerts' => [Models\PriceAlert::class, 'market', ['user_id', 'threshold', 'currency', 'unit', 'direction', 'active'], [], ['direction']],
            'notification-logs' => [Models\NotificationLog::class, 'market', ['price_alert_id', 'market_price_snapshot_id', 'status', 'sent_at'], [], ['status']],
        ];
    }

    public static function get(string $resource): array
    {
        return self::all()[$resource] ?? abort(404);
    }

    public static function choices(string $field): array
    {
        return match ($field) {
            'country_id' => Models\Country::pluck('name', 'id')->all(),'city_id' => Models\City::pluck('name', 'id')->all(),'dealer_profile_id' => Models\DealerProfile::approved()->pluck('business_name', 'id')->all(),'subscription_plan_id' => Models\SubscriptionPlan::where('active', true)->pluck('name', 'id')->all(),'blog_category_id' => Models\BlogCategory::pluck('name', 'id')->all(),'role_id' => Models\Role::pluck('name', 'id')->all(),'type' => ['article' => 'Article', 'guide' => 'Guide'],'status' => ['open' => 'Open', 'reviewed' => 'Reviewed', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'],default => []
        };
    }
}
