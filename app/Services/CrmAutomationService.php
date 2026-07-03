<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\CrmTarefa;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CrmAutomationService
{
    public function __construct(private readonly WhatsAppService $whatsApp) {}

    public function sincronizarAtrasos(?CarbonInterface $hoje = null, bool $dryRun = false): array
    {
        $hoje ??= today();
        $resultado = [
            'dry_run' => $dryRun,
            'data' => $hoje->toDateString(),
            'cobrancas_analisadas' => 0,
            'tarefas_criadas' => 0,
            'tarefas_existentes' => 0,
            'itens' => [],
        ];

        Cobranca::query()
            ->with('cliente')
            ->whereNotIn('status', ['paga', 'cancelada'])
            ->whereDate('vencimento', '<=', $hoje->copy()->subDay()->toDateString())
            ->orderBy('vencimento')
            ->chunkById(100, function ($cobrancas) use ($hoje, $dryRun, &$resultado): void {
                foreach ($cobrancas as $cobranca) {
                    $resultado['cobrancas_analisadas']++;
                    $diasAtraso = max(0, $cobranca->vencimento->startOfDay()->diffInDays($hoje->copy()->startOfDay()));

                    $primeiroDia = $this->criarTarefaAtraso(
                        cobranca: $cobranca,
                        chave: 'cobranca_atraso_1:'.$cobranca->id,
                        titulo: 'Cobrar cliente',
                        diasAtraso: $diasAtraso,
                        etapa: 'em_cobranca',
                        dryRun: $dryRun,
                    );
                    $this->somarResultado($resultado, $primeiroDia);

                    if ($diasAtraso >= 3) {
                        $terceiroDia = $this->criarTarefaAtraso(
                            cobranca: $cobranca,
                            chave: 'cobranca_atraso_3:'.$cobranca->id,
                            titulo: 'Avisar gerente sobre atraso',
                            diasAtraso: $diasAtraso,
                            etapa: 'recuperacao',
                            dryRun: $dryRun,
                        );
                        $this->somarResultado($resultado, $terceiroDia);
                    }
                }
            });

        return $resultado;
    }

    public function fecharTarefasDeCobranca(Cobranca $cobranca): int
    {
        if ($cobranca->status !== 'paga') {
            return 0;
        }

        return DB::transaction(function () use ($cobranca): int {
            $tarefas = CrmTarefa::query()
                ->where('cliente_id', $cobranca->cliente_id)
                ->where('status', 'aberta')
                ->where(function ($query) use ($cobranca): void {
                    $query
                        ->where('cobranca_id', $cobranca->id)
                        ->orWhere('chave', 'like', '%:'.$cobranca->id);
                })
                ->get();

            foreach ($tarefas as $tarefa) {
                $tarefa->update([
                    'status' => 'concluida',
                    'concluido_em' => now(),
                    'observacao' => trim((string) $tarefa->observacao."\nPagamento confirmado. Tarefa fechada automaticamente."),
                ]);
            }

            if (! $this->clienteTemAtrasoAberto($cobranca)) {
                $cobranca->cliente?->update(['crm_etapa' => 'contrato_ativo']);
            }

            return $tarefas->count();
        });
    }

    public function dispararTarefasAgendadas(?CarbonInterface $agora = null, bool $dryRun = false): array
    {
        $agora ??= now();
        $resultado = [
            'dry_run' => $dryRun,
            'data_hora' => $agora->format('Y-m-d H:i:s'),
            'tarefas_analisadas' => 0,
            'whatsapp_enviados' => 0,
            'sem_cobranca' => 0,
            'erros' => [],
        ];

        CrmTarefa::query()
            ->with('cliente', 'cobranca.cliente', 'cobranca.contrato.motocicleta')
            ->where('status', 'aberta')
            ->whereIn('tipo', ['whatsapp', 'cobranca'])
            ->whereNotNull('prazo_em')
            ->whereNull('disparado_em')
            ->where('prazo_em', '<=', $agora)
            ->orderBy('prazo_em')
            ->limit(100)
            ->get()
            ->each(function (CrmTarefa $tarefa) use ($agora, $dryRun, &$resultado): void {
                $resultado['tarefas_analisadas']++;
                $cobranca = $tarefa->cobranca ?: $this->cobrancaAbertaDoCliente((int) $tarefa->cliente_id);

                if (! $cobranca) {
                    $resultado['sem_cobranca']++;
                    if (! $dryRun) {
                        $tarefa->update([
                            'disparado_em' => $agora,
                            'disparo_status' => 'sem_cobranca',
                            'disparo_erro' => 'Cliente sem cobranca aberta.',
                        ]);
                    }

                    return;
                }

                if ($dryRun) {
                    return;
                }

                if (! $tarefa->cobranca_id) {
                    $tarefa->update(['cobranca_id' => $cobranca->id]);
                }

                $envio = $this->whatsApp->enviarCobranca($cobranca);
                if ($envio['ok'] ?? false) {
                    $resultado['whatsapp_enviados']++;
                    $tarefa->update([
                        'disparado_em' => $agora,
                        'disparo_status' => ($envio['demo'] ?? false) ? 'demo' : 'enviado',
                        'disparo_erro' => null,
                    ]);

                    return;
                }

                $erro = $envio['erro'] ?? 'WhatsApp nao enviado.';
                $resultado['erros'][] = "Tarefa #{$tarefa->id}: {$erro}";
                $tarefa->update([
                    'disparado_em' => $agora,
                    'disparo_status' => 'erro',
                    'disparo_erro' => $erro,
                ]);
            });

        return $resultado;
    }

    private function criarTarefaAtraso(
        Cobranca $cobranca,
        string $chave,
        string $titulo,
        int $diasAtraso,
        string $etapa,
        bool $dryRun
    ): array {
        if (CrmTarefa::where('chave', $chave)->exists()) {
            return ['status' => 'existente'];
        }

        if (! $dryRun) {
            CrmTarefa::create([
                'cliente_id' => $cobranca->cliente_id,
                'cobranca_id' => $cobranca->id,
                'titulo' => $titulo,
                'tipo' => 'cobranca',
                'chave' => $chave,
                'status' => 'aberta',
                'prazo_em' => now(),
                'observacao' => 'Cobranca #'.$cobranca->id.' vencida ha '.$diasAtraso.' dia(s).',
                'criado_em' => now(),
            ]);

            $cobranca->cliente?->update(['crm_etapa' => $etapa]);
        }

        return [
            'status' => 'criada',
            'item' => [
                'cliente' => $cobranca->cliente?->nome,
                'cobranca_id' => $cobranca->id,
                'titulo' => $titulo,
                'dias_atraso' => $diasAtraso,
            ],
        ];
    }

    private function somarResultado(array &$resultado, array $item): void
    {
        if (($item['status'] ?? null) === 'existente') {
            $resultado['tarefas_existentes']++;

            return;
        }

        if (($item['status'] ?? null) === 'criada') {
            $resultado['tarefas_criadas']++;
            $resultado['itens'][] = $item['item'];
        }
    }

    private function clienteTemAtrasoAberto(Cobranca $cobranca): bool
    {
        return Cobranca::query()
            ->where('cliente_id', $cobranca->cliente_id)
            ->whereKeyNot($cobranca->id)
            ->whereNotIn('status', ['paga', 'cancelada'])
            ->whereDate('vencimento', '<', today()->toDateString())
            ->exists();
    }

    private function cobrancaAbertaDoCliente(int $clienteId): ?Cobranca
    {
        return Cobranca::query()
            ->with('cliente', 'contrato.motocicleta')
            ->where('cliente_id', $clienteId)
            ->whereNotIn('status', ['paga', 'cancelada'])
            ->orderBy('vencimento')
            ->first();
    }
}
