<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformMessage extends Notification
{
    public function __construct(public string $subject, public string $body, public ?string $link = null) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $m = (new MailMessage)->subject($this->subject)->line($this->body);

        return $this->link ? $m->action('Open CHANDI.LIVE', $this->link) : $m;
    }
}
