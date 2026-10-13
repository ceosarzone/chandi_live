<?php

namespace Tests\Feature;

use App\Jobs\EvaluateAlerts;
use App\Jobs\SendPlatformMail;
use App\Jobs\SendPriceAlert;
use App\Models\AnalyticsEvent;
use App\Models\City;
use App\Models\DailyMarketPrice;
use App\Models\DealerDocument;
use App\Models\DealerPriceQuote;
use App\Models\DealerProfile;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\MarketDataError;
use App\Models\MarketDataProvider;
use App\Models\MarketPriceSnapshot;
use App\Models\NotificationLog;
use App\Models\Page;
use App\Models\Payment;
use App\Models\PriceAlert;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProviderRequest;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\PlatformMessage;
use App\Services\AdminResources;
use App\Services\ImageStorage;
use App\Services\Market\FetchMarket;
use App\Services\Market\MarketRepository;
use App\Services\Market\MetalsDev;
use App\Services\Pricing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'customer'): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role_id' => Role::where('name', $role)->value('id')])->save();

        return $u;
    }

    private function dealer(?User $u = null, string $status = 'approved'): DealerProfile
    {
        $u ??= $this->user('dealer');

        return DealerProfile::create(['user_id' => $u->id, 'city_id' => City::first()->id, 'business_name' => 'Test Silver '.$u->id, 'slug' => 'test-silver-'.$u->id, 'representative' => 'Test Representative', 'phone' => '+923001234567', 'whatsapp' => '+923001234567', 'address' => 'Test business address', 'description' => 'A test dealer profile created only in the dedicated testing database.', 'status' => $status]);
    }

    private function product(DealerProfile $d, string $status = 'published'): Product
    {
        return Product::create(['dealer_profile_id' => $d->id, 'product_category_id' => ProductCategory::first()->id, 'title' => 'Test silver bar', 'slug' => 'test-bar-'.$d->id, 'description' => 'Test silver product description for moderation.', 'weight' => '10', 'weight_unit' => 'g', 'purity' => '99.9', 'quantity' => 1, 'price' => '1500', 'status' => $status]);
    }

    private function observation(bool $demo = false, ?CarbonImmutable $at = null): array
    {
        return ['xag' => '31.1034768', 'fx' => '100', 'gold' => '2000', 'observed_at' => $at ?? CarbonImmutable::now()->subMinute(), 'is_demo' => $demo];
    }

    private function provider(): MarketDataProvider
    {
        $p = MarketDataProvider::where('slug', 'metals_dev')->first();
        $p->update(['enabled' => true, 'monthly_quota' => 50, 'refresh_minutes' => 60]);

        return $p;
    }

    private function dealerPayload(): array
    {
        return ['business_name' => 'New dealer', 'representative' => 'Business owner', 'city_id' => City::first()->id, 'phone' => '03001234567', 'address' => 'Valid physical test shop address', 'category' => 'Bullion dealer', 'description' => 'A legitimate length description for this new testing dealer application.', 'intent' => 'submit'];
    }

    private function productPayload(): array
    {
        return ['title' => 'New silver product', 'product_category_id' => ProductCategory::first()->id, 'description' => 'A detailed new physical silver test product.', 'weight' => '10', 'weight_unit' => 'g', 'purity' => '99.9', 'quantity' => 1, 'availability' => 'in_stock', 'price' => '1500', 'currency' => 'PKR', 'condition' => 'new'];
    }

    public function test_database_is_actually_mariadb(): void
    {
        $row = DB::selectOne('SELECT VERSION() AS v');
        $this->assertStringContainsString('MariaDB', $row->v);
    }

    public function test_public_pages_render_empty_states(): void
    {
        foreach (['/', '/silver-rate-in-pakistan', '/silver-rate-per-tola', '/silver-rate-per-gram', '/silver-rate-per-ounce', '/silver-price-history', '/silver-calculator', '/gold-silver-ratio', '/silver-rate/lahore', '/dealers', '/dealers/lahore', '/products', '/become-a-dealer', '/about', '/contact', '/advertise', '/blog', '/guides', '/privacy-policy', '/terms-and-conditions', '/disclaimer', '/dealer-policy', '/login', '/register'] as $url) {
            $this->get($url)->assertOk();
        }$this->get('/')->assertSee('Unavailable');
    }

    public function test_nationwide_city_disclosure_and_sitemap(): void
    {
        $this->get('/silver-rate/karachi')->assertSee('nationwide currency-converted benchmark')->assertSee('noindex');
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertDontSee('silver-rate/karachi');
        $this->get('/health')->assertJson(['database' => 'mariadb', 'status' => 'ok']);
    }

    public function test_calculator_endpoint_validates_and_returns_precise_results(): void
    {
        $this->postJson('/calculator', ['weight' => '1', 'unit' => 'tola', 'purity' => '99.9', 'quantity' => 1, 'benchmark' => '100', 'premium' => 0, 'charges' => 0, 'tax' => 0])->assertOk()->assertJsonPath('results.gross_weight', '11.66380380')->assertJsonPath('results.intrinsic', '1165.21399962');
        $this->postJson('/calculator', ['weight' => 0])->assertUnprocessable();
    }

    public function test_public_cannot_access_admin_or_dealer_actions(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs($this->user())->get('/admin')->assertForbidden();
    }

    public function test_data_operator_is_least_privilege(): void
    {
        $this->actingAs($this->user('data_operator'))->get('/admin/providers')->assertOk();
        $this->get('/admin/dealers')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
    }

    public function test_public_registration_cannot_set_admin_role(): void
    {
        $this->post('/register', ['name' => 'Buyer', 'email' => 'buyer@example.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'role_id' => Role::where('name', 'super_admin')->value('id')])->assertRedirect('/dashboard');
        $this->assertSame('customer', User::where('email', 'buyer@example.test')->first()->role->name);
    }

    public function test_dealer_application_persists_and_does_not_auto_verify(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/dealer/profile', $this->dealerPayload())->assertRedirect('/dealer');
        $d = $u->fresh()->dealer;
        $this->assertSame('submitted', $d->status);
        $this->assertFalse($d->verified);
        $this->assertSame('+923001234567', $d->phone);
        $this->assertSame('dealer', $u->fresh()->role->name);
    }

    public function test_private_upload_and_cross_dealer_document_denial(): void
    {
        Storage::fake('local');
        $u = $this->user();
        $this->actingAs($u)->post('/dealer/profile', $this->dealerPayload() + ['document' => UploadedFile::fake()->create('verification.pdf', 10, 'application/pdf'), 'document_label' => 'Business proof'])->assertRedirect('/dealer');
        $doc = DealerDocument::first();
        Storage::disk('local')->assertExists($doc->path);
        $this->actingAs($this->user('dealer'))->get('/documents/'.$doc->id)->assertForbidden();
        $this->actingAs($u)->get('/documents/'.$doc->id)->assertOk();
    }

    public function test_dealer_review_requires_notes_documents_and_authority(): void
    {
        Queue::fake();
        $d = $this->dealer(null, 'submitted');
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->post('/admin/dealers/'.$d->id.'/review', ['decision' => 'approved', 'notes' => 'Reviewed business contact and application.', 'verified' => 1])->assertSessionHasErrors('verified');
        DealerDocument::create(['dealer_profile_id' => $d->id, 'label' => 'Business evidence', 'path' => 'private/file.pdf', 'mime' => 'application/pdf']);
        $this->post('/admin/dealers/'.$d->id.'/review', ['decision' => 'approved', 'notes' => 'Reviewed uploaded business supporting document.', 'verified' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($d->fresh()->verified);
        $this->assertDatabaseHas('dealer_verifications', ['dealer_profile_id' => $d->id, 'reviewer_id' => $admin->id, 'decision' => 'approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dealer.review']);
    }

    public function test_dealer_can_only_edit_own_products(): void
    {
        $a = $this->dealer();
        $b = $this->dealer();
        $p = $this->product($b);
        $this->actingAs($a->user)->get('/dealer/products/'.$p->id.'/edit')->assertForbidden();
        $this->post('/dealer/products/'.$p->id, $this->productPayload())->assertForbidden();
        $this->delete('/dealer/products/'.$p->id)->assertForbidden();
    }

    public function test_listing_workflow_upload_moderation_and_public_visibility(): void
    {
        Storage::fake('public');
        $d = $this->dealer();
        $this->actingAs($d->user)->post('/dealer/products', $this->productPayload() + ['images' => [UploadedFile::fake()->image('bar.jpg', 300, 200)]])->assertRedirect('/dealer');
        $p = Product::first();
        $this->assertSame('pending', $p->status);
        $this->assertCount(1, $p->images);
        $this->get('/products/'.$p->slug)->assertNotFound();
        $this->actingAs($this->user('platform_admin'))->post('/admin/products/'.$p->id.'/moderate', ['status' => 'published', 'notes' => 'Reviewed description and photograph.'])->assertSessionHasNoErrors();
        $this->get('/products/'.$p->slug)->assertOk()->assertSee('New silver product');
    }

    public function test_subscription_listing_limits_enforced(): void
    {
        $d = $this->dealer();
        SubscriptionPlan::where('slug', 'free')->update(['product_limit' => 1]);
        $this->product($d);
        $this->actingAs($d->user)->post('/dealer/products', $this->productPayload())->assertSessionHasErrors('title');
        $this->assertSame(1, Product::count());
    }

    public function test_suspension_hides_profiles_products_and_blocks_changes(): void
    {
        $d = $this->dealer();
        $p = $this->product($d);
        $d->update(['status' => 'suspended']);
        $this->get('/dealers/'.$d->slug)->assertNotFound();
        $this->get('/products/'.$p->slug)->assertNotFound();
        $this->actingAs($d->user)->post('/dealer/profile', $this->dealerPayload())->assertForbidden();
        $this->get('/dealer/products/create')->assertForbidden();
    }

    public function test_marketplace_filters_and_exact_grams(): void
    {
        $d = $this->dealer();
        $p = $this->product($d);
        $p->update(['weight' => 1, 'weight_unit' => 'tola']);
        $this->get('/products?min_weight=11&max_weight=12&min_price=1000&currency=PKR')->assertOk()->assertSee('Test silver bar');
        $this->get('/products?min_weight=12')->assertDontSee('Test silver bar');
        $this->get('/products?purity=100')->assertDontSee('Test silver bar');
    }

    public function test_enquiry_persists_deduplicates_and_is_private(): void
    {
        Queue::fake();
        $d = $this->dealer();
        $p = $this->product($d);
        $v = ['dealer_profile_id' => $d->id, 'product_id' => $p->id, 'buyer_name' => 'Interested Buyer', 'email' => 'buyer@example.test', 'message' => 'Please confirm silver purity and availability.', 'consent' => 1];
        $this->post('/enquiries', $v)->assertSessionHasNoErrors();
        $this->post('/enquiries', $v)->assertSessionHasNoErrors();
        $this->assertSame(1, Enquiry::count());
        Queue::assertPushed(SendPlatformMail::class);
        $this->get('/dealers/'.$d->slug)->assertDontSee('buyer@example.test');
        $this->actingAs($d->user)->get('/dealer')->assertOk()->assertSee('buyer@example.test');
        $other = $this->dealer();
        $this->actingAs($other->user)->get('/dealer')->assertDontSee('buyer@example.test');
        $this->post('/leads/'.Enquiry::first()->id, ['status' => 'completed'])->assertForbidden();
    }

    public function test_enquiry_rejects_cross_dealer_product_and_honeypot(): void
    {
        $a = $this->dealer();
        $b = $this->dealer();
        $p = $this->product($b);
        $v = ['dealer_profile_id' => $a->id, 'product_id' => $p->id, 'buyer_name' => 'Test Buyer', 'email' => 'buyer@example.test', 'message' => 'I would like a product quotation.', 'consent' => 1];
        $this->post('/enquiries', $v)->assertUnprocessable();
        $this->post('/enquiries', array_merge($v, ['website' => 'spam.example']))->assertSessionHasErrors('website');
        $this->assertSame(0, Enquiry::count());
    }

    public function test_whatsapp_link_is_encoded_and_click_not_lead(): void
    {
        $d = $this->dealer();
        $p = $this->product($d);
        $response = $this->get('/whatsapp/'.$d->id.'?product='.$p->id);
        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/923001234567?text=', $response->headers->get('Location'));
        $this->assertSame(0, Enquiry::count());
        $this->assertDatabaseHas('analytics_events', ['event' => 'whatsapp_click']);
    }

    public function test_manual_invoice_payment_activation_and_duplicate_prevention(): void
    {
        $d = $this->dealer();
        $plan = SubscriptionPlan::where('slug', 'premium')->first();
        $this->actingAs($d->user)->post('/dealer/invoices', ['subscription_plan_id' => $plan->id])->assertSessionHasNoErrors();
        $i = Invoice::first();
        $this->assertSame('unpaid', $i->status);
        $this->assertSame(0, Subscription::count());
        $admin = $this->user('super_admin');
        $v = ['amount' => '2500', 'reference' => 'RECEIPT-TEST-1', 'notes' => 'Confirmed receipt in the bank statement.', 'months' => '1', 'confirmed' => 1];
        $this->actingAs($admin)->post('/admin/invoices/'.$i->id.'/confirm', array_merge($v, ['amount' => 2000]))->assertSessionHasErrors('amount');
        $this->assertSame(0, Payment::count());
        $this->post('/admin/invoices/'.$i->id.'/confirm', $v)->assertSessionHasNoErrors();
        $this->assertSame('paid', $i->fresh()->status);
        $this->assertSame($plan->id, $d->fresh()->currentPlan()->id);
        $this->post('/admin/invoices/'.$i->id.'/confirm', array_merge($v, ['reference' => 'RECEIPT-TEST-2']))->assertSessionHasErrors();
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, Subscription::count());
    }

    public function test_expired_subscription_falls_back_to_free(): void
    {
        $d = $this->dealer();
        Subscription::create(['dealer_profile_id' => $d->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'premium')->value('id'), 'starts_at' => now()->subMonths(2), 'expires_at' => now()->subMonth(), 'activated_by' => $this->user('super_admin')->id]);
        $this->assertSame('free', $d->currentPlan()->slug);
        $this->artisan('chandi:expire')->assertSuccessful();
        $this->assertSame('expired', Subscription::first()->status);
    }

    public function test_market_duplicate_storage_aggregation_and_stale_state(): void
    {
        Queue::fake();
        $p = $this->provider();
        $fetch = app(FetchMarket::class);
        $v = $this->observation();
        $fetch->store($p, $v);
        $fetch->store($p, $v);
        $this->assertSame(1, MarketPriceSnapshot::count());
        $v['xag'] = '32';
        $v['observed_at'] = $v['observed_at']->addSeconds(20);
        $fetch->store($p, $v);
        $day = DailyMarketPrice::first();
        $this->assertSame(2, $day->observations);
        $this->assertSame('32.000000000000', $day->high);
        $m = app(MarketRepository::class);
        $this->assertSame('recent', $m->state($m->latest()));
        $this->travel(4)->hours();
        $this->assertSame('stale', $m->state($m->latest()));
    }

    public function test_provider_normalizes_inverse_fx_and_rejects_units(): void
    {
        $a = new MetalsDev;
        $v = $a->normalize(['status' => 'success', 'currency' => 'USD', 'unit' => 'toz', 'timestamp' => now()->toIso8601String(), 'metals' => ['silver' => 30, 'gold' => 2000], 'currencies' => ['PKR' => '0.005']]);
        $this->assertSame('200.000000000000', $v['fx']);
        $this->expectException(\RuntimeException::class);
        $a->normalize(['currency' => 'USD', 'unit' => 'g', 'timestamp' => now()->toIso8601String()]);
    }

    public function test_invalid_response_and_api_failure_keep_last_good_without_leaking_key(): void
    {
        Queue::fake();
        $p = $this->provider();
        app(FetchMarket::class)->store($p, $this->observation());
        config(['market.provider' => 'metals_dev', 'market.key' => 'TEST-SECRET-KEY', 'market.license_confirmed' => true]);
        Http::fake(['*' => Http::response(['status' => 'success', 'currency' => 'USD', 'unit' => 'toz', 'timestamp' => now()->toIso8601String(), 'metals' => ['silver' => -1], 'currencies' => ['PKR' => 0]], 200)]);
        app(FetchMarket::class)->run();
        $this->assertSame(1, MarketPriceSnapshot::count());
        $this->assertSame(3, ProviderRequest::count());
        $this->assertSame(1, $p->fresh()->failures);
        $this->assertStringNotContainsString('TEST-SECRET', MarketDataError::first()->message);
        $p->refresh()->update(['last_attempt_at' => null]);
        Http::fake(['*' => Http::response([], 503)]);
        app(FetchMarket::class)->run();
        $this->assertSame(2, $p->fresh()->failures);
        $this->assertSame(1, MarketPriceSnapshot::count());
    }

    public function test_quota_and_refresh_interval_prevent_extra_requests(): void
    {
        config(['market.provider' => 'metals_dev', 'market.key' => 'TESTKEY', 'market.license_confirmed' => true]);
        $p = $this->provider();
        $p->update(['monthly_quota' => 1]);
        Http::fake(['*' => Http::response([], 503)]);
        app(FetchMarket::class)->run();
        Http::assertSentCount(1);
        $this->assertSame(1, ProviderRequest::count());
        app(FetchMarket::class)->run();
        Http::assertSentCount(1);
        $p->refresh()->update(['last_attempt_at' => null]);
        app(FetchMarket::class)->run();
        Http::assertSentCount(1);
    }

    public function test_production_excludes_demo_even_if_demo_setting_enabled(): void
    {
        Queue::fake();
        $p = MarketDataProvider::where('slug', 'demo')->first();
        app(FetchMarket::class)->store($p, $this->observation(true));
        config(['market.demo' => true]);
        $this->app->instance('env', 'production');
        $this->assertNull(app(MarketRepository::class)->latest());
        $this->expectException(\RuntimeException::class);
        app(FetchMarket::class)->store($p, $this->observation(true));
    }

    public function test_alerts_trigger_only_fresh_real_data_once(): void
    {
        Notification::fake();
        Queue::fake();
        $u = $this->user();
        $a = PriceAlert::create(['user_id' => $u->id, 'threshold' => 90, 'direction' => 'above', 'currency' => 'PKR', 'unit' => 'g', 'frequency_hours' => 24, 'unsubscribe_token' => str_repeat('a', 64)]);
        $s = app(FetchMarket::class)->store($this->provider(), $this->observation());
        $job = new EvaluateAlerts($s->id);
        $job->handle(app(Pricing::class));
        $job->handle(app(Pricing::class));
        Queue::assertPushed(SendPriceAlert::class, 1);
        (new SendPriceAlert(NotificationLog::first()->id, 'Test threshold alert'))->handle();
        Notification::assertSentToTimes($u, PlatformMessage::class, 1);
        $this->assertSame(1, NotificationLog::count());
        $a->update(['last_notified_at' => null]);
        $s->update(['is_demo' => true]);
        $job->handle(app(Pricing::class));
        $s->update(['is_demo' => false, 'observed_at' => now()->subDay()]);
        $job->handle(app(Pricing::class));
        Notification::assertSentToTimes($u, PlatformMessage::class, 1);
    }

    public function test_alert_ownership_opt_in_and_unsubscribe(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/alerts', ['threshold' => '500', 'direction' => 'above', 'currency' => 'PKR', 'unit' => 'tola', 'frequency_hours' => 24, 'email_enabled' => 1])->assertSessionHasNoErrors();
        $alert = PriceAlert::first();
        $this->actingAs($this->user())->delete('/alerts/'.$alert->id)->assertForbidden();
        $this->get('/alerts/unsubscribe/'.$alert->unsubscribe_token)->assertOk();
        $this->post('/alerts/unsubscribe/'.$alert->unsubscribe_token)->assertRedirect('/');
        $this->assertFalse($alert->fresh()->active);
    }

    public function test_cms_escapes_script_and_operator_cannot_edit(): void
    {
        $this->actingAs($this->user('platform_admin'))->post('/admin/pages', ['title' => 'Test editorial page', 'slug' => 'test-editorial', 'body' => '<script>alert("xss")</script> Safe editorial content.', 'published' => 1])->assertSessionHasNoErrors();
        $page = Page::where('slug', 'test-editorial')->first();
        $this->assertNotNull($page);
        $this->get('/admin/pages/'.$page->id)->assertOk();
        $this->actingAs($this->user('data_operator'))->post('/admin/pages/'.$page->id, ['title' => 'Changed'])->assertForbidden();
    }

    public function test_all_admin_management_views_render(): void
    {
        $u = $this->user('super_admin');
        $this->actingAs($u)->get('/admin')->assertOk();
        foreach (AdminResources::all() as $key => $def) {
            $this->get('/admin/'.$key)->assertOk();
            if ($def[3] && ! in_array($key, ['providers', 'placements', 'reports', 'users'])) {
                $this->get('/admin/'.$key.'/create')->assertOk();
            }
        }foreach (MarketDataProvider::all() as $p) {
            $this->get('/admin/providers/'.$p->id)->assertOk();
        }$this->get('/admin/settings')->assertOk();
    }

    public function test_two_factor_blocks_admin_until_challenge(): void
    {
        $u = $this->user('super_admin');
        $totp = new Google2FA;
        $u->forceFill(['totp_enabled' => true, 'totp_secret' => $totp->generateSecretKey()])->save();
        $this->actingAs($u)->get('/admin')->assertRedirect('/account/two-factor/challenge');
        $this->post('/account/two-factor/challenge', ['code' => $totp->getCurrentOtp($u->totp_secret)])->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
    }

    public function test_deactivated_user_cannot_access_dashboard(): void
    {
        $u = $this->user();
        $u->forceFill(['active' => false])->save();
        $this->actingAs($u)->get('/dashboard')->assertForbidden();
        $this->assertGuest();
    }

    public function test_successful_fetch_validates_and_stores_real_provider_response(): void
    {
        Queue::fake();
        $p = $this->provider();
        config(['market.provider' => 'metals_dev', 'market.key' => 'TESTONLY', 'market.license_confirmed' => true]);
        Http::fake(['*' => Http::response(['status' => 'success', 'currency' => 'USD', 'unit' => 'toz', 'timestamp' => now()->subMinute()->toIso8601String(), 'metals' => ['silver' => '31.1034768', 'gold' => '2000'], 'currencies' => ['PKR' => '0.005']], 200)]);
        app(FetchMarket::class)->run();
        $s = MarketPriceSnapshot::first();
        $this->assertFalse($s->is_demo);
        $this->assertSame('200.000000000000', $s->fx->rate);
        $this->assertNotNull($p->fresh()->last_success_at);
        $this->assertSame('success', ProviderRequest::first()->status);
        Queue::assertPushed(EvaluateAlerts::class);
    }

    public function test_daily_history_is_not_claimed_as_intraday_data(): void
    {
        Queue::fake();
        $p = $this->provider();
        $v = $this->observation(false, CarbonImmutable::now()->subHour());
        $v['resolution'] = 'daily';
        app(FetchMarket::class)->store($p, $v);
        $this->assertSame('historical_observation', MarketPriceSnapshot::first()->classification);
        $this->assertSame([], app(MarketRepository::class)->history('24h'));
        $this->assertCount(1, app(MarketRepository::class)->history('7d'));
        Queue::assertNotPushed(EvaluateAlerts::class);
    }

    public function test_missing_provider_timestamp_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new MetalsDev)->normalize(['currency' => 'USD', 'unit' => 'toz', 'metals' => ['silver' => '30'], 'currencies' => ['PKR' => '0.003']]);
    }

    public function test_review_documents_require_admin_second_factor(): void
    {
        Storage::fake('local');
        $d = $this->dealer();
        $doc = DealerDocument::create(['dealer_profile_id' => $d->id, 'label' => 'Private business proof', 'path' => 'private/test.pdf', 'mime' => 'application/pdf']);
        Storage::disk('local')->put($doc->path, 'test private file');
        $u = $this->user('super_admin');
        $u->forceFill(['totp_enabled' => true, 'totp_secret' => (new Google2FA)->generateSecretKey()])->save();
        $this->actingAs($u)->get('/documents/'.$doc->id)->assertRedirect('/account/two-factor/challenge');
        $this->withSession(['totp_user' => $u->id])->get('/documents/'.$doc->id)->assertOk();
    }

    public function test_premium_activity_is_gated_by_current_membership(): void
    {
        $d = $this->dealer();
        $this->actingAs($d->user)->get('/dealer')->assertSee('Extended activity reporting is included');
        Subscription::create(['dealer_profile_id' => $d->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'premium')->value('id'), 'starts_at' => now()->subDay(), 'expires_at' => now()->addMonth(), 'activated_by' => $this->user('super_admin')->id]);
        $this->get('/dealer')->assertDontSee('Extended activity reporting is included')->assertSee('No activity in this range');
    }

    public function test_material_profile_change_requires_re_review(): void
    {
        $d = $this->dealer();
        $d->update(['verified' => true]);
        $this->actingAs($d->user)->post('/dealer/profile', $this->dealerPayload())->assertSessionHasNoErrors();
        $this->assertSame('submitted', $d->fresh()->status);
        $this->assertFalse($d->fresh()->verified);
        $this->get('/dealers/'.$d->slug)->assertNotFound();
    }

    public function test_local_reference_quote_is_distinct_append_only_and_audited(): void
    {
        $u = $this->user('data_operator');
        $this->actingAs($u)->post('/admin/quotes', ['pkr_per_gram' => '200', 'purity' => '99.9', 'source' => 'Testing reference source', 'observed_at' => now()->subMinute()->toDateTimeString(), 'expires_at' => now()->addHour()->toDateTimeString()])->assertSessionHasNoErrors();
        $q = DealerPriceQuote::first();
        $this->assertSame('admin_reference', $q->classification);
        $this->get('/silver-rate-in-pakistan')->assertSee('Administrator-entered quotations')->assertSee('Testing reference source');
        $this->post('/admin/quotes/'.$q->id, ['pkr_per_gram' => '300', 'purity' => '99.9', 'source' => 'Attempted change', 'observed_at' => now()->subMinute()->toDateTimeString(), 'expires_at' => now()->addHour()->toDateTimeString()])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.quotes.saved']);
    }

    public function test_published_cms_page_is_crawlable_and_escapes_untrusted_markup(): void
    {
        $p = Page::create(['slug' => 'new-informational-page', 'title' => 'An informational page', 'body' => '<script>alert(1)</script> Correctly escaped article text.', 'published' => true]);
        $this->get('/'.$p->slug)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/sitemap.xml')->assertSee($p->slug);
    }

    public function test_invalid_pakistan_phone_is_not_accepted(): void
    {
        $this->actingAs($this->user())->post('/dealer/profile', array_merge($this->dealerPayload(), ['phone' => '+92000']))->assertSessionHasErrors('phone');
    }

    public function test_rate_limit_blocks_abusive_enquiry_submissions(): void
    {
        Queue::fake();
        $d = $this->dealer();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/enquiries', ['buyer_name' => 'Buyer', 'email' => 'buyer@example.test', 'message' => 'Test request number '.$i, 'dealer_profile_id' => $d->id, 'consent' => 1])->assertSessionHasNoErrors();
        }$this->post('/enquiries', ['buyer_name' => 'Buyer', 'email' => 'buyer@example.test', 'message' => 'Test request blocked', 'dealer_profile_id' => $d->id, 'consent' => 1])->assertTooManyRequests();
    }

    public function test_expiry_limits_public_listings_immediately_and_keeps_records(): void
    {
        $d = $this->dealer();
        SubscriptionPlan::where('slug', 'free')->update(['product_limit' => 1]);
        $p = $this->product($d);
        $second = $p->replicate();
        $second->slug = 'second-listing';
        $second->save();
        $membership = Subscription::create(['dealer_profile_id' => $d->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'premium')->value('id'), 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay(), 'activated_by' => $this->user('super_admin')->id]);
        $this->assertSame(2, Product::published()->count());
        $this->travel(2)->days();
        $this->assertSame(1, Product::published()->count());
        $this->assertSame(2, Product::count());
        $this->get('/products/second-listing')->assertNotFound();
    }

    public function test_contact_click_is_a_redirect_and_not_an_enquiry(): void
    {
        $d = $this->dealer();
        $this->get('/contact-dealer/'.$d->id)->assertRedirect('tel:'.$d->phone);
        $this->assertDatabaseHas('analytics_events', ['event' => 'contact_click', 'dealer_profile_id' => $d->id]);
        $this->assertSame(0, Enquiry::count());
    }

    public function test_alert_delivery_cancels_if_observation_becomes_stale_in_queue(): void
    {
        Notification::fake();
        Queue::fake();
        $u = $this->user();
        $a = PriceAlert::create(['user_id' => $u->id, 'threshold' => 90, 'direction' => 'above', 'currency' => 'PKR', 'unit' => 'g', 'frequency_hours' => 24, 'unsubscribe_token' => str_repeat('b', 64)]);
        $s = app(FetchMarket::class)->store($this->provider(), $this->observation());
        $log = NotificationLog::create(['price_alert_id' => $a->id, 'market_price_snapshot_id' => $s->id, 'status' => 'reserved']);
        $this->travel(4)->hours();
        (new SendPriceAlert($log->id, 'Test-only price alert'))->handle();
        Notification::assertNothingSent();
        $this->assertSame('cancelled', $log->fresh()->status);
    }

    public function test_malformed_scalar_fields_and_sub_precision_weights_are_rejected(): void
    {
        $this->actingAs($this->user())->post('/dealer/profile', array_merge($this->dealerPayload(), ['phone' => ['bad'], 'city_id' => [[City::first()->id]]]))->assertSessionHasErrors(['phone', 'city_id']);
        $d = $this->dealer();
        $this->actingAs($d->user)->post('/dealer/products', array_merge($this->productPayload(), ['weight' => '0.000000001']))->assertSessionHasErrors('weight');
        $this->get('/silver-calculator?weight[]=1')->assertSessionHasErrors('weight');
    }

    public function test_failed_image_replacement_keeps_existing_gallery(): void
    {
        Storage::fake('public');
        $d = $this->dealer();
        $p = $this->product($d);
        Storage::disk('public')->put('products/existing.jpg', 'old image');
        $old = ProductImage::create(['product_id' => $p->id, 'path' => 'products/existing.jpg', 'alt' => 'Original image']);
        $this->mock(ImageStorage::class)->shouldReceive('store')->once()->andThrow(ValidationException::withMessages(['images' => 'Could not process replacement.']));
        $this->actingAs($d->user)->post('/dealer/products/'.$p->id, $this->productPayload() + ['images' => [UploadedFile::fake()->image('new.jpg')]])->assertSessionHasErrors('images');
        Storage::disk('public')->assertExists('products/existing.jpg');
        $this->assertDatabaseHas('product_images', ['id' => $old->id, 'path' => 'products/existing.jpg']);
    }

    public function test_log_transport_does_not_claim_email_delivery(): void
    {
        Notification::fake();
        Queue::fake();
        config(['mail.default' => 'log']);
        $u = $this->user();
        $a = PriceAlert::create(['user_id' => $u->id, 'threshold' => '90', 'direction' => 'above', 'currency' => 'PKR', 'unit' => 'g', 'frequency_hours' => 24, 'unsubscribe_token' => str_repeat('c', 64)]);
        $s = app(FetchMarket::class)->store($this->provider(), $this->observation());
        $log = NotificationLog::create(['price_alert_id' => $a->id, 'market_price_snapshot_id' => $s->id, 'status' => 'reserved']);
        (new SendPriceAlert($log->id, 'Test-only notification'))->handle();
        $this->assertSame('logged', $log->fresh()->status);
        $this->assertNull($log->fresh()->sent_at);
        $this->assertNull($a->fresh()->last_notified_at);
    }

    public function test_public_session_activity_respects_dnt_and_is_deduplicated(): void
    {
        $before = AnalyticsEvent::where('event', 'platform_visit')->count();
        $response = $this->get('/')->assertOk();
        $cookie = $response->getCookie(config('session.cookie'));
        $this->withCookie(config('session.cookie'), $cookie->getValue())->get('/silver-rate-per-gram')->assertOk();
        $this->assertSame($before + 1, AnalyticsEvent::where('event', 'platform_visit')->count());
        $this->withHeader('DNT', '1')->withCookie(config('session.cookie'), Str::random(40))->get('/about')->assertOk();
        $this->assertSame($before + 1, AnalyticsEvent::where('event', 'platform_visit')->count());
    }
}
