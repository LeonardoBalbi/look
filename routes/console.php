<?php

use App\Services\CobrancaRecorrenteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command(
    'locx:gerar-cobrancas-recorrentes
        {--ate= : Data limite em YYYY-MM-DD}
        {--dias-antecedencia= : Quantos dias a frente gerar}
        {--max-por-contrato= : Limite de cobrancas criadas por contrato nesta execucao}
        {--gerar-pix : Gera PIX no gateway configurado}
        {--enviar-whatsapp : Envia WhatsApp depois de criar a cobranca}
        {--enviar-email : Envia e-mail depois de criar a cobranca}
        {--dry-run : Simula sem gravar nada e sem chamar APIs externas}',
    function (): int {
        $diasAntecedencia = $this->option('dias-antecedencia');
        $ate = $this->option('ate')
            ? Carbon::parse($this->option('ate'))
            : today()->addDays($diasAntecedencia !== null ? (int) $diasAntecedencia : (int) config('locx.recorrencia.dias_antecedencia', 0));

        $resultado = app(CobrancaRecorrenteService::class)->gerar(
            ate: $ate,
            dryRun: (bool) $this->option('dry-run'),
            gerarPix: (bool) $this->option('gerar-pix'),
            enviarWhatsApp: (bool) $this->option('enviar-whatsapp'),
            enviarEmail: (bool) $this->option('enviar-email'),
            maxPorContrato: (int) ($this->option('max-por-contrato') ?: config('locx.recorrencia.max_por_contrato', 12)),
        );

        $modo = $resultado['dry_run'] ? 'SIMULACAO' : 'EXECUCAO REAL';
        $this->info("LocX recorrencia - {$modo}");
        $this->line('Data limite: '.$resultado['ate']);
        $this->line('Contratos analisados: '.$resultado['contratos']);
        $this->line('Cobrancas novas: '.$resultado['criadas']);
        $this->line('Cobrancas ja existentes: '.$resultado['existentes']);
        $this->line('PIX gerados: '.$resultado['pix_gerados']);
        $this->line('WhatsApp enviados: '.$resultado['whatsapp_enviados']);
        $this->line('E-mails enviados: '.$resultado['emails_enviados']);

        if ($resultado['itens']) {
            $this->table(
                ['Contrato', 'Cliente', 'Vencimento', 'Valor'],
                collect($resultado['itens'])->take(20)->map(fn (array $item) => [
                    $item['contrato_id'],
                    $item['cliente'] ?: '-',
                    $item['vencimento'],
                    number_format($item['valor'], 2, ',', '.'),
                ])->all()
            );
        }

        foreach ($resultado['erros'] as $erro) {
            $this->warn($erro);
        }

        return empty($resultado['erros']) ? 0 : 1;
    }
)->purpose('Gera cobranças recorrentes dos contratos ativos da LocX.');

$opcoesAgendadas = [];
if (config('locx.recorrencia.gerar_pix')) {
    $opcoesAgendadas[] = '--gerar-pix';
}
if (config('locx.recorrencia.enviar_whatsapp')) {
    $opcoesAgendadas[] = '--enviar-whatsapp';
}
if (config('locx.recorrencia.enviar_email')) {
    $opcoesAgendadas[] = '--enviar-email';
}
$opcoesAgendadas[] = '--dias-antecedencia='.(int) config('locx.recorrencia.dias_antecedencia', 0);
$opcoesAgendadas[] = '--max-por-contrato='.(int) config('locx.recorrencia.max_por_contrato', 12);

Schedule::command('locx:gerar-cobrancas-recorrentes '.implode(' ', $opcoesAgendadas))
    ->dailyAt(config('locx.recorrencia.horario', '07:00'))
    ->withoutOverlapping()
    ->when(fn () => (bool) config('locx.recorrencia.ativa'));
