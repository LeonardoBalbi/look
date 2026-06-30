<?php

namespace App\Services;

use App\Mail\PagamentoConfirmadoMail;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailPagamentoService
{
    public function enviarConfirmacao(Pagamento $pagamento): array
    {
        $pagamento->loadMissing('cobranca.cliente');
        $email = trim((string) $pagamento->cobranca?->cliente?->email);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'erro' => 'Cliente sem e-mail valido.'];
        }

        try {
            Mail::to($email)->send(new PagamentoConfirmadoMail($pagamento));
        } catch (Throwable $exception) {
            Log::warning('Falha ao enviar e-mail de confirmacao de pagamento.', [
                'pagamento_id' => $pagamento->id,
                'cobranca_id' => $pagamento->cobranca_id,
                'email' => $email,
                'erro' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'erro' => 'Falha no servidor de e-mail: '.$exception->getMessage()];
        }

        return ['ok' => true, 'email' => $email];
    }
}
