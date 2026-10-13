@section('title','Account access')
@php($slot = new \Illuminate\Support\HtmlString('<div class="auth-shell"><div class="eyebrow">YOUR SILVER PERSPECTIVE</div><h1>Welcome to Chandi.</h1><p class="muted">A clearer view of the market starts here.</p><div class="panel">'.(config('mail.default')==='log'?'<p class="notice">Development mail mode: messages are recorded privately on the server. External email delivery is not configured.</p>':'').$slot.'</div></div>'))
@include('layouts.site')
