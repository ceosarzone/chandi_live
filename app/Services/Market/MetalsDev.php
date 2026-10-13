<?php

namespace App\Services\Market;

use App\Models\SiteSetting;
use Brick\Math\BigDecimal as D;
use Brick\Math\RoundingMode as R;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class MetalsDev implements Provider
{
    private function request(string $endpoint, array $query = []): array
    {
        $saved = SiteSetting::where('key', 'metals_dev_api_key')->value('value');
        $key = config('market.key') ?: ($saved ? Crypt::decryptString($saved) : null);
        $licensed = config('market.key') ? config('market.license_confirmed') : SiteSetting::where('key', 'market_license_confirmed')->value('value') === '1';
        if (! $key || ! $licensed) {
            throw new \RuntimeException('Credentials and redistribution approval are required.');
        }
        // Retries are owned by the quota-aware service. Never log exception URLs containing credentials.
        $r = Http::acceptJson()->connectTimeout(5)->timeout(config('market.timeout'))->get('https://api.metals.dev/v1/'.$endpoint, ['api_key' => $key] + $query);
        if (! $r->successful()) {
            throw new \RuntimeException('Provider HTTP failure.');
        }
        $data = $r->json();
        if (! is_array($data) || ($data['status'] ?? null) !== 'success') {
            throw new \RuntimeException('Provider rejected request.');
        }

        return $data;
    }

    public function normalize(array $d, ?string $date = null): array
    {
        if (($d['currency'] ?? null) !== 'USD' || ($d['unit'] ?? null) !== 'toz') {
            throw new \RuntimeException('Unexpected provider units.');
        }
        foreach (['metals.silver', 'currencies.PKR'] as $key) {
            $value = data_get($d, $key);
            if (! is_numeric($value) || D::of((string) $value)->isLessThanOrEqualTo('0')) {
                throw new \RuntimeException('Invalid market response.');
            }
        }
        $stamp = $date ?? ($d['timestamp'] ?? null);
        if (! is_string($stamp) || ! preg_match('/^\d{4}-\d{2}-\d{2}(?:T| )/', $stamp)) {
            throw new \RuntimeException('Invalid timestamp.');
        }
        $parsed = date_parse($stamp);
        if ($parsed['error_count'] || $parsed['warning_count'] || ! preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $stamp)) {
            throw new \RuntimeException('Invalid timestamp.');
        }
        $observed = CarbonImmutable::parse($stamp)->utc();
        if ($observed->isAfter(now()->addMinutes(5))) {
            throw new \RuntimeException('Future observation rejected.');
        }
        $gold = data_get($d, 'metals.gold');
        $inr = data_get($d, 'currencies.INR');
        if ($inr !== null && (! is_numeric($inr) || D::of((string) $inr)->isLessThanOrEqualTo('0'))) {
            throw new \RuntimeException('Invalid INR exchange rate.');
        }
        if ($gold !== null && (! is_numeric($gold) || D::of((string) $gold)->isLessThanOrEqualTo('0'))) {
            throw new \RuntimeException('Invalid gold price.');
        }

        return ['xag' => (string) $d['metals']['silver'], 'gold' => $gold === null ? null : (string) $gold, 'fx' => (string) D::of('1')->dividedBy((string) $d['currencies']['PKR'], 12, R::HALF_UP), 'fx_inr' => $inr === null ? null : (string) D::of('1')->dividedBy((string) $inr, 12, R::HALF_UP), 'observed_at' => $observed, 'is_demo' => false, 'resolution' => $date ? 'daily' : 'timestamp'];
    }

    public function latest(): array
    {
        return $this->normalize($this->request('latest', ['currency' => 'USD', 'unit' => 'toz']));
    }

    public function historical(string $from, string $to): array
    {
        $d = $this->request('timeseries', ['start_date' => $from, 'end_date' => $to]);
        if (($d['currency'] ?? null) !== 'USD' || ($d['unit'] ?? null) !== 'toz') {
            throw new \RuntimeException('Unexpected provider units.');
        }
        if (! is_array($d['rates'] ?? null)) {
            throw new \RuntimeException('Invalid history.');
        }

        return collect($d['rates'])->map(fn ($v, $date) => $this->normalize($v + ['currency' => $d['currency'], 'unit' => $d['unit']], $date.'T00:00:00Z'))->values()->all();
    }
}
