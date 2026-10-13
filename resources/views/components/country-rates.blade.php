@props(['countries'=>[]])
<div class="market-quotes">
@foreach($countries as $country)
<article class="country-rate market-quote" data-country="{{ $country['currency'] }}">
    <div class="quote-top"><span class="country-code">{{ ['USD'=>'US','PKR'=>'PK','INR'=>'IN'][$country['currency']] }}</span><h3>{{ $country['name'] }}</h3><span class="status-tag {{ $country['state'] }}">{{ ['recent'=>'Recent','stale'=>'Stale','demo'=>'Demo','unavailable'=>'No data'][$country['state']] }}</span></div>
    <p class="quote-kind">{{ $country['currency']==='USD'?'International silver spot':'Currency-converted spot benchmark' }}</p>
    <div class="financial country-headline"><small>{{ $country['currency'] }}</small> {{ isset($country['values'][$country['unit']])?\App\Support\Decimal::format($country['values'][$country['unit']],2):'—' }}</div>
    <div class="quote-unit">{{ $country['label'] }} <a href="#silver-chart" data-select-market="{{ $country['currency'] }}" aria-label="View {{ $country['name'] }} chart">View chart ↗</a></div>
    <div class="quote-time">@if($country['observed_at'])<time datetime="{{ $country['observed_at']->toIso8601String() }}">{{ $country['observed_at']->utc()->format('d M · H:i:s') }} UTC</time>@else Awaiting source data @endif</div>
    <details class="quote-details"><summary>Units & source details</summary><dl class="country-unit-list">@foreach(['g'=>'1 gram','10g'=>'10 grams','tola'=>'1 tola','oz'=>'1 troy ounce','kg'=>'1 kilogram'] as $unit=>$label)<div><dt>{{ $label }}</dt><dd>{{ isset($country['values'][$unit])?\App\Support\Decimal::format($country['values'][$unit],2):'—' }}</dd></div>@endforeach</dl><p>Silver: {{ $country['source'] ?? 'Unavailable' }}</p>@if($country['currency']!=='USD')<p>FX: {{ $country['fx_source'] ?? 'Unavailable' }}<br>{{ $country['fx_observed_at']?->utc()->format('d M Y, H:i') ?? 'No matching exchange rate' }} UTC</p>@endif @if($country['state']==='stale')<p>It is stale. Last-known silver or FX values are shown.</p>@endif @if($country['state']==='demo')<p>Simulated development fixture.</p>@endif</details>
</article>
@endforeach
@php($psl=\App\Models\PslQuote::latest('observed_at')->first())
<article class="market-quote psl-quote" data-psl-quote>
    <div class="quote-top"><span class="country-code">PK</span><h3>PSL silver</h3><span class="status-tag stale">{{ !$psl ? 'No data' : ($psl->observed_at->lt(now()->subHours(24)) ? 'Stale' : 'Source quote') }}</span></div>
    <p class="quote-kind">Pakistan · GoldToday published quote</p>
    <div class="financial country-headline"><small>PKR</small> {{ $psl?\App\Support\Decimal::format($psl->price,2):'—' }}</div>
    <div class="quote-unit">Unit unconfirmed <a href="#silver-chart" data-select-market="PSL" aria-label="View PSL chart">View chart ↗</a></div>
    <div class="quote-time">@if($psl)<time datetime="{{ $psl->observed_at->toIso8601String() }}">{{ $psl->observed_at->utc()->format('d M · H:i:s') }} UTC</time>@else Awaiting source data @endif</div>
    <details class="quote-details"><summary>About this quote</summary><p>GoldToday labels PSL as “10 Tola”, but its feed value appears inconsistent with that unit. This is the published number, without conversion. Confirm the weight and actual price with the source.</p><p>PSL history is collected from launch; earlier PSL history is not supplied by this source.</p>@if(\Illuminate\Support\Facades\Cache::has('market.psl.error'))<p>Feed check failed. Last-known quote retained.</p>@endif<a href="https://www.goldtoday.io/" target="_blank" rel="noopener noreferrer">View GoldToday ↗</a></details>
</article>
</div>
