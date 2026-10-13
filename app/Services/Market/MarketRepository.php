<?php

namespace App\Services\Market;

use App\Models\MarketPriceSnapshot;
use App\Services\Pricing;
use Illuminate\Support\Facades\Cache;

class MarketRepository
{
    public function query()
    {
        return MarketPriceSnapshot::with(['provider', 'fx', 'inrFx'])->where('metal', 'XAG')->where('is_demo', ! app()->environment('production') && config('market.demo'));
    }

    public function latest()
    {
        return Cache::remember('market.latest.'.(int) config('market.demo').'.'.app()->environment(), config('market.cache_seconds'), fn () => $this->query()->whereHas('provider', fn ($q) => $q->where('slug', '!=', 'currency_archive'))->latest('observed_at')->first());
    }

    public function state($row): string
    {
        return ! $row ? 'unavailable' : ($row->is_demo ? 'demo' : ($row->observed_at->lt(now()->subMinutes(config('market.stale_minutes'))) ? 'stale' : 'recent'));
    }

    public function countries(): array
    {
        $row = $this->latest();
        $pricing = app(Pricing::class);
        $countries = [];
        foreach (['USD' => ['USA', 'oz', 'Per troy ounce', 'silver-rate-in-usa'],
            'PKR' => ['Pakistan', 'tola', 'Per tola', 'silver-rate-in-pakistan'],
            'INR' => ['India', 'kg', 'Per kilogram', 'silver-rate-in-india']] as $currency => [$name, $unit, $label, $path]) {
            $fx = $currency === 'PKR' ? $row?->fx : ($currency === 'INR' ? $row?->inrFx : null);
            $available = $row && ($currency === 'USD' || $fx);
            $state = $available ? $this->state($row) : 'unavailable';
            if ($state === 'recent' && $fx && $fx->observed_at->lt(now()->subHours(config('market.fx_stale_hours', 48)))) {
                $state = 'stale';
            }
            $values = [];
            if ($available) {
                foreach (['g', 'tola', 'oz', 'kg'] as $weight) {
                    $values[$weight] = $pricing->unitPrice($row->price, $fx?->rate ?? '1', $weight, $currency);
                }
                $values['10g'] = bcmul($values['g'], '10', 12);
            }
            $countries[] = ['name' => $name, 'currency' => $currency, 'unit' => $unit, 'label' => $label,
                'path' => $path, 'state' => $state, 'values' => $values,
                'observed_at' => $row?->observed_at, 'fetched_at' => $row?->fetched_at,
                'source' => $row?->provider->name, 'fx_observed_at' => $fx?->observed_at,
                'fx_source' => $fx?->source ?? $row?->provider->name];
        }

        return $countries;
    }

    public function history(string $period, string $currency = 'USD', string $unit = 'oz'): array
    {
        $days = match ($period) {
            '24h' => 1,'7d' => 7,'30d' => 30,'90d' => 90,'1y' => 365,'max' => 36500,default => 30
        };
        $q = $this->query();
        if ($period === '24h') {
            $q->where('observation_resolution', 'timestamp');
        } $latest = (clone $q)->latest('observed_at')->first();
        if (! $latest) {
            return [];
        }
        $from = ($latest->is_demo ? $latest->observed_at : now())->subDays($days);
        if ($period === '24h') {
            $rows = $q->where('observed_at', '>=', $from)->orderBy('observed_at')->limit(10000)->get();
        } else {
            $dates = (clone $q)->where('observed_at', '>=', $from)->selectRaw('MAX(observed_at) as observed_at')->groupByRaw('DATE(observed_at)')->pluck('observed_at');
            $rows = $q->whereIn('observed_at', $dates)->orderBy('observed_at')->orderByDesc('id')->get()->unique(fn ($r) => $r->observed_at->toDateString())->values();
        }
        // Preserve observed values; decimate long histories without interpolation.
        $step = max(1, (int) ceil($rows->count() / 500));
        $points = $rows->filter(fn ($r, $i) => $i % $step === 0 || $i === $rows->count() - 1);

        return $points->map(function ($r) use ($currency, $unit) {
            $fx = $currency === 'PKR' ? $r->fx : ($currency === 'INR' ? $r->inrFx : null);
            $price = $currency === 'USD' || $fx ? app(Pricing::class)->unitPrice($r->price, $fx?->rate ?? '1', $unit === '10g' ? 'g' : $unit, $currency) : null;
            if ($price !== null && $unit === '10g') {
                $price = bcmul($price, '10', 12);
            }

            return ['at' => $r->observed_at->toIso8601String(), 'value' => $price, 'usd' => $r->price,
                'pkr_gram' => $r->fx ? (string) app(Pricing::class)->perGram($r->price, $r->fx->rate) : null,
                'demo' => $r->is_demo, 'resolution' => $r->observation_resolution,
                'source' => $r->provider->name, 'series' => $r->provider->slug === 'currency_archive' ? 'reference' : 'recorded'];
        })->values()->all();
    }
}
