<?php

namespace App\Services;

use App\Mail\CobrancaCriadaMail;
use App\Models\Cobranca;
use Illuminate\Support\Facades\Mail;

class EmailCobrancaService
{
    public function enviarCobranca(Cobranca $cobranca): array
    {
        $cobranca->loadMissing('cliente');
        $email = trim((string) $cobranca->cliente?->email);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'erro' => 'Cliente sem e-mail valido.'];
        }

        Mail::to($email)->send(new CobrancaCriadaMail($cobranca));

        return ['ok' => true, 'email' => $email];
    }
}
