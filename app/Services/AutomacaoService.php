<?php

namespace App\Services;

use App\Models\AutomacaoLog;
use App\Models\Cobranca;
use App\Models\Pagamento;
use App\Support\Locx;
use Illuminate\Support\Collection;
use Throwable;

class AutomacaoService
{
    public const LEMBRETE_3_DIAS = 'lembrete_3_dias';

    public const VENCIMENTO_PIX = 'vencimento_pix';

    public const COBRANCA_1_DIA = 'cobranca_1_dia';

    public const AVISO_GERENTE_3_DIAS = 'aviso_gerente_3_dias';

    public const PAGAMENTO_CONFIRMADO = 'pagamento_confirmado';

    public const TIPOS = [
        self::LEMBRETE_3_DIAS,
        self::VENCIMENTO_PIX,
        self::COBRANCA_1_DIA,
        self::AVISO_GERENTE_3_DIAS,
        self::PAGAMENTO_CONFIRMADO,
    ];

    public function __construct(
        private readonly CobrancaCalculator $calculator,
        private readonly PagBankService $pagBank,
        private readonly WhatsAppService $whatsApp,
    ) {}

    public function pendentes(?string $tipo = null): Collection
    {
        $this->sincronizar($tipo);

        return AutomacaoLog::query()
            ->when($tipo, fn ($query) => $query->where('tipo', $tipo))
            ->whereIn('status', ['pendente', 'erro'])
            ->where('tentativas', '<', config('n8n.max_attempts'))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (AutomacaoLog $evento) => [
                'id' => $evento->id,
                'chave' => $evento->chave,
                'tipo' => $evento->tipo,
                'tentativas' => $evento->tentativas,
                'cobranca_id' => $evento->cobranca_id,
                'pagamento_id' => $evento->pagamento_id,
            ]);
    }

    public function executar(AutomacaoLog $evento): array
    {
        if ($evento->status === 'concluido') {
            return ['ok' => true, 'duplicado' => true, 'evento_id' => $evento->id];
        }

        $evento->increment('tentativas');

        try {
            $resultado = match ($evento->tipo) {
                self::LEMBRETE_3_DIAS => $this->enviarLembrete($evento),
                self::VENCIMENTO_PIX => $this->enviarVencimento($evento),
                self::COBRANCA_1_DIA => $this->enviarCobranca($evento),
                self::AVISO_GERENTE_3_DIAS => $this->avisarGerente($evento),
                self::PAGAMENTO_CONFIRMADO => $this->confirmarPagamento($evento),
                default => ['ok' => false, 'erro' => 'Tipo de automação desconhecido.'],
            };
        } catch (Throwable $exception) {
            $resultado = ['ok' => false, 'erro' => $exception->getMessage()];
        }

        $evento->update([
            'status' => ($resultado['ok'] ?? false) ? 'concluido' : 'erro',
            'erro' => ($resultado['ok'] ?? false) ? null : ($resultado['erro'] ?? 'Falha desconhecida'),
            'executado_em' => ($resultado['ok'] ?? false) ? now() : null,
            'atualizado_em' => now(),
        ]);

        return $resultado + ['evento_id' => $evento->id, 'tipo' => $evento->tipo];
    }

    private function sincronizar(?string $tipo): void
    {
        if (! config('n8n.enabled')) {
            return;
        }

        $tipos = $tipo ? [$tipo] : self::TIPOS;
        abort_unless(collect($tipos)->every(fn ($item) => in_array($item, self::TIPOS, true)), 422);

        if (in_array(self::LEMBRETE_3_DIAS, $tipos, true)) {
            $this->criarEventosCobranca(self::LEMBRETE_3_DIAS, today()->addDays(3));
        }
        if (in_array(self::VENCIMENTO_PIX, $tipos, true)) {
            $this->criarEventosCobranca(self::VENCIMENTO_PIX, today());
        }
        if (in_array(self::COBRANCA_1_DIA, $tipos, true)) {
            $this->criarEventosCobranca(self::COBRANCA_1_DIA, today()->subDay());
        }
        if (in_array(self::AVISO_GERENTE_3_DIAS, $tipos, true)) {
            $this->criarEventosCobranca(self::AVISO_GERENTE_3_DIAS, today()->subDays(3));
        }
        if (in_array(self::PAGAMENTO_CONFIRMADO, $tipos, true)) {
            Pagamento::query()
                ->where('pago_em', '>=', now()->subDays(config('n8n.payment_lookback_days')))
                ->orderBy('id')
                ->chunkById(100, function ($pagamentos): void {
                    foreach ($pagamentos as $pagamento) {
                        AutomacaoLog::firstOrCreate(
                            ['chave' => self::PAGAMENTO_CONFIRMADO.':'.$pagamento->id],
                            [
                                'tipo' => self::PAGAMENTO_CONFIRMADO,
                                'pagamento_id' => $pagamento->id,
                                'cobranca_id' => $pagamento->cobranca_id,
                                'status' => 'pendente',
                                'criado_em' => now(),
                            ]
                        );
                    }
                });
        }
    }

    private function criarEventosCobranca(string $tipo, $data): void
    {
        Cobranca::query()
            ->whereDate('vencimento', $data)
            ->where('status', '<>', 'paga')
            ->orderBy('id')
            ->chunkById(100, function ($cobrancas) use ($tipo): void {
                foreach ($cobrancas as $cobranca) {
                    AutomacaoLog::firstOrCreate(
                        ['chave' => $tipo.':'.$cobranca->id],
                        [
                            'tipo' => $tipo,
                            'cobranca_id' => $cobranca->id,
                            'status' => 'pendente',
                            'criado_em' => now(),
                        ]
                    );
                }
            });
    }

    private function enviarLembrete(AutomacaoLog $evento): array
    {
        $cobranca = $this->cobranca($evento);
        $config = $this->whatsApp->config();
        $dados = $this->dadosCobranca($cobranca);

        return $this->whatsApp->enviarTemplate(
            $cobranca->cliente->whatsapp,
            $config->template_lembrete,
            [
                'customer_name' => $dados['cliente'],
                'vehicle_plate' => $dados['placa'],
                'due_date' => $dados['vencimento'],
                'amount' => $dados['saldo'],
                'pix_code' => $dados['pix'],
            ],
            "Lembrete: a cobrança de {$dados['cliente']} vence em {$dados['vencimento']}.",
            self::LEMBRETE_3_DIAS,
            $cobranca,
        );
    }

    private function enviarVencimento(AutomacaoLog $evento): array
    {
        $cobranca = $this->cobranca($evento);
        if (! $cobranca->pix_copia_cola) {
            $pix = $this->pagBank->criarPix($cobranca);
            if (! ($pix['ok'] ?? false)) {
                return $pix;
            }
            $cobranca->refresh();
        }

        $config = $this->whatsApp->config();
        $dados = $this->dadosCobranca($cobranca);

        return $this->whatsApp->enviarTemplate(
            $cobranca->cliente->whatsapp,
            $config->template_vencimento,
            [
                'customer_name' => $dados['cliente'],
                'vehicle_plate' => $dados['placa'],
                'amount' => $dados['saldo'],
                'due_date' => $dados['vencimento'],
                'pix_code' => $dados['pix'],
            ],
            "Cobrança de {$dados['cliente']} vence hoje. PIX: {$dados['pix']}",
            self::VENCIMENTO_PIX,
            $cobranca,
        );
    }

    private function enviarCobranca(AutomacaoLog $evento): array
    {
        return $this->whatsApp->enviarCobranca($this->cobranca($evento));
    }

    private function avisarGerente(AutomacaoLog $evento): array
    {
        $cobranca = $this->cobranca($evento);
        $config = $this->whatsApp->config();
        $dados = $this->dadosCobranca($cobranca);

        return $this->whatsApp->enviarTemplate(
            $config->gerente_whatsapp,
            $config->template_gerente,
            [
                'customer_name' => $dados['cliente'],
                'vehicle_plate' => $dados['placa'],
                'days_overdue' => '3',
                'updated_balance' => $dados['saldo'],
                'customer_phone' => $cobranca->cliente->whatsapp ?: 'não informado',
            ],
            "Gerente: {$dados['cliente']} está há 3 dias em atraso, saldo {$dados['saldo']}.",
            self::AVISO_GERENTE_3_DIAS,
            $cobranca,
        );
    }

    private function confirmarPagamento(AutomacaoLog $evento): array
    {
        $pagamento = Pagamento::with('cobranca.cliente')->findOrFail($evento->pagamento_id);
        $cobranca = $pagamento->cobranca;
        $config = $this->whatsApp->config();

        return $this->whatsApp->enviarTemplate(
            $cobranca->cliente->whatsapp,
            $config->template_pagamento,
            [
                'customer_name' => $cobranca->cliente->nome,
                'amount_paid' => Locx::moeda($pagamento->valor),
                'payment_method' => $pagamento->forma,
                'payment_date' => $pagamento->pago_em->format('d/m/Y H:i'),
                'charge_id' => (string) $cobranca->id,
            ],
            "Pagamento confirmado para {$cobranca->cliente->nome}: ".Locx::moeda($pagamento->valor).'.',
            self::PAGAMENTO_CONFIRMADO,
            $cobranca,
        );
    }

    private function cobranca(AutomacaoLog $evento): Cobranca
    {
        return Cobranca::with('cliente', 'contrato.motocicleta')->findOrFail($evento->cobranca_id);
    }

    private function dadosCobranca(Cobranca $cobranca): array
    {
        $saldo = $this->calculator->valorAtualizado(
            $cobranca->valor_principal,
            $cobranca->valor_pago,
            $cobranca->vencimento
        );

        return [
            'cliente' => $cobranca->cliente->nome,
            'placa' => $cobranca->contrato?->motocicleta?->placa ?: 'não informada',
            'vencimento' => $cobranca->vencimento->format('d/m/Y'),
            'saldo' => Locx::moeda($saldo),
            'pix' => $cobranca->pix_copia_cola ?: 'não disponível',
        ];
    }
}
