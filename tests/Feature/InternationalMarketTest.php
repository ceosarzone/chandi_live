<?php

namespace Tests\Feature;

use App\Jobs\EvaluateAlerts;
use App\Jobs\SendPriceAlert;
use App\Models\FxRateSnapshot;
use App\Models\MarketDataProvider;
use App\Models\MarketPriceSnapshot;
use App\Models\NotificationLog;
use App\Models\PriceAlert;
use App\Models\User;
use App\Services\Market\FetchMarket;
use App\Services\Market\GoldApi;
use App\Services\Market\MarketRepository;
use App\Services\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InternationalMarketTest extends TestCase
{
    use RefreshDatabase;

    private function silver(): array
    {
        return ['symbol'=>'XAG', 'currency'=>'USD', 'price'=>'31.1034768', 'updatedAt'=>now()->utc()->format('Y-m-d\TH:i:s\Z')];
    }

    private function fx(): array
    {
        return ['result'=>'success', 'base_code'=>'USD', 'time_last_update_unix'=>now()->subHours(6)->timestamp,
            'rates'=>['PKR'=>'100', 'INR'=>'50']];
    }

    public function test_real_adapter_saves_separate_fx_timestamps_and_exact_three_country_prices(): void
    {
        Queue::fake();
        config(['market.provider'=>'gold_api']);
        Http::preventStrayRequests();
        Http::fake(['api.gold-api.com/*'=>Http::response($this->silver()), 'open.er-api.com/*'=>Http::response($this->fx())]);
        $provider = MarketDataProvider::where('slug','gold_api')->first();
        $provider->update(['enabled'=>true]);
        app(FetchMarket::class)->run();
        Http::assertSentCount(2);
        $snapshot = MarketPriceSnapshot::with(['fx','inrFx'])->sole();
        $this->assertFalse($snapshot->is_demo);
        $this->assertSame('100.000000000000', $snapshot->fx->rate);
        $this->assertSame('50.000000000000', $snapshot->inrFx->rate);
        $this->assertSame($this->fx()['time_last_update_unix'], $snapshot->fx->observed_at->timestamp);
        $this->assertNotEquals($snapshot->observed_at, $snapshot->fx->observed_at);
        $countries = app(MarketRepository::class)->countries();
        $this->assertSame(['USD','PKR','INR'], array_column($countries,'currency'));
        $this->assertSame('1.000000000000', $countries[0]['values']['g']);
        $this->assertSame('1166.380380000000', $countries[1]['values']['tola']);
        $this->assertSame('50000.000000000000', $countries[2]['values']['kg']);
        $this->get('/live-silver-rates')->assertOk()->assertSee('50,000.00')->assertSee('Rates By Exchange Rate API');
        $this->get('/silver-rate-in-usa')->assertOk();
        $this->get('/silver-rate-in-india')->assertOk()->assertSee('Currency-converted spot benchmark');
        // Polling reads stored values only, without returning a raw exchange-rate feed.
        $this->get('/market/rates')->assertOk()->assertDontSee('"rates":')->assertDontSee('USD/PKR');
        Http::assertSentCount(2);
        Queue::assertPushed(EvaluateAlerts::class, 1);
    }

    public function test_missing_inr_remains_unavailable_without_borrowing_pkr(): void
    {
        Queue::fake();
        $value = app(GoldApi::class)->normalize($this->silver(), $this->fx());
        unset($value['fx_inr']);
        app(FetchMarket::class)->store(MarketDataProvider::where('slug','gold_api')->first(), $value);
        $countries = app(MarketRepository::class)->countries();
        $this->assertSame('recent', $countries[1]['state']);
        $this->assertSame('unavailable', $countries[2]['state']);
        $this->assertSame([], $countries[2]['values']);
    }

    public function test_daily_fx_cache_is_reused_for_successive_silver_observations(): void
    {
        Cache::forget('market.gold_api.usd_fx');
        Http::preventStrayRequests();
        Http::fake(['api.gold-api.com/*'=>Http::response($this->silver()), 'open.er-api.com/*'=>Http::response($this->fx())]);
        app(GoldApi::class)->latest();
        app(GoldApi::class)->latest();
        Http::assertSentCount(3);
    }

    public function test_invalid_symbols_currencies_values_and_timestamps_are_rejected(): void
    {
        $cases = [
            [array_replace($this->silver(), ['symbol'=>'XAU']), $this->fx()],
            [array_replace($this->silver(), ['currency'=>'INR']), $this->fx()],
            [array_replace($this->silver(), ['price'=>'-1']), $this->fx()],
            [array_replace($this->silver(), ['updatedAt'=>'2026-02-30T00:00:00Z']), $this->fx()],
            [array_replace($this->silver(), ['updatedAt'=>now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z')]), $this->fx()],
            [$this->silver(), array_replace($this->fx(), ['base_code'=>'EUR'])],
            [$this->silver(), array_replace($this->fx(), ['rates'=>['PKR'=>'100']])],
            [$this->silver(), array_replace($this->fx(), ['rates'=>['PKR'=>'100','INR'=>'0']])],
            [$this->silver(), array_replace($this->fx(), ['time_last_update_unix'=>null])],
        ];
        foreach ($cases as [$silver,$fx]) {
            try { app(GoldApi::class)->normalize($silver,$fx); $this->fail('Invalid data accepted.'); }
            catch (\RuntimeException) { $this->addToAssertionCount(1); }
        }
    }

    public function test_stale_fx_marks_converted_prices_and_suppresses_pkr_alerts(): void
    {
        Queue::fake();
        $fx = array_replace($this->fx(), ['time_last_update_unix'=>now()->subDays(3)->timestamp]);
        $snapshot = app(FetchMarket::class)->store(MarketDataProvider::where('slug','gold_api')->first(), app(GoldApi::class)->normalize($this->silver(),$fx));
        $countries = app(MarketRepository::class)->countries();
        $this->assertSame(['recent','stale','stale'], array_column($countries,'state'));
        $user = User::factory()->create();
        PriceAlert::create(['user_id'=>$user->id,'threshold'=>'1','currency'=>'PKR','unit'=>'g','direction'=>'above','frequency_hours'=>24,'unsubscribe_token'=>str_repeat('b',64)]);
        (new EvaluateAlerts($snapshot->id))->handle(app(Pricing::class));
        Queue::assertNotPushed(SendPriceAlert::class);
        $this->assertSame(0, NotificationLog::count());
        $this->get('/silver-rate-in-pakistan')->assertOk()->assertSee('It is stale');
    }

    public function test_failed_real_feed_retains_last_good_observation(): void
    {
        Queue::fake();
        config(['market.provider'=>'gold_api']);
        $provider = MarketDataProvider::where('slug','gold_api')->first();
        $provider->update(['enabled'=>true]);
        app(FetchMarket::class)->store($provider, app(GoldApi::class)->normalize($this->silver(),$this->fx()));
        Http::preventStrayRequests();
        Http::fake(['api.gold-api.com/*'=>Http::response([],503), 'open.er-api.com/*'=>Http::response($this->fx())]);
        app(FetchMarket::class)->run();
        $this->assertSame(1, MarketPriceSnapshot::count());
        $this->assertSame(2, FxRateSnapshot::count());
        $this->assertSame(1, $provider->fresh()->failures);
        $this->assertSame('100.000000000000', app(MarketRepository::class)->latest()->fx->rate);
    }
}
