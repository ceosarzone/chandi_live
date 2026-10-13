<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('active')->default(true);
            $t->text('totp_secret')->nullable();
            $t->boolean('totp_enabled')->default(false);
            $t->unsignedBigInteger('totp_last_step')->nullable();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->json('permissions');
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->constrained();
        });
        Schema::create('countries', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->char('code', 2)->unique();
            $t->timestamps();
        });
        Schema::create('cities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('country_id')->constrained();
            $t->string('name');
            $t->string('slug')->unique();
            $t->boolean('active')->default(true);
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('product_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('dealer_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained();
            $t->foreignId('city_id')->constrained();
            $t->string('business_name');
            $t->string('slug')->unique();
            $t->string('representative');
            $t->string('phone', 30);
            $t->string('whatsapp', 30)->nullable();
            $t->string('address');
            $t->string('maps_url')->nullable();
            $t->string('category')->default('Silver dealer');
            $t->text('description');
            $t->string('logo_path')->nullable();
            $t->json('photos')->nullable();
            $t->json('social_urls')->nullable();
            $t->string('working_hours')->nullable();
            $t->string('website')->nullable();
            $t->string('status')->default('draft')->index();
            $t->boolean('verified')->default(false);
            $t->text('review_notes')->nullable();
            $t->dateTime('submitted_at')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('dealer_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dealer_profile_id')->constrained();
            $t->string('label');
            $t->string('path');
            $t->string('mime');
            $t->timestamps();
        });
        Schema::create('dealer_verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dealer_profile_id')->constrained();
            $t->foreignId('reviewer_id')->constrained('users');
            $t->string('decision');
            $t->text('notes');
            $t->boolean('verified')->default(false);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dealer_profile_id')->constrained();
            $t->foreignId('product_category_id')->constrained();
            $t->string('title');
            $t->string('slug')->unique();
            $t->text('description');
            $t->decimal('weight', 18, 8);
            $t->string('weight_unit', 10);
            $t->decimal('purity', 8, 5);
            $t->unsignedInteger('quantity')->default(1);
            $t->string('availability')->default('in_stock')->index();
            $t->decimal('price', 20, 8)->nullable();
            $t->string('currency', 3)->default('PKR');
            $t->string('condition')->default('new');
            $t->string('status')->default('pending')->index();
            $t->dateTime('published_at')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('alt');
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });
        Schema::create('metal_types', function (Blueprint $t) {
            $t->id();
            $t->string('symbol', 3)->unique();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('metal_purities', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->decimal('percentage', 8, 5);
            $t->timestamps();
        });
        Schema::create('market_data_providers', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->boolean('enabled')->default(false);
            $t->unsignedInteger('monthly_quota')->default(100);
            $t->unsignedInteger('refresh_minutes')->default(60);
            $t->dateTime('last_success_at')->nullable();
            $t->dateTime('last_attempt_at')->nullable();
            $t->unsignedInteger('failures')->default(0);
            $t->timestamps();
        });
        Schema::create('fx_rate_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('market_data_provider_id')->constrained();
            $t->char('base_currency', 3)->default('USD');
            $t->char('quote_currency', 3)->default('PKR');
            $t->decimal('rate', 24, 12);
            $t->dateTime('observed_at')->index();
            $t->dateTime('fetched_at');
            $t->boolean('is_demo')->default(false);
            $t->unique(['market_data_provider_id', 'observed_at', 'base_currency', 'quote_currency'], 'fx_observation_unique');
            $t->timestamps();
        });
        Schema::create('market_price_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('market_data_provider_id')->constrained();
            $t->foreignId('fx_rate_snapshot_id')->constrained();
            $t->string('metal', 3)->default('XAG');
            $t->char('currency', 3)->default('USD');
            $t->decimal('price', 24, 12);
            $t->decimal('gold_price', 24, 12)->nullable();
            $t->string('classification')->default('international_spot');
            $t->boolean('is_demo')->default(false)->index();
            $t->dateTime('observed_at')->index();
            $t->dateTime('fetched_at');
            $t->unique(['market_data_provider_id', 'metal', 'observed_at'], 'market_observation_unique');
            $t->timestamps();
        });
        Schema::create('daily_market_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('market_data_provider_id')->constrained();
            $t->date('date');
            $t->string('metal', 3);
            $t->decimal('open', 24, 12);
            $t->decimal('high', 24, 12);
            $t->decimal('low', 24, 12);
            $t->decimal('close', 24, 12);
            $t->unsignedInteger('observations');
            $t->boolean('is_demo');
            $t->unique(['market_data_provider_id', 'date', 'metal']);
            $t->timestamps();
        });
        Schema::create('dealer_price_quotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dealer_profile_id')->nullable()->constrained();
            $t->foreignId('entered_by')->constrained('users');
            $t->string('classification');
            $t->decimal('pkr_per_gram', 24, 12);
            $t->decimal('purity', 8, 5);
            $t->string('source');
            $t->dateTime('observed_at');
            $t->dateTime('expires_at');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('price_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->decimal('threshold', 24, 12);
            $t->string('direction', 5);
            $t->char('currency', 3);
            $t->string('unit', 10);
            $t->unsignedInteger('frequency_hours')->default(24);
            $t->dateTime('expires_at')->nullable();
            $t->dateTime('last_notified_at')->nullable();
            $t->boolean('active')->default(true);
            $t->boolean('email_enabled')->default(true);
            $t->string('unsubscribe_token', 64)->unique();
            $t->timestamps();
        });
        Schema::create('notification_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('price_alert_id')->constrained();
            $t->foreignId('market_price_snapshot_id')->constrained();
            $t->string('status')->default('pending');
            $t->dateTime('sent_at')->nullable();
            $t->unique(['price_alert_id', 'market_price_snapshot_id']);
            $t->timestamps();
        });
        Schema::create('enquiries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dealer_profile_id')->nullable()->constrained();
            $t->foreignId('product_id')->nullable()->constrained();
            $t->string('buyer_name');
            $t->string('email');
            $t->string('phone', 30)->nullable();
            $t->text('message');
            $t->dateTime('consented_at');
            $t->string('status')->default('new')->index();
            $t->string('dedup_key', 64)->unique();
            $t->timestamps();
        });
        Schema::create('enquiry_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enquiry_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('status');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('subscription_plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->decimal('monthly_price', 20, 8);
            $t->unsignedInteger('product_limit');
            $t->boolean('advanced_analytics')->default(false);
            $t->boolean('sponsored')->default(false);
            $t->boolean('active')->default(true);
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dealer_profile_id')->constrained();
            $t->foreignId('subscription_plan_id')->constrained();
            $t->dateTime('starts_at');
            $t->dateTime('expires_at')->index();
            $t->string('status')->default('active');
            $t->foreignId('activated_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dealer_profile_id')->constrained();
            $t->foreignId('subscription_plan_id')->constrained();
            $t->string('number')->unique();
            $t->decimal('amount', 20, 8);
            $t->char('currency', 3)->default('PKR');
            $t->string('status')->default('unpaid');
            $t->dateTime('due_at');
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->unique()->constrained();
            $t->foreignId('recorded_by')->constrained('users');
            $t->decimal('amount', 20, 8);
            $t->string('reference')->unique();
            $t->string('method')->default('offline');
            $t->dateTime('confirmed_at');
            $t->text('notes');
            $t->timestamps();
        });
        Schema::create('sponsored_placements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dealer_profile_id')->constrained();
            $t->foreignId('subscription_id')->constrained();
            $t->dateTime('starts_at');
            $t->dateTime('expires_at');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->text('body');
            $t->string('meta_description', 320)->nullable();
            $t->boolean('published')->default(false);
            $t->timestamps();
        });
        Schema::create('blog_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });
        Schema::create('blog_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('blog_category_id')->nullable()->constrained();
            $t->foreignId('author_id')->constrained('users');
            $t->string('title');
            $t->string('slug')->unique();
            $t->text('body');
            $t->string('meta_description', 320)->nullable();
            $t->string('type')->default('article');
            $t->dateTime('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('site_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('changes')->nullable();
            $t->timestamps();
        });
        Schema::create('background_job_status', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('status');
            $t->text('message')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('market_data_errors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('market_data_provider_id')->constrained();
            $t->string('code');
            $t->string('message');
            $t->timestamps();
        });
        Schema::create('provider_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('market_data_provider_id')->constrained();
            $t->string('endpoint');
            $t->string('status')->default('attempted');
            $t->index(['market_data_provider_id', 'created_at']);
            $t->timestamps();
        });
        Schema::create('analytics_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dealer_profile_id')->nullable()->constrained();
            $t->foreignId('product_id')->nullable()->constrained();
            $t->string('event');
            $t->string('dedup_key', 64)->unique();
            $t->index(['dealer_profile_id', 'event', 'created_at']);
            $t->timestamps();
        });
        Schema::create('content_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->text('reason');
            $t->string('email')->nullable();
            $t->string('status')->default('open');
            $t->timestamps();
        });

    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('role_id'));
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('provider_requests');
        Schema::dropIfExists('market_data_errors');
        Schema::dropIfExists('background_job_status');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('sponsored_placements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('enquiry_status_history');
        Schema::dropIfExists('enquiries');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('price_alerts');
        Schema::dropIfExists('dealer_price_quotes');
        Schema::dropIfExists('daily_market_prices');
        Schema::dropIfExists('market_price_snapshots');
        Schema::dropIfExists('fx_rate_snapshots');
        Schema::dropIfExists('market_data_providers');
        Schema::dropIfExists('metal_purities');
        Schema::dropIfExists('metal_types');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('dealer_verifications');
        Schema::dropIfExists('dealer_documents');
        Schema::dropIfExists('dealer_profiles');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('roles');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['active', 'totp_secret', 'totp_enabled', 'totp_last_step']));
    }
};
