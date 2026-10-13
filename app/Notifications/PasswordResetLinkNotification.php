<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetLinkNotification extends Notification
{
    public function __construct(
        public readonly string $url,
        public readonly int $expiresInMinutes,
        public readonly bool $requestedByAdmin = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your '.config('app.name').' password')
            ->greeting("Hi {$notifiable->username},")
            ->line($this->requestedByAdmin
                ? 'An administrator has sent you a link to choose a new password.'
                : 'We received a request to reset the password for your account.')
            ->action('Choose a new password', $this->url)
            ->line("This link works once and expires in {$this->expiresInMinutes} minutes.")
            ->line('If you did not ask for this, you can ignore this email; your password will not change.');
    }
}
