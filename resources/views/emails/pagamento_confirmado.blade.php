<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Pagamento confirmado LocX</title>
</head>
<body style="font-family: Arial, sans-serif; color: #172033; line-height: 1.5;">
    @php($cobranca = $pagamento->cobranca)
    <h2 style="margin-bottom: 8px;">Pagamento confirmado</h2>
    <p>Ola, {{ $cobranca->cliente?->nome }}.</p>
    <p>Recebemos a confirmacao do pagamento e a baixa foi registrada no sistema LocX.</p>

    <p>
        <strong>Cobranca:</strong> #{{ $cobranca->id }}<br>
        <strong>Valor pago:</strong> {{ \App\Support\Locx::moeda($pagamento->valor) }}<br>
        <strong>Forma:</strong> {{ strtoupper($pagamento->forma) }}<br>
        <strong>Data da baixa:</strong> {{ $pagamento->pago_em?->format('d/m/Y H:i') }}<br>
        <strong>Status:</strong> {{ $cobranca->status }}<br>
        <strong>Moto:</strong> {{ $cobranca->contrato?->motocicleta?->placa ?: 'nao informada' }}
    </p>

    <p>Obrigado. Guarde este e-mail como comprovante da confirmacao registrada no sistema.</p>
</body>
</html>
