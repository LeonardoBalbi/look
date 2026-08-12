<?php

namespace App\Notifications;

use App\Support\RentalSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Recuperação de senha — '.RentalSupport::productName())
            ->greeting('Olá, '.$notifiable->nome.'.')
            ->line('Recebemos uma solicitação para redefinir a senha do seu usuário.')
            ->action('Criar nova senha', $url)
            ->line('Este link expira em 60 minutos e pode ser usado apenas uma vez.')
            ->line('Se você não fez essa solicitação, ignore esta mensagem.');
    }
}
