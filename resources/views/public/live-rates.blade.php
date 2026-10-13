@extends('layouts.site')
@section('title',$title)
@section('content')
<div class="page-heading"><div class="breadcrumb"><a href="{{ url('/') }}">Home</a> / Live silver rates</div><span class="eyebrow">ONE METAL. THREE CURRENCIES.</span><h1>{{ $title }}<span class="accent-dot">.</span></h1><p>Follow international silver spot in US dollars, and its benchmark value in Pakistani and Indian rupees.</p></div>
<div class="city-links country-navigation"><a href="{{ url('/silver-rate-in-usa') }}">USA · USD ↗</a><a href="{{ url('/silver-rate-in-pakistan') }}">Pakistan · PKR ↗</a><a href="{{ url('/silver-rate-in-india') }}">India · INR ↗</a></div>
<x-live-rates :countries="$countries"/>
<section class="prose section"><h2>Compare the same silver content.</h2><p>Each card values pure silver using one recorded XAG/USD observation. One troy ounce is 31.1034768 grams; one tola is 11.6638038 grams. The Pakistan and India figures use independently timestamped USD/PKR and USD/INR exchange rates.</p><p>The US card is an international spot reference in USD. Pakistan and India cards are converted benchmarks, rather than quotations from local bullion associations, shops or the Indian MCX futures exchange. Confirm the actual buy or sell price with your dealer.</p><p>New observations build the recorded history. Unavailable periods remain gaps, and stale values are labelled rather than presented as fresh.</p><a class="button outline" href="{{ url('/silver-price-history') }}">Explore recorded history ↗</a></section>
@endsection
