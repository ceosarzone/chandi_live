<?php

namespace App\Jobs;

use App\Notifications\PlatformMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendPlatformMail implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300];

    public function __construct(public string $email, public string $subject, public string $body, public ?string $link = null) {}

    public function handle(): void
    {
        Notification::route('mail', $this->email)->notify(new PlatformMessage($this->subject, $this->body, $this->link));
    }
}
