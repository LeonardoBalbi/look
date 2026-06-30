<?php

namespace App\Mail;

use App\Models\Pagamento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PagamentoConfirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Pagamento $pagamento) {}

    public function build(): self
    {
        $this->pagamento->loadMissing('cobranca.cliente', 'cobranca.contrato.motocicleta');

        return $this
            ->subject('Pagamento confirmado LocX #'.$this->pagamento->cobranca_id)
            ->view('emails.pagamento_confirmado')
            ->with(['pagamento' => $this->pagamento]);
    }
}
