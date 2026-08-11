<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\CobrancaCampanha;
use App\Models\CobrancaCampanhaItem;
use App\Models\User;
use App\Support\RentalSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class CobrancaCampanhaService
{
    public function __construct(
        private readonly WhatsAppService $whatsApp,
        private readonly EmailCampanhaService $email,
        private readonly TelegramService $telegram,
        private readonly CobrancaCalculator $calculator,
    ) {}

    public function criar(array $dados, User $usuario): CobrancaCampanha
    {
        return DB::transaction(function () use ($dados, $usuario): CobrancaCampanha {
            $canais = array_values(array_unique(array_intersect(
                (array) ($dados['canais'] ?? []),
                ['whatsapp', 'email', 'telegram']
            )));
            $lojas = $usuario->isAdmin() ? [] : $usuario->lojaIdsPermitidas();

            $campanha = CobrancaCampanha::create([
                'criado_por' => $usuario->id,
                'nome' => $dados['nome'],
                'publico' => $dados['publico'],
                'lojas_json' => $lojas ?: null,
                'canais_json' => $canais,
                'estrategia' => $dados['estrategia'] ?? 'todos',
                'mensagem' => $dados['mensagem'],
                'status' => ! empty($dados['agendado_para']) ? 'agendada' : 'pendente',
                'agendado_para' => $dados['agendado_para'] ?? null,
                'atualizado_em' => now(),
            ]);

            $cobrancas = $this->consultaPublico(
                $dados['publico'],
                collect($dados['cobrancas'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all(),
                $usuario
            )->get();

            foreach ($cobrancas as $cobranca) {
                CobrancaCampanhaItem::create([
                    'campanha_id' => $campanha->id,
                    'cobranca_id' => $cobranca->id,
                    'cliente_id' => $cobranca->cliente_id,
                    'status' => 'pendente',
                    'canais_json' => $canais,
                ]);
            }

            $campanha->update(['total_destinatarios' => $cobrancas->count()]);

            return $campanha->fresh('itens');
        });
    }

    public function podeGerenciar(CobrancaCampanha $campanha, User $usuario): bool
    {
        return $usuario->isAdmin() || (int) $campanha->criado_por === (int) $usuario->id;
    }

    public function processar(CobrancaCampanha $campanha, int $limite = 200): array
    {
        $campanha->refresh();
        if (in_array($campanha->status, ['concluida', 'cancelada'], true)) {
            return $this->resumo($campanha);
        }

        if ($campanha->agendado_para && $campanha->agendado_para->isFuture()) {
            return $this->resumo($campanha);
        }

        $campanha->update(['status' => 'processando', 'atualizado_em' => now()]);
        $ids = $this->reservarItens($campanha, max(1, $limite));

        CobrancaCampanhaItem::query()
            ->with('cobranca.cliente', 'cobranca.contrato.motocicleta')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->each(fn (CobrancaCampanhaItem $item) => $this->processarItem($campanha, $item));

        $this->atualizarTotais($campanha);

        return $this->resumo($campanha->fresh());
    }

    public function processarAgendadas(int $limiteCampanhas = 10, int $limiteItens = 200): array
    {
        $campanhas = CobrancaCampanha::query()
            ->whereIn('status', ['agendada', 'pendente', 'processando'])
            ->where(function (Builder $query): void {
                $query->whereNull('agendado_para')->orWhere('agendado_para', '<=', now());
            })
            ->orderBy('id')
            ->limit(max(1, $limiteCampanhas))
            ->get();

        $resultados = [];
        foreach ($campanhas as $campanha) {
            $resultados[] = $this->processar($campanha, $limiteItens);
        }

        return ['campanhas' => count($resultados), 'resultados' => $resultados];
    }

    public function cancelar(CobrancaCampanha $campanha): void
    {
        if (in_array($campanha->status, ['concluida', 'cancelada'], true)) {
            return;
        }

        DB::transaction(function () use ($campanha): void {
            CobrancaCampanhaItem::query()
                ->where('campanha_id', $campanha->id)
                ->where('status', 'pendente')
                ->update([
                    'status' => 'cancelado',
                    'erro' => 'Campanha cancelada antes do envio.',
                    'processado_em' => now(),
                ]);

            $campanha->update(['status' => 'cancelada', 'atualizado_em' => now()]);
        });
    }

    public function renderizarMensagem(CobrancaCampanha $campanha, Cobranca $cobranca): string
    {
        $cobranca->loadMissing('cliente', 'contrato.motocicleta');
        $saldo = $this->calculator->valorAtualizado(
            $cobranca->valor_principal,
            $cobranca->valor_pago,
            $cobranca->vencimento
        );

        return $this->telegram->renderizarTemplate($campanha->mensagem, [
            'cliente' => $cobranca->cliente?->nome ?: 'cliente',
            'cobranca_id' => (string) $cobranca->id,
            'vencimento' => $cobranca->vencimento?->format('d/m/Y') ?: '-',
            'valor' => RentalSupport::moeda($cobranca->valor_principal),
            'saldo' => RentalSupport::moeda($saldo),
            'dias_atraso' => (string) max(0, $cobranca->vencimento?->diffInDays(today(), false) ?? 0),
            'placa' => $cobranca->contrato?->motocicleta?->placa ?: 'não informada',
            'pix' => $cobranca->pix_copia_cola ?: 'PIX ainda não disponível',
            'link_portal' => route('cliente.login'),
        ]);
    }

    private function consultaPublico(string $publico, array $selecionadas, User $usuario): Builder
    {
        $query = Cobranca::query()
            ->with('cliente', 'contrato.motocicleta')
            ->whereNotIn('status', ['paga', 'cancelada']);

        if (! $usuario->isAdmin()) {
            $lojas = $usuario->lojaIdsPermitidas();
            $lojas ? $query->whereIn('loja_id', $lojas) : $query->whereRaw('1 = 0');
        }

        return match ($publico) {
            'vence_hoje' => $query->whereDate('vencimento', today()),
            'vencidas_7' => $query->whereDate('vencimento', '<=', today()->subDays(7)),
            'vencidas_15' => $query->whereDate('vencimento', '<=', today()->subDays(15)),
            'vencidas_30' => $query->whereDate('vencimento', '<=', today()->subDays(30)),
            'selecionadas' => $query->whereIn('id', $selecionadas ?: [0]),
            default => $query,
        };
    }

    /** @return array<int> */
    private function reservarItens(CobrancaCampanha $campanha, int $limite): array
    {
        return DB::transaction(function () use ($campanha, $limite): array {
            $expiradoEm = now()->subMinutes(15);
            $ids = CobrancaCampanhaItem::query()
                ->where('campanha_id', $campanha->id)
                ->where(function (Builder $query) use ($expiradoEm): void {
                    $query->where('status', 'pendente')
                        ->orWhere(function (Builder $subquery) use ($expiradoEm): void {
                            $subquery->where('status', 'processando')
                                ->where(function (Builder $tempo) use ($expiradoEm): void {
                                    $tempo->whereNull('processando_em')->orWhere('processando_em', '<=', $expiradoEm);
                                });
                        });
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($limite)
                ->pluck('id')
                ->all();

            if ($ids) {
                CobrancaCampanhaItem::query()->whereIn('id', $ids)->update([
                    'status' => 'processando',
                    'processando_em' => now(),
                    'erro' => null,
                ]);
            }

            return array_map('intval', $ids);
        });
    }

    private function processarItem(CobrancaCampanha $campanha, CobrancaCampanhaItem $item): void
    {
        $cobranca = $item->cobranca;
        if (! $cobranca || in_array($cobranca->status, ['paga', 'cancelada'], true)) {
            $item->update([
                'status' => 'ignorado',
                'erro' => 'Cobrança já paga, cancelada ou não encontrada.',
                'processado_em' => now(),
            ]);
            return;
        }

        $mensagem = $this->renderizarMensagem($campanha, $cobranca);
        $canais = (array) $item->canais_json;
        $resultados = [];
        $sucessos = 0;

        foreach ($canais as $canal) {
            try {
                $resultado = $this->enviarCanal($canal, $cobranca, $mensagem);
            } catch (Throwable $exception) {
                $resultado = ['ok' => false, 'erro' => $exception->getMessage()];
            }

            $resultados[$canal] = $resultado;
            if ($resultado['ok'] ?? false) {
                $sucessos++;
                if ($campanha->estrategia === 'prioridade') {
                    break;
                }
            }
        }

        $tentados = count($resultados);
        $status = $sucessos === 0
            ? 'falha'
            : (($campanha->estrategia === 'prioridade' || $sucessos === $tentados) ? 'enviado' : 'parcial');

        $erros = collect($resultados)
            ->filter(fn (array $resultado) => ! ($resultado['ok'] ?? false))
            ->map(fn (array $resultado, string $canal) => strtoupper($canal).': '.($resultado['erro'] ?? 'falha'))
            ->implode(' | ');

        $item->update([
            'status' => $status,
            'resultados_json' => $resultados,
            'erro' => $erros ?: null,
            'processado_em' => now(),
        ]);
    }

    private function enviarCanal(string $canal, Cobranca $cobranca, string $mensagem): array
    {
        return match ($canal) {
            'whatsapp' => $this->whatsApp->enviarTemplate(
                $cobranca->cliente?->whatsapp,
                $this->whatsApp->config()->template_cobranca,
                [
                    'customer_name' => $cobranca->cliente?->nome ?: 'cliente',
                    'vehicle_plate' => $cobranca->contrato?->motocicleta?->placa ?: 'não informada',
                    'days_overdue' => (string) max(0, $cobranca->vencimento?->diffInDays(today(), false) ?? 0),
                    'updated_balance' => RentalSupport::moeda(max(0, (float) $cobranca->valor_atualizado - (float) $cobranca->valor_pago)),
                    'pix_code' => $cobranca->pix_copia_cola ?: 'não disponível',
                ],
                $mensagem,
                'campanha_cobranca',
                $cobranca
            ),
            'email' => $this->email->enviar($cobranca, $mensagem, 'Aviso de cobrança · '.$cobranca->cliente?->nome),
            'telegram' => $this->telegram->enviarCobranca($cobranca, $mensagem),
            default => ['ok' => false, 'erro' => 'Canal desconhecido.'],
        };
    }

    private function atualizarTotais(CobrancaCampanha $campanha): void
    {
        $base = CobrancaCampanhaItem::query()->where('campanha_id', $campanha->id);
        $emFila = (clone $base)->whereIn('status', ['pendente', 'processando'])->count();
        $processados = (clone $base)->whereIn('status', ['enviado', 'parcial', 'falha', 'ignorado'])->count();
        $enviados = (clone $base)->whereIn('status', ['enviado', 'parcial'])->count();
        $falhas = (clone $base)->where('status', 'falha')->count();

        $status = $emFila > 0
            ? 'processando'
            : ($falhas > 0 ? ($enviados > 0 ? 'parcial' : 'falha') : 'concluida');

        $campanha->update([
            'status' => $status,
            'total_processados' => $processados,
            'total_enviados' => $enviados,
            'total_falhas' => $falhas,
            'processado_em' => $emFila === 0 ? now() : null,
            'atualizado_em' => now(),
        ]);
    }

    private function resumo(CobrancaCampanha $campanha): array
    {
        return [
            'id' => $campanha->id,
            'nome' => $campanha->nome,
            'status' => $campanha->status,
            'total' => $campanha->total_destinatarios,
            'processados' => $campanha->total_processados,
            'enviados' => $campanha->total_enviados,
            'falhas' => $campanha->total_falhas,
        ];
    }
}
