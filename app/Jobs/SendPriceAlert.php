<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Notifications\PlatformMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendPriceAlert implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300];

    public function __construct(public int $logId, public string $body) {}

    public function handle(): void
    {
        $log = NotificationLog::with(['alert.user', 'snapshot.fx'])->findOrFail($this->logId);
        if (in_array($log->status, ['sent', 'logged'], true)) {
            return;
        }
        $a = $log->alert;
        if ($a->currency === 'PKR' && $log->snapshot->fx->observed_at->lt(now()->subHours(config('market.fx_stale_hours', 48)))) {
            $log->update(['status' => 'cancelled']);
            return;
        }
        if (! $a->active || ! $a->email_enabled || ! $a->user->active || ! $a->user->hasVerifiedEmail() || $a->expires_at?->isPast() || $log->snapshot->is_demo || $log->snapshot->observation_resolution !== 'timestamp' || $log->snapshot->observed_at->lt(now()->subMinutes(config('market.stale_minutes')))) {
            $log->update(['status' => 'cancelled']);

            return;
        }try {
            Notification::send($a->user, new PlatformMessage('Your silver price alert', $this->body, url('/alerts/unsubscribe/'.$a->unsubscribe_token)));
            $logged = config('mail.default') === 'log';
            $log->update(['status' => $logged ? 'logged' : 'sent', 'sent_at' => $logged ? null : now()]);
            if (! $logged) {
                $a->update(['last_notified_at' => now()]);
            }
        } catch (\Throwable $e) {
            $log->update(['status' => 'retrying']);
            throw $e;
        }
    }

    public function failed(?\Throwable $e): void
    {
        NotificationLog::whereKey($this->logId)->update(['status' => 'failed']);
    }
}
