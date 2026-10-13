<?php

namespace App\Services\Market;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoldApi implements Provider
{
    public function normalize(array $silver, array $fx): array
    {
        if (($silver['symbol'] ?? null) !== 'XAG' || ($silver['currency'] ?? null) !== 'USD'
            || ($fx['result'] ?? null) !== 'success' || ($fx['base_code'] ?? null) !== 'USD') {
            throw new \RuntimeException('Unexpected market symbols or currencies.');
        }
        foreach ([$silver['price'] ?? null, data_get($fx, 'rates.PKR'), data_get($fx, 'rates.INR')] as $value) {
            if (! is_numeric($value) || BigDecimal::of((string) $value)->isLessThanOrEqualTo('0')) {
                throw new \RuntimeException('Invalid market price.');
            }
        }
        $stamp = $silver['updatedAt'] ?? null;
        if (! is_string($stamp) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $stamp)) {
            throw new \RuntimeException('Invalid observation timestamp.');
        }
        $parsed = date_parse($stamp);
        if ($parsed['error_count'] || $parsed['warning_count']) {
            throw new \RuntimeException('Invalid observation date.');
        }
        $observed = CarbonImmutable::parse($stamp)->utc();
        $fxStamp = $fx['time_last_update_unix'] ?? null;
        if (! is_int($fxStamp) || $fxStamp <= 0) {
            throw new \RuntimeException('Missing exchange-rate timestamp.');
        }
        $fxObserved = CarbonImmutable::createFromTimestampUTC($fxStamp);
        if ($observed->gt(now()->addMinutes(5)) || $fxObserved->gt(now()->addMinutes(5))) {
            throw new \RuntimeException('Future observation rejected.');
        }
        return ['xag' => (string) $silver['price'], 'gold' => null,
            'fx' => (string) $fx['rates']['PKR'], 'fx_inr' => (string) $fx['rates']['INR'],
            'observed_at' => $observed, 'fx_observed_at' => $fxObserved,
            'fx_source' => 'ExchangeRate-API', 'is_demo' => false, 'resolution' => 'timestamp'];
    }

    public function latest(): array
    {
        // Daily FX stays on the server. Do not expose this raw response as an FX API.
        $fx = Cache::remember('market.gold_api.usd_fx', 3600, function () {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(config('market.timeout'))
                ->get('https://open.er-api.com/v6/latest/USD');
            $data = $response->json();
            if (! $response->successful() || ! is_array($data) || ($data['result'] ?? null) !== 'success') {
                throw new \RuntimeException('Exchange-rate feed unavailable.');
            }
            // Validate before caching, so malformed responses cannot poison future fetches.
            $this->normalize(['symbol'=>'XAG', 'currency'=>'USD', 'price'=>'1', 'updatedAt'=>now()->utc()->format('Y-m-d\TH:i:s\Z')], $data);
            return $data;
        });
        $response = Http::acceptJson()->connectTimeout(5)->timeout(config('market.timeout'))
            ->get('https://api.gold-api.com/price/XAG');
        $silver = $response->json();
        if (! $response->successful() || ! is_array($silver)) {
            throw new \RuntimeException('Silver feed unavailable.');
        }
        return $this->normalize($silver, $fx);
    }

    public function historical(string $from, string $to): array
    {
        throw new \RuntimeException('Historical imports require a separate provider plan. Live observations build local history.');
    }
}
