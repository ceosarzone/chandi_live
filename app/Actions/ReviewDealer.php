<?php

namespace App\Actions;

use App\Jobs\SendPlatformMail;
use App\Models\DealerProfile;
use App\Models\DealerVerification;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewDealer
{
    public function handle(DealerProfile $d, array $v): void
    {
        Gate::authorize('review', $d);
        DB::transaction(function () use ($d, $v) {
            $d = DealerProfile::lockForUpdate()->findOrFail($d->id);
            if ($v['decision'] === 'approved' && ! $d->user->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['decision' => 'The dealer must verify their account email before approval.']);
            }
            $verified = $v['decision'] === 'approved' && ($v['verified'] ?? false);
            if ($verified && ! $d->documents()->exists()) {
                throw ValidationException::withMessages(['verified' => 'Upload and review supporting documents before granting a platform review badge.']);
            }
            DealerVerification::create(['dealer_profile_id' => $d->id, 'reviewer_id' => auth()->id(), 'decision' => $v['decision'], 'notes' => $v['notes'], 'verified' => $verified]);
            $d->update(['status' => $v['decision'], 'verified' => $verified, 'review_notes' => $v['notes']]);
            Audit::record('dealer.review', $d, ['decision' => $v['decision'], 'verified' => $verified]);
            SendPlatformMail::dispatch($d->user->email, 'Dealer application updated', 'Your application status is '.$v['decision'].'. '.$v['notes'], url('/dealer'))->afterCommit();
        });
    }
}
