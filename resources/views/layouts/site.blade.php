<!DOCTYPE html>
<html data-theme="light" data-base-url="{{ url('/') }}" data-livewire="{{ request()->is('silver-calculator')?'true':'false' }}" lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
<script>
// Apply the saved choice before styles paint; new visitors always start in light mode.
try { document.documentElement.dataset.theme = localStorage.getItem('chandi.theme') === 'dark' ? 'dark' : 'light'; } catch {}
</script>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
@php($siteSettings=\Illuminate\Support\Facades\Cache::remember('site.public_settings.'.app()->environment(),300,fn()=>\App\Models\SiteSetting::whereIn('key',['brand_tagline','contact_email','contact_phone','search_console','analytics_id','home_intro','legal_operator'])->pluck('value','key')->all()))
<title>{{ $title ?? trim($__env->yieldContent('title', 'Live silver market charts')) }} · CHANDI.LIVE</title>
<meta name="description" content="{{ $description ?? trim($__env->yieldContent('description','Follow silver in the USA, Pakistan and India with dated prices and historical charts.')) }}">
<link rel="canonical" href="{{ url()->current().(request()->integer('page')>1?'?page='.request()->integer('page'):'') }}">
<meta name="robots" content="{{ ($noindex ?? false)||request()->is('admin*','dealer','dealer/*','account*','profile','dashboard','login','register','alerts*','forgot-password','reset-password/*') ? 'noindex,nofollow' : 'index,follow' }}">
<meta property="og:site_name" content="CHANDI.LIVE"><meta property="og:title" content="{{ $title ?? trim($__env->yieldContent('title','Silver markets, in focus.')) }}"><meta property="og:type" content="{{ request()->is('blog/*')?'article':'website' }}"><meta property="og:url" content="{{ url()->current() }}"><meta property="og:image" content="{{ url('/images/social.jpg') }}"><meta name="twitter:card" content="summary_large_image">
@if(!empty($siteSettings['search_console']))<meta name="google-site-verification" content="{{ $siteSettings['search_console'] }}">@endif
<link rel="apple-touch-icon" href="{{ url('/images/apple-touch-icon.png') }}"><link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}"><link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}"><link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">@vite(['resources/css/app.css','resources/js/app.js']) @if(request()->is('silver-calculator'))@livewireStyles @endif
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'Organization','name'=>'CHANDI.LIVE','url'=>config('app.url'),'logo'=>url('/favicon.svg')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) !!}</script>
@stack('head')
</head>
<body x-data="{ menu: false }">
<a class="skip-link" href="#main">Skip to content</a>
<div class="topline"><span>INDEPENDENT SILVER MARKET MONITOR</span><span>USA / Pakistan / India <span class="dot"></span> USD / PKR / INR</span></div>
<header class="site-header"><a class="wordmark" href="{{ url('/') }}" aria-label="CHANDI.LIVE home"><x-application-logo/><span>CHANDI<span class="wordmark-live">.LIVE</span></span></a>
<nav class="desktop-nav" aria-label="Main navigation"><a href="{{ url('/') }}" class="{{ request()->is('/')?'current':'' }}">Overview</a><a href="{{ url('/silver-rate-in-usa') }}">USA</a><a href="{{ url('/silver-rate-in-pakistan') }}">Pakistan</a><a href="{{ url('/silver-rate-in-india') }}">India</a><a href="{{ url('/silver-price-history') }}#silver-chart">History</a></nav>
<div class="header-actions"><button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme"><svg data-theme-moon aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20.6 14A9 9 0 0 1 10 3.4 9 9 0 1 0 20.6 14Z"/></svg><svg data-theme-sun aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.4 1.4m11.2 11.2L19 19M5 19l1.4-1.4M17.6 6.4 19 5"/></svg></button><a href="{{ url('/silver-calculator') }}" class="button outline small desktop-cta">Silver calculator ↗</a><button class="menu-toggle button small" @click="menu = !menu" :aria-expanded="menu" aria-controls="mobile-nav">Menu <span x-text="menu ? '−' : '+'"></span></button></div></header>
<nav id="mobile-nav" class="mobile-nav" x-show="menu" x-cloak aria-label="Mobile navigation"><a href="{{ url('/') }}">Overview</a><a href="{{ url('/silver-rate-in-usa') }}">USA</a><a href="{{ url('/silver-rate-in-pakistan') }}">Pakistan</a><a href="{{ url('/silver-rate-in-india') }}">India</a><a href="{{ url('/silver-price-history') }}#silver-chart">History</a><a href="{{ url('/silver-calculator') }}">Silver calculator</a></nav>
@if(!app()->environment('production')&&config('market.demo'))<div class="notice demo-notice">DEVELOPMENT DEMO — simulated market observations and fictional accounts. Not current market prices.</div>@endif
@if(request()->is('admin*'))<div class="workspace-nav"><a href="{{ url('/admin') }}">Overview</a>@foreach(\App\Services\AdminResources::all() as $resource=>$def)@if(auth()->user()->canManage($def[1]))<a href="{{ url('/') }}/admin/{{ $resource }}">{{ ucfirst(str_replace('-',' ',$resource)) }}</a>@endif @endforeach @can('manage','settings')<a href="{{ url('/admin/settings') }}">Settings</a>@endcan<a href="{{ url('/account/two-factor') }}">Security</a></div>@elseif(request()->is('dealer','dealer/*','dashboard','profile','alerts','account*'))<div class="workspace-nav"><a href="{{ url('/dashboard') }}">Overview</a><a href="{{ url('/dealer/profile') }}">Business profile</a><a href="{{ url('/dealer/products/create') }}">Add product</a><a href="{{ url('/alerts') }}">Price alerts</a><a href="{{ url('/profile') }}">Account settings</a><form action="{{ url('/logout') }}" method="post">@csrf<button class="quiet-link">Sign out</button></form></div>@endif
<main id="main" class="container">
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>Please check these fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content') @isset($slot){{ $slot }}@endisset
</main>
<footer class="market-footer">
<div class="footer-bottom"><span>© {{ now('Asia/Karachi')->year }} CHANDI.LIVE · Silver markets, in focus.</span><div><a href="{{ url('/') }}#sources">Data sources</a><a href="{{ url('/privacy-policy') }}">Privacy</a><a href="{{ url('/terms-and-conditions') }}">Terms</a><a href="{{ url('/disclaimer') }}">Disclaimer</a></div></div>
<div class="visitor-counter" title="Cumulative recorded daily sessions. A session counts once per day; returning visitors on another day may count again. Do Not Track requests and recognized bots are excluded from new counts.">
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v2"/></svg>
    <span>Total visitors <strong data-visitor-total>{{ number_format(app(\App\Services\Analytics::class)->totalVisitors()) }}</strong></span>
    <span class="visitor-counter-note">Recorded daily sessions</span>
</div>
<p class="disclosure">Prices are informational and may be delayed. Converted benchmarks exclude local premiums, making charges and taxes. Always check the source timestamp and confirm the actual quotation before transacting.</p>
</footer>
<div id="offline-notice" class="offline-notice" hidden role="status">You’re offline. Displayed data may be outdated. Reconnect to refresh.</div>@if(request()->is('silver-calculator'))
@livewireScripts(['url'=>url('/livewire/livewire.min.js')])
<script>
// Livewire 3 emits a relative update URI; retain Apache's application subdirectory.
document.querySelector('script[data-update-uri]').dataset.updateUri = @js(url('/livewire/update'));
</script>
@endif @stack('scripts')
</body></html>
