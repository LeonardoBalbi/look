<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | {{ $branding['product_name'] }}</title>
    <link rel="stylesheet" href="{{ \App\Support\RentalSupport::asset('assets/css/style.css') }}">
</head>
<body class="login-body">
<main class="login-card error-card">
    <x-brand class="login-brand" />
    <span class="error-code">{{ $code }}</span>
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    <a class="btn" href="{{ url('/') }}">Voltar ao início</a>
    @if($branding['support_email'])<small>Suporte: {{ $branding['support_email'] }}</small>@endif
</main>
</body>
</html>
