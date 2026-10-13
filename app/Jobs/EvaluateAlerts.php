<?php

namespace App\Jobs;

use App\Models\MarketPriceSnapshot;
use App\Models\NotificationLog;
use App\Models\PriceAlert;
use App\Services\Pricing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class EvaluateAlerts implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public function __construct(public int $snapshotId) {}

    public function handle(Pricing $pricing): void
    {
        $s = MarketPriceSnapshot::with('fx')->findOrFail($this->snapshotId);
        if ($s->is_demo || $s->observed_at->lt(now()->subMinutes(config('market.stale_minutes')))) {
            return;
        }
        PriceAlert::with('user')->where('active', true)->where('email_enabled', true)->whereHas('user', fn ($q) => $q->whereNotNull('email_verified_at')->where('active', true))->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->chunkById(100, function ($alerts) use ($s, $pricing) {
            foreach ($alerts as $alert) {
                if ($alert->currency === 'PKR' && $s->fx->observed_at->lt(now()->subHours(config('market.fx_stale_hours', 48)))) {
                    continue;
                }
                $value = $pricing->unitPrice($s->price, $s->fx->rate, $alert->unit, $alert->currency);
                $matches = $alert->direction === 'above' ? bccomp($value, $alert->threshold, 12) >= 0 : bccomp($value, $alert->threshold, 12) <= 0;
                if (! $matches) {
                    continue;
                }
                $log = DB::transaction(function () use ($alert, $s) {
                    $a = PriceAlert::lockForUpdate()->find($alert->id);
                    if (! $a->active || ($a->last_reserved_at && $a->last_reserved_at->gt(now()->subHours($a->frequency_hours))) || NotificationLog::where('price_alert_id', $a->id)->where('market_price_snapshot_id', $s->id)->exists()) {
                        return null;
                    }
                    $log = NotificationLog::create(['price_alert_id' => $a->id, 'market_price_snapshot_id' => $s->id, 'status' => 'reserved']);
                    $a->update(['last_reserved_at' => now()]);

                    return $log;
                });
                if (! $log) {
                    continue;
                }
                SendPriceAlert::dispatch($log->id, $alert->currency.' '.bcadd($value, '0', 2).' per '.$alert->unit.' observed at '.$s->observed_at->toIso8601String().'. Converted benchmark; not a dealer offer.')->afterCommit();
            }
        });
    }
}
