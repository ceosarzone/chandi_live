<?php

namespace Tests\Feature;

use App\Models\MarketDataProvider;
use App\Models\MarketPriceSnapshot;
use App\Models\PslQuote;
use App\Services\Market\FetchMarket;
use App\Services\Market\GoldToday;
use App\Services\Market\MarketRepository;
use App\Services\Market\ReferenceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_launch_hides_and_blocks_vendor_registration_and_marketplace(): void
    {
        config(['chandi.marketplace_enabled' => false]);
        $this->get('/')->assertOk()->assertSee('One metal. Three markets')->assertDontSee('Become a dealer')->assertDontSee('Find a dealer');
        foreach (['/register', '/become-a-dealer', '/dealers', '/products', '/dealer/profile'] as $path) {
            $this->get($path)->assertNotFound();
        }
        $this->post('/register', ['name' => 'Example'])->assertNotFound();
        $this->post('/dealer/profile')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/dealers')->assertDontSee('/products')->assertDontSee('become-a-dealer');
        foreach (['/silver-rate-in-usa', '/silver-rate-in-pakistan', '/silver-rate-in-india', '/silver-price-history'] as $path) {
            $this->get($path)->assertOk()->assertSee('data-market-chart', false);
        }
    }

    public function test_archive_import_is_idempotent_uses_same_day_fx_and_never_becomes_live(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $date = now('UTC')->subDays(3)->toDateString();
        $next = now('UTC')->subDays(2)->toDateString();
        Http::fake([
            "*currency-api@{$date}/*" => Http::response(['date' => $date, 'usd' => ['xag' => 0.05, 'pkr' => 100, 'inr' => 50]]),
            "*currency-api@{$next}/*" => Http::response(['date' => $next, 'usd' => ['xag' => 0.04, 'pkr' => 200, 'inr' => 60]]),
        ]);
        $service = app(ReferenceHistory::class);
        $result = $service->run($date, $next);
        $this->assertSame(2, $result['imported']);
        $this->assertSame([], $result['failed']);
        $this->assertSame(0, $service->run($date, $next)['imported']);
        Http::assertSentCount(2);
        Queue::assertNothingPushed();
        $this->assertNull(app(MarketRepository::class)->latest());
        $this->getJson('/market/chart?currency=PKR&unit=oz&period=7d')->assertOk()->assertJsonPath('points.0.value', '2000.000000000000')->assertJsonPath('points.1.value', '5000.000000000000')->assertJsonPath('change.percent', '150.00000000');
        $this->getJson('/market/chart?currency=INR&unit=oz&period=7d')->assertJsonPath('points.0.value', '1000.000000000000')->assertJsonPath('points.1.value', '1500.000000000000');
        $this->getJson('/market/chart?currency=USD&unit=oz&period=7d')->assertJsonPath('points.0.value', '20.000000000000');
        $this->getJson('/market/chart?period=24h')->assertJsonCount(0, 'points');
        $this->getJson('/market/chart?currency=BAD&unit=wrong')->assertUnprocessable();
    }

    public function test_archive_failure_is_a_gap_and_official_fallback_can_recover(): void
    {
        Http::preventStrayRequests();
        $date = now('UTC')->subDay()->toDateString();
        Http::fake(['*cdn.jsdelivr.net/*' => Http::response([], 503), "https://{$date}.currency-api.pages.dev/*" => Http::response(['date' => $date, 'usd' => ['xag' => 0.05, 'pkr' => 100, 'inr' => 50]])]);
        $this->assertSame(1, app(ReferenceHistory::class)->run($date, $date)['imported']);
        Http::assertSentCount(2);
        $bad = now('UTC')->subDays(2)->toDateString();
        Http::fake(['*' => Http::response(['date' => $date, 'usd' => ['xag' => 0.05, 'pkr' => 100, 'inr' => 50]])]);
        $this->assertSame([$bad], app(ReferenceHistory::class)->run($bad, $bad)['failed']);
        $this->assertSame(1, MarketPriceSnapshot::count());
    }

    public function test_psl_preserves_published_value_timestamp_and_unconfirmed_unit(): void
    {
        Http::preventStrayRequests();
        $at = now()->subHour()->utc()->format('Y-m-d\TH:i:s.v\Z');
        Http::fake(['www.goldtoday.io/*' => Http::sequence()->push(['success' => true, 'data' => ['rate' => 6750, 'updatedAt' => $at]])->push([], 503)]);
        $service = app(GoldToday::class);
        $service->run();
        $service->run();
        Http::assertSentCount(1);
        $quote = PslQuote::sole();
        $this->assertSame('6750.000000000000', $quote->price);
        $this->assertSame('unconfirmed', $quote->unit_status);
        $this->get('/')->assertSee('6,750.00')->assertSee('Unit unconfirmed');
        $this->getJson('/market/chart?currency=PSL&unit=kg')->assertJsonPath('unit', 'published quote')->assertJsonPath('points.0.value', '6750.000000000000')->assertJsonPath('limited', true);
        Cache::forget('market.psl.attempt');
        $service->run();
        $this->assertSame(1, PslQuote::count());
        $this->assertTrue(Cache::has('market.psl.error'));
    }

    public function test_missing_inr_history_stays_missing_and_invalid_psl_data_is_rejected(): void
    {
        Queue::fake();
        $v = app(ReferenceHistory::class)->normalize(['date' => now()->subDay()->toDateString(), 'usd' => ['xag' => 0.05, 'pkr' => 100, 'inr' => 50]], now()->subDay()->toDateString());
        unset($v['fx_inr']);
        app(FetchMarket::class)->store(MarketDataProvider::where('slug', 'gold_api')->first(), $v);
        $this->getJson('/market/chart?currency=INR')->assertJsonPath('points.0.value', null)->assertJsonPath('change', null);
        foreach ([['success' => false], ['success' => true, 'data' => ['rate' => 0, 'updatedAt' => now()->toIso8601String()]], ['success' => true, 'data' => ['rate' => 500, 'updatedAt' => '2026-02-30T00:00:00Z']], ['success' => true, 'data' => ['rate' => 500, 'updatedAt' => now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z')]]] as $data) {
            try {
                app(GoldToday::class)->normalize($data);
                $this->fail('Invalid PSL data accepted.');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
