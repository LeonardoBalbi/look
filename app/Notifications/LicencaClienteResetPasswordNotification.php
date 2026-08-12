<?php

namespace App\Notifications;

use App\Support\RentalSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LicencaClienteResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('licenca-cliente.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Acesso à sua licença — '.RentalSupport::productName())
            ->greeting('Olá, '.$notifiable->nome.'.')
            ->line('Use o botão abaixo para cadastrar uma nova senha no portal Minha Licença.')
            ->action('Criar nova senha', $url)
            ->line('O link expira em 60 minutos e pode ser utilizado somente uma vez.')
            ->line('Se você não solicitou a alteração, ignore esta mensagem.');
    }
}
