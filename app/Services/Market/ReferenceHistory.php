<?php

namespace App\Services\Market;

use App\Models\MarketDataProvider;
use App\Models\MarketPriceSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ReferenceHistory
{
    public function normalize(array $data, string $date): array
    {
        if (($data['date'] ?? null) !== $date) {
            throw new \RuntimeException('Archive date mismatch.');
        }
        foreach (['xag', 'pkr', 'inr'] as $key) {
            $value = $data['usd'][$key] ?? null;
            if (! is_numeric($value) || bccomp((string) $value, '0', 12) <= 0) {
                throw new \RuntimeException('Invalid historical rate.');
            }
        }

        return ['xag' => bcdiv('1', (string) $data['usd']['xag'], 12), 'gold' => null,
            'fx' => (string) $data['usd']['pkr'], 'fx_inr' => (string) $data['usd']['inr'],
            'observed_at' => CarbonImmutable::parse($date, 'UTC')->startOfDay(),
            'fx_source' => 'Currency API · same-day reference', 'is_demo' => false, 'resolution' => 'daily'];
    }

    public function run(string $from, string $to): array
    {
        foreach ([$from, $to] as $date) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || CarbonImmutable::parse($date)->format('Y-m-d') !== $date) {
                throw new \InvalidArgumentException('Use YYYY-MM-DD dates.');
            }
        }
        $start = CarbonImmutable::parse($from, 'UTC');
        $end = CarbonImmutable::parse($to, 'UTC');
        if ($start->gt($end) || $start->diffInDays($end) > 365 || $end->gte(CarbonImmutable::now('UTC')->startOfDay())) {
            throw new \InvalidArgumentException('Choose up to 366 completed days.');
        }
        $provider = MarketDataProvider::firstOrCreate(['slug' => 'currency_archive'], ['name' => 'Currency API · daily reference', 'enabled' => false, 'monthly_quota' => 10000, 'refresh_minutes' => 1440]);
        $existing = MarketPriceSnapshot::where('market_data_provider_id', $provider->id)->whereBetween('observed_at', [$start, $end->endOfDay()])->pluck('observed_at')->map(fn ($at) => $at->toDateString())->all();
        $dates = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            if (! in_array($day->toDateString(), $existing, true)) {
                $dates[] = $day->toDateString();
            }
        }
        $result = ['imported' => 0, 'existing' => count($existing), 'failed' => []];
        foreach (array_chunk($dates, 8) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch) {
                return array_map(fn ($date) => $pool->as($date)->connectTimeout(5)->timeout(15)->get("https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@{$date}/v1/currencies/usd.min.json"), $batch);
            });
            foreach ($batch as $date) {
                try {
                    $response = $responses[$date] ?? null;
                    if (! $response instanceof Response || ! $response->successful()) {
                        $response = Http::connectTimeout(5)->timeout(15)->get("https://{$date}.currency-api.pages.dev/v1/currencies/usd.min.json")->throw();
                    }
                    app(FetchMarket::class)->store($provider, $this->normalize($response->json(), $date));
                    $result['imported']++;
                } catch (\Throwable $e) {
                    $result['failed'][] = $date;
                }
            }
        }
        $provider->update(['last_attempt_at' => now(), 'last_success_at' => $result['failed'] ? $provider->last_success_at : now(), 'failures' => count($result['failed'])]);

        return $result;
    }
}
