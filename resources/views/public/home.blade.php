@extends('layouts.site')
@section('title','Live silver prices · USA, Pakistan & India')
@section('description','Follow silver spot in USD, converted PKR and INR benchmarks, historical charts and the published Pakistan PSL quote.')
@section('content')
<div class="market-dashboard">
<header class="market-intro"><div><span class="eyebrow">THE SILVER MARKET, IN FOCUS</span><h1>One metal. Three markets<span>.</span></h1><p>International spot, local currency context. A clearer view of silver.</p></div><div class="market-date"><span>MARKET OVERVIEW</span><time datetime="{{ now('UTC')->toDateString() }}">{{ now('Asia/Karachi')->format('d M Y') }}</time><small>Automatic updates · every minute</small></div></header>
<x-live-rates :countries="$countries"/>
<x-chart :selected="$selected ?? 'USD'"/>
<section class="market-method" id="sources"><div><span class="eyebrow">BEHIND THE NUMBERS</span><h2>A price with context.</h2><p>Compare the same silver content, with a clear source for every observation.</p></div><div><h3>International & converted spot</h3><p>USA is XAG/USD spot. Pakistan and India convert that price using dated FX; local retail prices, taxes and premiums can differ.</p><p><a href="https://gold-api.com" target="_blank" rel="noopener noreferrer">Gold API ↗</a> · <a href="https://www.exchangerate-api.com" target="_blank" rel="noopener noreferrer">Rates By Exchange Rate API</a></p></div><div><h3>History & Pakistan PSL</h3><p><a href="https://github.com/fawazahmed0/exchange-api" target="_blank" rel="noopener noreferrer">Currency API ↗</a> supplies daily silver references and same-day FX. These are reference snapshots, not exchange closing prices.</p><p><a href="https://www.goldtoday.io/" target="_blank" rel="noopener noreferrer">GoldToday ↗</a> supplies the separate PSL quote. Its published unit needs confirmation; PSL observations accumulate from launch.</p></div></section>
<div class="market-conventions"><span>1 troy ounce = 31.1034768 g</span><span>1 tola = 11.6638038 g</span><a href="{{ url('/silver-calculator') }}">Calculate silver value ↗</a></div>
</div>
@endsection
