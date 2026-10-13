<?php

namespace App\Services\Market;

use App\Models\PslQuote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoldToday
{
    public function normalize(array $data): array
    {
        $price = $data['data']['rate'] ?? null;
        $at = $data['data']['updatedAt'] ?? '';
        if (($data['success'] ?? false) !== true || ! is_numeric($price) || bccomp((string) $price, '0', 12) <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $at)) {
            throw new \RuntimeException('Invalid PSL quote.');
        }
        $observed = CarbonImmutable::parse($at);
        if ($observed->format('Y-m-d') !== substr($at, 0, 10) || $observed->gt(now()->addMinutes(5))) {
            throw new \RuntimeException('Invalid PSL timestamp.');
        }

        // The publisher labels 10 tola, but its feed currently resembles a per-tola value.
        // Preserve the raw quote; never infer a unit or manufacture a premium.
        return ['source' => 'GoldToday', 'price' => (string) $price, 'currency' => 'PKR', 'unit_status' => 'unconfirmed', 'observed_at' => $observed, 'fetched_at' => now()];
    }

    public function run(): void
    {
        Cache::lock('market.psl.lock', 40)->get(function () {
            if (! Cache::add('market.psl.attempt', true, 900)) {
                return;
            }
            try {
                $data = Http::acceptJson()->connectTimeout(5)->timeout(15)->get('https://www.goldtoday.io/api/prices/get', ['type' => 'pslSilver'])->throw()->json();
                $value = $this->normalize($data);
                PslQuote::firstOrCreate(['source' => $value['source'], 'observed_at' => $value['observed_at']], $value);
                Cache::put('market.psl.last_check', now()->toIso8601String(), 86400);
                Cache::forget('market.psl.error');
            } catch (\Throwable $e) {
                Cache::put('market.psl.error', true, 86400);
                Log::warning('PSL feed unavailable; last verified observation retained.');
            }
        });
    }
}
