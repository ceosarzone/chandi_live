<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SponsoredPlacement;
use App\Models\Subscription;
use App\Services\Audit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ActivateSubscription
{
    public function handle(Invoice $invoice, array $v): Subscription
    {
        Gate::authorize('manage', 'commercial');

        return DB::transaction(function () use ($invoice, $v) {
            $i = Invoice::with('plan')->lockForUpdate()->findOrFail($invoice->id);
            if ($i->status === 'paid' || $i->payment()->exists()) {
                throw ValidationException::withMessages(['reference' => 'This invoice is already confirmed.']);
            }
            if (bccomp($v['amount'], $i->amount, 8) !== 0) {
                throw ValidationException::withMessages(['amount' => 'Payment must equal the invoiced amount.']);
            }
            Payment::create(['invoice_id' => $i->id, 'recorded_by' => auth()->id(), 'amount' => $v['amount'], 'reference' => $v['reference'], 'confirmed_at' => now(), 'notes' => $v['notes']]);
            $i->update(['status' => 'paid']);
            $existing = Subscription::where('dealer_profile_id', $i->dealer_profile_id)->where('subscription_plan_id', $i->subscription_plan_id)->where('status', 'active')->max('expires_at');
            $start = $existing && Carbon::parse($existing)->gt(now()) ? Carbon::parse($existing) : now();
            $s = Subscription::create(['dealer_profile_id' => $i->dealer_profile_id, 'subscription_plan_id' => $i->subscription_plan_id, 'starts_at' => $start, 'expires_at' => $start->copy()->addMonthsNoOverflow((int) $v['months']), 'activated_by' => auth()->id()]);
            if ($i->plan->sponsored) {
                SponsoredPlacement::create(['dealer_profile_id' => $i->dealer_profile_id, 'subscription_id' => $s->id, 'starts_at' => $s->starts_at, 'expires_at' => $s->expires_at]);
            }
            Audit::record('payment.confirmed', $i, ['amount' => $v['amount'], 'reference' => $v['reference']]);

            return $s;
        });
    }
}
