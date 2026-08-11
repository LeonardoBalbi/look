<?php

use App\Services\CobrancaRecorrenteService;
use App\Services\CobrancaCampanhaService;
use App\Services\CrmAutomationService;
use App\Services\LicencaService;
use App\Support\RentalSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command(
    'rental:validar-licenca {--json : Retorna o resultado em JSON}',
    function (): int {
        $resultado = app(LicencaService::class)->validarOnline();

        if ($this->option('json')) {
            $this->line(json_encode($resultado, JSON_UNESCAPED_UNICODE));
        } else {
            $ok = (bool) ($resultado['ok'] ?? false);
            $status = (string) ($resultado['status'] ?? 'indefinido');
            $mensagem = $resultado['erro']
                ?? data_get($resultado, 'resposta.mensagem')
                ?? data_get($resultado, 'resposta.message')
                ?? 'Validacao concluida.';

            $titulo = 'Licença '.RentalSupport::productName().': '.$status;
            $ok ? $this->info($titulo) : $this->error($titulo);
            $this->line($mensagem);
        }

        return (bool) ($resultado['ok'] ?? false) ? 0 : 1;
    }
)->purpose('Valida a licença comercial da aplicação no portal on-line.');

Artisan::command(
    'rental:gerar-cobrancas-recorrentes
        {--ate= : Data limite em YYYY-MM-DD}
        {--dias-antecedencia= : Quantos dias a frente gerar}
        {--max-por-contrato= : Limite de cobrancas criadas por contrato nesta execucao}
        {--gerar-pix : Gera PIX no gateway configurado}
        {--enviar-whatsapp : Envia WhatsApp depois de criar a cobranca}
        {--enviar-email : Envia e-mail depois de criar a cobranca}
        {--enviar-telegram : Envia Telegram depois de criar a cobranca}
        {--dry-run : Simula sem gravar nada e sem chamar APIs externas}',
    function (): int {
        $diasAntecedencia = $this->option('dias-antecedencia');
        $ate = $this->option('ate')
            ? Carbon::parse($this->option('ate'))
            : today()->addDays($diasAntecedencia !== null ? (int) $diasAntecedencia : (int) config('rental.recorrencia.dias_antecedencia', 0));

        $resultado = app(CobrancaRecorrenteService::class)->gerar(
            ate: $ate,
            dryRun: (bool) $this->option('dry-run'),
            gerarPix: (bool) $this->option('gerar-pix'),
            enviarWhatsApp: (bool) $this->option('enviar-whatsapp'),
            enviarEmail: (bool) $this->option('enviar-email'),
            enviarTelegram: (bool) $this->option('enviar-telegram'),
            maxPorContrato: (int) ($this->option('max-por-contrato') ?: config('rental.recorrencia.max_por_contrato', 12)),
        );

        $modo = $resultado['dry_run'] ? 'SIMULACAO' : 'EXECUCAO REAL';
        $this->info(RentalSupport::productName()." - recorrência - {$modo}");
        $this->line('Data limite: '.$resultado['ate']);
        $this->line('Contratos analisados: '.$resultado['contratos']);
        $this->line('Cobrancas novas: '.$resultado['criadas']);
        $this->line('Cobrancas ja existentes: '.$resultado['existentes']);
        $this->line('PIX gerados: '.$resultado['pix_gerados']);
        $this->line('WhatsApp enviados: '.$resultado['whatsapp_enviados']);
        $this->line('E-mails enviados: '.$resultado['emails_enviados']);
        $this->line('Telegram enviados: '.$resultado['telegram_enviados']);

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
)->purpose('Gera cobranças recorrentes dos contratos ativos.');

Artisan::command(
    'rental:sincronizar-crm {--dry-run : Simula sem criar tarefas}',
    function (): int {
        $resultado = app(CrmAutomationService::class)->sincronizarAtrasos(dryRun: (bool) $this->option('dry-run'));
        $modo = $resultado['dry_run'] ? 'SIMULACAO' : 'EXECUCAO REAL';

        $this->info(RentalSupport::productName()." - CRM - {$modo}");
        $this->line('Data: '.$resultado['data']);
        $this->line('Cobrancas analisadas: '.$resultado['cobrancas_analisadas']);
        $this->line('Tarefas novas: '.$resultado['tarefas_criadas']);
        $this->line('Tarefas ja existentes: '.$resultado['tarefas_existentes']);

        if ($resultado['itens']) {
            $this->table(
                ['Cliente', 'Cobranca', 'Tarefa', 'Dias atraso'],
                collect($resultado['itens'])->take(20)->map(fn (array $item) => [
                    $item['cliente'] ?: '-',
                    $item['cobranca_id'],
                    $item['titulo'],
                    $item['dias_atraso'],
                ])->all()
            );
        }

        return 0;
    }
)->purpose('Cria tarefas automaticas do CRM para cobrancas em atraso.');

Artisan::command(
    'rental:disparar-crm-agendado {--dry-run : Simula sem enviar WhatsApp/Telegram}',
    function (): int {
        $resultado = app(CrmAutomationService::class)->dispararTarefasAgendadas(dryRun: (bool) $this->option('dry-run'));
        $modo = $resultado['dry_run'] ? 'SIMULACAO' : 'EXECUCAO REAL';

        $this->info(RentalSupport::productName()." - disparos do CRM - {$modo}");
        $this->line('Data/hora: '.$resultado['data_hora']);
        $this->line('Tarefas analisadas: '.$resultado['tarefas_analisadas']);
        $this->line('WhatsApp enviados: '.$resultado['whatsapp_enviados']);
        $this->line('Telegram enviados: '.($resultado['telegram_enviados'] ?? 0));
        $this->line('Sem cobranca aberta: '.$resultado['sem_cobranca']);

        foreach ($resultado['erros'] as $erro) {
            $this->warn($erro);
        }

        return empty($resultado['erros']) ? 0 : 1;
    }
)->purpose('Dispara WhatsApp e Telegram das tarefas do CRM quando o prazo agendado chegar.');

Artisan::command(
    'rental:conciliar-pix {--limite= : Quantidade maxima de cobrancas consultadas}',
    function (): int {
        $resultado = app(\App\Services\PixGatewayService::class)->conciliarPendentes(
            (int) ($this->option('limite') ?: config('rental.pix.conciliacao_limite', 50))
        );

        $this->info(RentalSupport::productName().' - conciliação automática de PIX');
        $this->line('Cobrancas analisadas: '.$resultado['analisadas']);
        $this->line('Baixas realizadas: '.$resultado['baixadas']);
        $this->line('Ainda pendentes: '.$resultado['pendentes']);

        foreach ($resultado['erros'] as $erro) {
            $this->warn($erro);
        }

        return empty($resultado['erros']) ? 0 : 1;
    }
)->purpose('Consulta PagBank/Asaas e baixa automaticamente PIX pagos quando o webhook nao chegou.');

Artisan::command(
    'rental:processar-campanhas-cobranca {--limite-campanhas=10} {--limite-itens=200}',
    function (): int {
        $resultado = app(CobrancaCampanhaService::class)->processarAgendadas(
            (int) $this->option('limite-campanhas'),
            (int) $this->option('limite-itens')
        );

        $this->info('Campanhas de cobrança processadas: '.$resultado['campanhas']);
        foreach ($resultado['resultados'] as $item) {
            $this->line('#'.$item['id'].' '.$item['nome'].' · '.$item['status'].' · '.$item['enviados'].' enviados · '.$item['falhas'].' falhas');
        }

        return 0;
    }
)->purpose('Processa campanhas de cobrança por WhatsApp, e-mail e Telegram.');

$opcoesAgendadas = [];
if (config('rental.recorrencia.gerar_pix')) {
    $opcoesAgendadas[] = '--gerar-pix';
}
if (config('rental.recorrencia.enviar_whatsapp')) {
    $opcoesAgendadas[] = '--enviar-whatsapp';
}
if (config('rental.recorrencia.enviar_email')) {
    $opcoesAgendadas[] = '--enviar-email';
}
if (config('rental.recorrencia.enviar_telegram')) {
    $opcoesAgendadas[] = '--enviar-telegram';
}
$opcoesAgendadas[] = '--dias-antecedencia='.(int) config('rental.recorrencia.dias_antecedencia', 0);
$opcoesAgendadas[] = '--max-por-contrato='.(int) config('rental.recorrencia.max_por_contrato', 12);

Schedule::command('rental:gerar-cobrancas-recorrentes '.implode(' ', $opcoesAgendadas))
    ->dailyAt(config('rental.recorrencia.horario', '07:00'))
    ->withoutOverlapping()
    ->when(fn () => (bool) config('rental.recorrencia.ativa'));

Schedule::command('rental:sincronizar-crm')
    ->dailyAt(config('rental.crm.automacoes_horario', '07:15'))
    ->withoutOverlapping()
    ->when(fn () => (bool) config('rental.crm.automacoes_ativas', true));

Schedule::command('rental:disparar-crm-agendado')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => (bool) config('rental.crm.automacoes_ativas', true));

Schedule::command('rental:conciliar-pix')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => (bool) config('rental.pix.conciliacao_ativa', true));

Schedule::command('rental:processar-campanhas-cobranca')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('rental:validar-licenca')
    ->hourly()
    ->withoutOverlapping()
    ->when(fn () => app(LicencaService::class)->controleAtivo());
