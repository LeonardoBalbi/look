<?php

namespace App\Mail;

use App\Models\Cobranca;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CobrancaCriadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Cobranca $cobranca) {}

    public function build(): self
    {
        $this->cobranca->loadMissing('cliente', 'contrato.motocicleta');

        return $this
            ->subject('Cobranca LocX #'.$this->cobranca->id)
            ->view('emails.cobranca_criada')
            ->with(['cobranca' => $this->cobranca]);
    }
}
