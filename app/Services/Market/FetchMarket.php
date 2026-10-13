<?php

namespace App\Services\Market;

use App\Jobs\EvaluateAlerts;
use App\Models\BackgroundJobStatus;
use App\Models\DailyMarketPrice;
use App\Models\FxRateSnapshot;
use App\Models\MarketDataError;
use App\Models\MarketDataProvider;
use App\Models\MarketPriceSnapshot;
use App\Models\ProviderRequest;
use App\Notifications\PlatformMessage;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class FetchMarket
{
    public function adapter(): Provider
    {
        return match (config('market.provider')) {
            'gold_api' => app(GoldApi::class),'metals_dev' => app(MetalsDev::class),'demo' => app(DemoProvider::class),default => throw new \RuntimeException('No market provider configured.')
        };
    }

    public function run(?string $from = null, ?string $to = null): void
    {
        if (config('market.provider') === 'disabled') {
            return;
        }
        Cache::lock('market.central-fetch', 90)->get(function () use ($from, $to) {
            $p = MarketDataProvider::where('slug', config('market.provider'))->firstOrFail();
            if (! $p->enabled) {
                return;
            }
            if (! $from && $p->last_attempt_at?->gt(now()->subMinutes($p->refresh_minutes))) {
                return;
            }
            $p->update(['last_attempt_at' => now()]);
            try {
                for ($attempt = 0; $attempt < 3; $attempt++) {
                    $request = DB::transaction(function () use ($p, $from) {
                        $locked = MarketDataProvider::lockForUpdate()->findOrFail($p->id);
                        if (ProviderRequest::where('market_data_provider_id', $p->id)->where('created_at', '>=', now()->startOfMonth())->count() >= $locked->monthly_quota) {
                            throw new \RuntimeException('Monthly request quota reached.');
                        }

return ProviderRequest::create(['market_data_provider_id' => $p->id, 'endpoint' => $from ? 'timeseries' : 'latest']);
                    });
                    try {
                        $rows = $from ? $this->adapter()->historical($from, $to) : [$this->adapter()->latest()];
                        $request->update(['status' => 'success']);
                        break;
                    } catch (\Throwable $e) {
                        $request->update(['status' => 'failed']);
                        if ($attempt === 2) {
                            throw $e;
                        }usleep((2 ** $attempt) * 250000);
                    }
                }
                foreach ($rows as $row) {
                    $this->store($p, $row);
                }
                $p->update(['last_success_at' => now(), 'failures' => 0]);
                BackgroundJobStatus::create(['name' => 'market:fetch', 'status' => 'success', 'message' => count($rows).' observations processed', 'completed_at' => now()]);
            } catch (\Throwable $e) {
                $p->increment('failures');
                MarketDataError::create(['market_data_provider_id' => $p->id, 'code' => 'fetch_failed', 'message' => 'Fetch failed; inspect credentials, quota and provider availability.']);
                BackgroundJobStatus::create(['name' => 'market:fetch', 'status' => 'failed', 'message' => 'Safe last-known observation retained.', 'completed_at' => now()]);
                if ($p->fresh()->failures === config('market.failure_notify_after') && config('market.admin_email')) {
                    Notification::route('mail', config('market.admin_email'))->notify(new PlatformMessage('Market data needs attention', 'Repeated market fetch failures. Check the protected administrator console.'));
                }
            }
        });
    }

    public function store(MarketDataProvider $p, array $v): MarketPriceSnapshot
    {
        foreach (['xag', 'fx'] as $k) {
            if (! is_numeric($v[$k]) || bccomp((string) $v[$k], '0', 12) <= 0) {
                throw new \RuntimeException('Invalid observation.');
            }
        }
        if ($v['is_demo'] && app()->environment('production')) {
            throw new \RuntimeException('Production rejects simulated observations.');
        }
        if (isset($v['fx_inr']) && (! is_numeric($v['fx_inr']) || bccomp((string) $v['fx_inr'], '0', 12) <= 0)) {
            throw new \RuntimeException('Invalid INR conversion.');
        }
        $row = DB::transaction(function () use ($p, $v) {
            $fxValues = ['fetched_at' => now(), 'is_demo' => $v['is_demo'], 'source' => $v['fx_source'] ?? $p->name];
            $fxKey = ['market_data_provider_id' => $p->id, 'observed_at' => $v['fx_observed_at'] ?? $v['observed_at'], 'base_currency' => 'USD'];
            $fx = FxRateSnapshot::firstOrCreate($fxKey + ['quote_currency' => 'PKR'], $fxValues + ['rate' => $v['fx']]);
            $inr = isset($v['fx_inr']) ? FxRateSnapshot::firstOrCreate($fxKey + ['quote_currency' => 'INR'], $fxValues + ['rate' => $v['fx_inr']]) : null;

            return MarketPriceSnapshot::firstOrCreate(['market_data_provider_id' => $p->id, 'metal' => 'XAG', 'observed_at' => $v['observed_at']], ['observation_resolution' => $v['resolution'] ?? 'timestamp', 'classification' => ($v['resolution'] ?? 'timestamp') === 'daily' ? 'historical_observation' : 'international_spot', 'fx_rate_snapshot_id' => $fx->id, 'fx_inr_snapshot_id' => $inr?->id, 'price' => $v['xag'], 'gold_price' => $v['gold'], 'is_demo' => $v['is_demo'], 'fetched_at' => now()]);
        });
        $day = $row->observed_at->toDateString();
        $q = MarketPriceSnapshot::where('market_data_provider_id', $p->id)->whereDate('observed_at', $day)->orderBy('observed_at');
        $all = $q->get();
        DailyMarketPrice::updateOrCreate(['market_data_provider_id' => $p->id, 'date' => $day, 'metal' => 'XAG'], ['open' => $all->first()->price, 'close' => $all->last()->price, 'high' => $all->reduce(fn ($a, $r) => BigDecimal::of($a)->isGreaterThan($r->price) ? $a : $r->price, $all->first()->price), 'low' => $all->reduce(fn ($a, $r) => BigDecimal::of($a)->isLessThan($r->price) ? $a : $r->price, $all->first()->price), 'observations' => $all->count(), 'is_demo' => $v['is_demo']]);
        Cache::forget('market.latest.'.(int) config('market.demo').'.'.app()->environment());
        if ($row->wasRecentlyCreated && ! $row->is_demo && $row->observation_resolution === 'timestamp') {
            EvaluateAlerts::dispatch($row->id);
        }

return $row;
    }
}
