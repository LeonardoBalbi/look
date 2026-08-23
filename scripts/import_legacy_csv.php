<?php

declare(strict_types=1);

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Loja;
use App\Models\Motocicleta;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);
$basePath = dirname(__DIR__);

function legacyRows(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException("Nao foi possivel abrir {$path}.");
    }

    $header = fgetcsv($handle, 0, ',', '"', '');
    if (! is_array($header)) {
        fclose($handle);
        throw new RuntimeException("CSV sem cabecalho: {$path}.");
    }

    $header = array_map(static fn ($value) => ltrim((string) $value, "\xEF\xBB\xBF"), $header);
    $rows = [];
    $line = 1;

    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        $line++;
        if ($values === [null] || $values === []) {
            continue;
        }
        if (count($values) !== count($header)) {
            fclose($handle);
            throw new RuntimeException(basename($path).": linha logica {$line} possui ".count($values).' colunas; esperadas '.count($header).'.');
        }
        $rows[] = array_combine($header, $values);
    }

    fclose($handle);

    return $rows;
}

function legacyText(mixed $value): ?string
{
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    if (! mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    if (preg_match('/Ã.|Â.|â€|ƒ/u', $text)) {
        $candidate = @iconv('UTF-8', 'Windows-1252//IGNORE', $text);
        if (is_string($candidate) && mb_check_encoding($candidate, 'UTF-8')) {
            $text = $candidate;
        }
    }

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text) ?: null;
}

function legacyDigits(mixed $value): ?string
{
    $digits = preg_replace('/\D+/', '', (string) $value);

    return $digits !== '' ? $digits : null;
}

function legacyDate(mixed $value): ?string
{
    $value = trim((string) $value);
    if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
        return null;
    }

    return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
        ? "{$matches[1]}-{$matches[2]}-{$matches[3]}"
        : null;
}

function legacyMoney(mixed $value): float
{
    $value = trim((string) $value);
    if ($value === '') {
        return 0.0;
    }

    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
    }

    return round((float) str_replace(',', '.', $value), 2);
}

function legacyAddress(array $row): ?string
{
    $street = legacyText($row['CL_END'] ?? null);
    $number = legacyText($row['CL_NUMERO'] ?? null);
    $complement = legacyText($row['COMPLEMENTO'] ?? null);
    $district = legacyText($row['CL_BAI'] ?? null);
    $city = legacyText($row['CL_CID'] ?? null);
    $state = legacyText($row['CL_UF'] ?? null);
    $zip = legacyText($row['CL_CEP'] ?? null);

    $parts = array_filter([
        trim(implode(', ', array_filter([$street, $number, $complement]))),
        $district,
        trim(implode(' - ', array_filter([$city, $state]))),
        $zip ? 'CEP '.$zip : null,
    ]);

    return $parts ? implode(', ', $parts) : null;
}

function contractStatus(mixed $value): string
{
    return match (mb_strtoupper(trim((string) $value))) {
        'A' => 'ativo',
        'P' => 'pendente',
        'C' => 'cancelado',
        default => 'encerrado',
    };
}

$clientesCsv = legacyRows($basePath.'/clientes_fornecedores.csv');
$motosCsv = legacyRows($basePath.'/veiculos.csv');
$locacoesCsv = legacyRows($basePath.'/locacoes.csv');
$contratosCsv = legacyRows($basePath.'/contratos.csv');

$contratosPorNumero = [];
$clientesReferenciados = [];
foreach ($contratosCsv as $row) {
    $numero = trim((string) ($row['CT_NUMERO'] ?? ''));
    $codigoCliente = trim((string) ($row['CT_CODCLI'] ?? ''));
    if ($numero !== '') {
        $contratosPorNumero[$numero] = $row;
    }
    if ($codigoCliente !== '') {
        $clientesReferenciados[$codigoCliente] = true;
    }
}
$contratosSelecionados = array_values($contratosPorNumero);

$clientesSelecionados = array_values(array_filter(
    $clientesCsv,
    static fn (array $row): bool => mb_strtoupper(trim((string) ($row['CLIENTE'] ?? ''))) === 'S'
        || isset($clientesReferenciados[trim((string) ($row['CL_CODIGO'] ?? ''))])
));

$locacoesPorContrato = [];
foreach ($locacoesCsv as $row) {
    $numero = trim((string) ($row['ME_CTR'] ?? ''));
    if ($numero === '' || ! isset($contratosPorNumero[$numero])) {
        continue;
    }

    $atual = $locacoesPorContrato[$numero] ?? null;
    $statusNovo = mb_strtoupper(trim((string) ($row['STATUS'] ?? '')));
    $statusAtual = mb_strtoupper(trim((string) ($atual['STATUS'] ?? '')));
    $dataNova = legacyDate($row['ME_D_SAIDA'] ?? null) ?? '';
    $dataAtual = $atual ? (legacyDate($atual['ME_D_SAIDA'] ?? null) ?? '') : '';

    if ($atual === null || ($statusNovo === 'A' && $statusAtual !== 'A') || ($statusNovo === $statusAtual && $dataNova > $dataAtual)) {
        $locacoesPorContrato[$numero] = $row;
    }
}

$placasCsv = [];
foreach ($motosCsv as $row) {
    $placa = mb_strtoupper(trim((string) ($row['VE_PLACA'] ?? '')));
    if ($placa !== '') {
        $placasCsv[$placa] = true;
    }
}

$clientesCsvIds = array_fill_keys(array_map(static fn (array $row) => trim((string) $row['CL_CODIGO']), $clientesSelecionados), true);
$contratosValidos = 0;
$contratosSemVinculo = [];
foreach ($contratosSelecionados as $row) {
    $numero = trim((string) ($row['CT_NUMERO'] ?? ''));
    $clienteOk = isset($clientesCsvIds[trim((string) ($row['CT_CODCLI'] ?? ''))]);
    $placa = mb_strtoupper(trim((string) ($locacoesPorContrato[$numero]['ME_PLACA'] ?? '')));
    if ($clienteOk && isset($placasCsv[$placa])) {
        $contratosValidos++;
    } else {
        $contratosSemVinculo[] = $numero ?: '(sem numero)';
    }
}

echo ($apply ? "IMPORTACAO REAL\n" : "SIMULACAO - nenhuma gravacao sera feita\n");
echo 'Clientes selecionados: '.count($clientesSelecionados)."\n";
echo 'Motos: '.count($motosCsv)."\n";
echo 'Locacoes usadas para vinculo: '.count($locacoesPorContrato)."\n";
echo "Contratos validos: {$contratosValidos}\n";
echo 'Contratos sem vinculo: '.count($contratosSemVinculo)."\n";

if ($contratosSemVinculo) {
    echo 'Primeiros contratos sem vinculo: '.implode(', ', array_slice($contratosSemVinculo, 0, 10))."\n";
}

if (! $apply) {
    echo "Execute com --apply para gravar no banco.\n";
    exit($contratosSemVinculo ? 2 : 0);
}

$loja = Loja::query()->orderBy('id')->first();
if (! $loja) {
    throw new RuntimeException('Nenhuma loja cadastrada. Cadastre a loja antes de importar.');
}

$backupDirectory = storage_path('app/import-backups');
if (! is_dir($backupDirectory) && ! mkdir($backupDirectory, 0750, true) && ! is_dir($backupDirectory)) {
    throw new RuntimeException('Nao foi possivel criar a pasta de backup.');
}

$backupPath = $backupDirectory.'/before-csv-import-'.date('Ymd-His').'.json';
$backup = [
    'database' => (string) config('database.connections.'.config('database.default').'.database'),
    'created_at' => date(DATE_ATOM),
    'clientes' => DB::table('clientes')->orderBy('id')->get()->all(),
    'motocicletas' => DB::table('motocicletas')->orderBy('id')->get()->all(),
    'contratos' => DB::table('contratos')->orderBy('id')->get()->all(),
];
if (file_put_contents($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Falha ao gravar o backup antes da importacao.');
}

$created = ['clientes' => 0, 'motos' => 0, 'contratos' => 0];
$updated = ['clientes' => 0, 'motos' => 0, 'contratos' => 0];

DB::transaction(function () use ($clientesSelecionados, $motosCsv, $contratosSelecionados, $contratosPorNumero, $locacoesPorContrato, $loja, &$created, &$updated): void {
    $clienteIds = [];
    foreach ($clientesSelecionados as $row) {
        $cpf = legacyDigits($row['CL_CGC'] ?? null);
        $cliente = Cliente::query()->firstOrNew(['cpf' => $cpf]);
        $cliente->exists ? $updated['clientes']++ : $created['clientes']++;
        $cliente->fill([
            'loja_id' => $loja->id,
            'nome' => legacyText($row['CL_NOME'] ?? null) ?? 'Cliente sem nome',
            'cpf' => $cpf,
            'rg' => legacyText($row['CL_RG'] ?? null),
            'cnh' => legacyText($row['CL_CNH_REG'] ?? null) ?? legacyText($row['CL_CNH'] ?? null),
            'endereco' => legacyAddress($row),
            'telefone' => legacyDigits($row['CL_FONE'] ?? null),
            'whatsapp' => legacyDigits($row['CL_CELULAR'] ?? null),
            'email' => filter_var(trim((string) ($row['CL_EMAIL'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null,
            'status' => mb_strtoupper(trim((string) ($row['ATIVO'] ?? 'S'))) === 'N' ? 'bloqueado' : 'ativo',
        ]);
        $cliente->save();
        $clienteIds[trim((string) $row['CL_CODIGO'])] = $cliente->id;
    }

    $contratosAtivos = [];
    foreach ($locacoesPorContrato as $numero => $locacao) {
        $contratoCsv = $contratosPorNumero[$numero] ?? null;
        if ($contratoCsv && contractStatus($contratoCsv['CT_STATUS'] ?? null) === 'ativo') {
            $contratosAtivos[mb_strtoupper(trim((string) ($locacao['ME_PLACA'] ?? '')))] = true;
        }
    }

    $motoIds = [];
    foreach ($motosCsv as $row) {
        $placa = mb_strtoupper(trim((string) ($row['VE_PLACA'] ?? '')));
        if ($placa === '') {
            continue;
        }
        $moto = Motocicleta::query()->firstOrNew(['placa' => $placa]);
        $moto->exists ? $updated['motos']++ : $created['motos']++;
        $ativo = mb_strtoupper(trim((string) ($row['VE_ATIVO'] ?? 'S'))) !== 'N';
        $ano = (int) (legacyText($row['ANO'] ?? null) ?? substr((string) ($row['VE_ANO'] ?? ''), 0, 4));
        $moto->fill([
            'loja_id' => $loja->id,
            'modelo' => 'Modelo '.(legacyText($row['VE_MODELO'] ?? null) ?? 'nao informado'),
            'marca' => legacyText($row['VE_MARCA'] ?? null),
            'ano' => $ano >= 1900 && $ano <= 2100 ? $ano : null,
            'placa' => $placa,
            'cor' => legacyText($row['VE_COR'] ?? null),
            'renavam' => legacyDigits($row['VE_RENAVAM'] ?? null),
            'chassi' => legacyText($row['VE_CHASSI'] ?? null),
            'data_aquisicao' => legacyDate($row['VE_DATACOM'] ?? null),
            'seguro' => legacyText($row['APOLICE_ID'] ?? null),
            'rastreador' => legacyText($row['RASTREADOR'] ?? null),
            'status_operacional' => ! $ativo ? 'inativo' : (isset($contratosAtivos[$placa]) ? 'alugada' : 'disponivel'),
        ]);
        $moto->save();
        $motoIds[$placa] = $moto->id;
    }

    foreach ($contratosSelecionados as $row) {
        $numero = trim((string) ($row['CT_NUMERO'] ?? ''));
        $locacao = $locacoesPorContrato[$numero] ?? null;
        $clienteId = $clienteIds[trim((string) ($row['CT_CODCLI'] ?? ''))] ?? null;
        $placa = mb_strtoupper(trim((string) ($locacao['ME_PLACA'] ?? '')));
        $motoId = $motoIds[$placa] ?? null;
        if (! $clienteId || ! $motoId || ! $locacao) {
            continue;
        }

        $dataInicio = legacyDate($row['CT_DATA_I'] ?? null)
            ?? legacyDate($row['CT_DATA_INI'] ?? null)
            ?? legacyDate($locacao['ME_D_SAIDA'] ?? null);
        if (! $dataInicio) {
            continue;
        }

        $contrato = Contrato::query()
            ->where('historico_alteracoes', 'like', '%"contrato_legado":"'.$numero.'"%')
            ->first() ?? new Contrato();
        $contrato->exists ? $updated['contratos']++ : $created['contratos']++;
        $contrato->fill([
            'cliente_id' => $clienteId,
            'motocicleta_id' => $motoId,
            'loja_id' => $loja->id,
            'data_inicio' => $dataInicio,
            'data_fim' => legacyDate($row['CT_DATA_F'] ?? null) ?? legacyDate($locacao['ME_D_PREV'] ?? null),
            'valor_contratado' => legacyMoney($locacao['ME_VALUNIT'] ?? null),
            'forma_cobranca' => 'semanal',
            'cobranca_automatica' => false,
            'proxima_cobranca_em' => null,
            'status' => contractStatus($row['CT_STATUS'] ?? null),
            'historico_alteracoes' => json_encode([
                'origem' => 'csv_legado',
                'contrato_legado' => $numero,
                'locacao_legada' => legacyText($locacao['ME_NUMERO'] ?? null),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $contrato->save();
    }
});

echo "Backup: {$backupPath}\n";
echo "Criados - clientes: {$created['clientes']}; motos: {$created['motos']}; contratos: {$created['contratos']}\n";
echo "Atualizados - clientes: {$updated['clientes']}; motos: {$updated['motos']}; contratos: {$updated['contratos']}\n";
echo 'Totais no banco - clientes: '.Cliente::count().'; motos: '.Motocicleta::count().'; contratos: '.Contrato::count()."\n";
