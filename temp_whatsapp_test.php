<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$config = App\Models\WhatsappConfig::query()->first();
$number = '24993252098';
$payload = [
    'number' => $number,
    'text' => 'Teste de envio LocX',
    'textMessage' => [
        'text' => 'Teste de envio LocX',
    ],
];

$baseUrl = rtrim((string) $config->evolution_base_url, '/');
$request = Illuminate\Support\Facades\Http::acceptJson()
    ->withHeaders(['apikey' => (string) $config->evolution_api_key])
    ->timeout(15);
if (! config('locx.gateway_verify_ssl', true)) {
    $request = $request->withoutVerifying();
}

$response = $request->post($baseUrl.'/message/sendText/'.$config->evolution_instance, array_filter([
    'json' => $payload,
]));

echo "base={$baseUrl}\n";
echo "instance={$config->evolution_instance}\n";
echo "status={$response->status()}\n";
echo "body={$response->body()}\n";
