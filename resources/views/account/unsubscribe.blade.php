@extends('layouts.site')
@section('title','Unsubscribe from price alert')
@section('content')<section class="auth-shell"><h1>Stop this price alert?</h1><p>The selected watch is {{ $alert->currency }} {{ $alert->threshold+0 }} / {{ $alert->unit }}.</p><form method="post" action="{{ url()->current() }}">@csrf<button class="button">Confirm unsubscribe</button></form></section>@endsection