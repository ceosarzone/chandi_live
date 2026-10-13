<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use Illuminate\Support\Facades\DB;

class Analytics
{
    public function track(string $event, ?int $dealer = null, ?int $product = null): void
    {
        if (! request()->hasSession() || request()->header('DNT') === '1') {
            return;
        }
        $key = hash_hmac('sha256', request()->session()->getId().'|'.$event.'|'.$dealer.'|'.$product.'|'.now()->toDateString(), config('app.key'));
        DB::transaction(function () use ($key, $event, $dealer, $product) {
            $record = AnalyticsEvent::firstOrCreate(['dedup_key' => $key], ['event' => $event, 'dealer_profile_id' => $dealer, 'product_id' => $product]);
            if ($event === 'platform_visit' && $record->wasRecentlyCreated) {
                DB::table('visitor_totals')->where('key', 'platform_visit')->increment('total');
            }
        }, 3);
    }

    public function totalVisitors(): int
    {
        return (int) DB::table('visitor_totals')->where('key', 'platform_visit')->value('total');
    }
}
