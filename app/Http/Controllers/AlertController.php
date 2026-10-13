<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlertRequest;
use App\Models\PriceAlert;
use App\Services\Analytics;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AlertController extends Controller
{
    public function index(Request $r)
    {
        return view('account.alerts', ['alerts' => PriceAlert::where('user_id', $r->user()->id)->latest()->paginate(20)]);
    }

    public function store(AlertRequest $r)
    {
        abort_if(PriceAlert::where('user_id', $r->user()->id)->where('active', true)->count() >= 20, 422, 'Maximum 20 active alerts.');
        PriceAlert::create($r->validated() + ['user_id' => $r->user()->id, 'unsubscribe_token' => Str::random(64)]);
        app(Analytics::class)->track('alert_registration');

        return back()->with('status', 'Alert saved. Only fresh provider observations can trigger delivery.');
    }

    public function delete(Request $r, PriceAlert $alert)
    {
        abort_unless($alert->user_id === $r->user()->id, 403);
        $alert->update(['active' => false]);

        return back()->with('status', 'Alert disabled.');
    }

    public function unsubscribe(string $token)
    {
        $alert = PriceAlert::where('unsubscribe_token', $token)->firstOrFail();

        return view('account.unsubscribe', compact('alert'));
    }

    public function confirmUnsubscribe(string $token)
    {
        PriceAlert::where('unsubscribe_token', $token)->firstOrFail()->update(['active' => false, 'email_enabled' => false]);

        return redirect('/')->with('status','Price alert unsubscribed.');
    }
}
