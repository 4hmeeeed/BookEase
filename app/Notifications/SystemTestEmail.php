<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent from the owner's System page to prove the server can send email. */
class SystemTestEmail extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test email from BookEase · Labnasan Resort')
            ->greeting('It works!')
            ->line('BookEase can send email from this server, so guests will receive their receipts and booking updates.')
            ->line('Sent from the System page on '.now()->format('M j, Y \a\t g:i A').'.');
    }
}
