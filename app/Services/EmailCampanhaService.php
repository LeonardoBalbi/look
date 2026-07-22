<?php

namespace App\Services;

use App\Models\Cobranca;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailCampanhaService
{
    public function enviar(Cobranca $cobranca, string $mensagem, string $assunto = 'Aviso de cobrança'): array
    {
        $cobranca->loadMissing('cliente');
        $email = trim((string) $cobranca->cliente?->email);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'erro' => 'Cliente sem e-mail válido.'];
        }

        $html = '<div style="font-family:Arial,sans-serif;line-height:1.55;color:#172033;max-width:640px;margin:auto">'
            .'<div style="background:#0f766e;color:#fff;padding:18px 22px;border-radius:12px 12px 0 0"><strong>LocX · Central de Cobranças</strong></div>'
            .'<div style="border:1px solid #dbe6ea;border-top:0;padding:24px;border-radius:0 0 12px 12px">'
            .nl2br(e($mensagem)).'</div></div>';

        try {
            Mail::html($html, function ($mail) use ($email, $assunto): void {
                $mail->to($email)->subject($assunto);
            });
        } catch (Throwable $exception) {
            Log::warning('Falha ao enviar e-mail de campanha de cobrança.', [
                'cobranca_id' => $cobranca->id,
                'cliente_id' => $cobranca->cliente_id,
                'email' => $email,
                'erro' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'erro' => 'Falha no servidor de e-mail: '.$exception->getMessage()];
        }

        return ['ok' => true, 'email' => $email];
    }
}
