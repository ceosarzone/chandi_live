@props(['countries'=>[]])
<div data-live-rates data-refresh-url="{{ url('/market/rates') }}">
    <div data-rates-content><x-country-rates :countries="$countries"/></div>
    <p class="tiny muted" data-rates-refresh-status>Display refreshes every 60 seconds. Source timestamps show market freshness.</p>
</div>
