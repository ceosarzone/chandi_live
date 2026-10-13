<?php

namespace App\Services;

use App\Models\AnalyticsEvent;

class Analytics
{
    public function track(string $event, ?int $dealer = null, ?int $product = null): void
    {
        if (! request()->hasSession() || request()->header('DNT') === '1') {
            return;
        }
        $key = hash_hmac('sha256', request()->session()->getId().'|'.$event.'|'.$dealer.'|'.$product.'|'.now()->toDateString(), config('app.key'));
        AnalyticsEvent::firstOrCreate(['dedup_key' => $key], ['event' => $event, 'dealer_profile_id' => $dealer, 'product_id' => $product]);
    }
}
