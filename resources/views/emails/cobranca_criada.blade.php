<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Cobranca LocX</title>
</head>
<body style="font-family: Arial, sans-serif; color: #172033; line-height: 1.5;">
    <h2 style="margin-bottom: 8px;">Cobranca LocX #{{ $cobranca->id }}</h2>
    <p>Ola, {{ $cobranca->cliente?->nome }}.</p>
    <p>Uma cobranca foi gerada para seu contrato LocX.</p>

    <p>
        <strong>Vencimento:</strong> {{ $cobranca->vencimento?->format('d/m/Y') }}<br>
        <strong>Valor:</strong> {{ \App\Support\Locx::moeda($cobranca->valor_principal) }}<br>
        <strong>Moto:</strong> {{ $cobranca->contrato?->motocicleta?->placa ?: 'nao informada' }}
    </p>

    @if ($cobranca->pix_copia_cola)
        <p><strong>PIX copia e cola:</strong></p>
        <p style="word-break: break-all; background: #f4f6f8; padding: 12px; border-radius: 6px;">{{ $cobranca->pix_copia_cola }}</p>
    @endif

    <p>Se o pagamento ja foi realizado, desconsidere esta mensagem.</p>
</body>
</html>
