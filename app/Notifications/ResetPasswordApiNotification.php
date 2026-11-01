<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordApiNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = rtrim(config('app.frontend_url'), '/')
            . "/reset?token={$this->token}&email=" . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Redefinição de senha')
            ->line('Você solicitou a redefinição de senha.')
            ->action('Redefinir senha', $url)
            ->line('Se você não solicitou, ignore este e-mail.');
    }
}
