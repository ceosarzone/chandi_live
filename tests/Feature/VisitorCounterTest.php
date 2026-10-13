<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Services\Analytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VisitorCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_visits_count_once_per_session_per_day(): void
    {
        config(['chandi.marketplace_enabled' => false]);
        $before = app(Analytics::class)->totalVisitors();
        $eventsBefore = AnalyticsEvent::where('event', 'platform_visit')->count();
        $this->get('/')->assertOk()->assertSee('Total visitors');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/')->assertOk();
        $this->get('/silver-rate-in-india')->assertOk();
        $this->assertSame($before + 1, app(Analytics::class)->totalVisitors());
        $this->assertSame($eventsBefore + 1, AnalyticsEvent::where('event', 'platform_visit')->count());

        $this->travel(1)->days();
        $this->get('/')->assertOk();
        $this->assertSame($before + 2, app(Analytics::class)->totalVisitors());
        $this->travelBack();
    }

    public function test_polls_bots_opt_outs_and_failed_pages_do_not_add_visitors(): void
    {
        $before = app(Analytics::class)->totalVisitors();
        $this->get('/market/rates')->assertOk();
        $this->getJson('/market/chart?period=1y')->assertOk();
        $this->get('/health')->assertOk();
        $this->get('/page-that-does-not-exist')->assertNotFound();
        $this->get('/', ['DNT' => '1'])->assertOk();
        $this->get('/', ['DNT' => '0', 'User-Agent' => 'Googlebot/2.1'])->assertOk();
        $this->assertSame($before, app(Analytics::class)->totalVisitors());
    }

    public function test_counter_survives_analytics_retention_and_formats_the_footer(): void
    {
        $before = app(Analytics::class)->totalVisitors();
        $this->get('/')->assertOk();
        AnalyticsEvent::where('event', 'platform_visit')->update(['created_at' => now()->subDays(91)]);
        $this->artisan('chandi:prune')->assertSuccessful();
        $this->assertSame(0, AnalyticsEvent::where('event', 'platform_visit')->count());
        $this->assertSame($before + 1, app(Analytics::class)->totalVisitors());

        DB::table('visitor_totals')->where('key', 'platform_visit')->update(['total' => 1234]);
        $this->get('/', ['DNT' => '1'])->assertOk()
            ->assertSee('data-visitor-total>1,234</strong>', false)
            ->assertSee('Recorded daily sessions');
    }
}
