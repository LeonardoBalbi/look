<?php

namespace App\Models;

use App\Services\AsaasService;
use App\Services\ItauService;
use App\Services\SicoobService;
use App\Support\RentalSupport;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaBancaria extends BaseModel
{
    protected $table = 'contas_bancarias';

    protected $casts = [
        'ativo' => 'boolean',
        'padrao' => 'boolean',
        'credenciais_json' => 'encrypted:array',
        'token_expires_at' => 'datetime',
        'atualizado_em' => 'datetime',
    ];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public static function config(string $provedor, ?int $lojaId = null): self
    {
        $defaults = self::defaults($provedor);
        $query = self::query()->where('provedor', $provedor);

        $conta = $lojaId
            ? (clone $query)->where('loja_id', $lojaId)->orderByDesc('padrao')->first()
            : null;

        if (! $conta) {
            $conta = (clone $query)->whereNull('loja_id')->orderByDesc('padrao')->first();
        }

        if ($conta) {
            return $conta;
        }

        return self::query()->create($defaults + [
            'loja_id' => $lojaId,
            'provedor' => $provedor,
            'nome_conta' => ucfirst($provedor).($lojaId ? ' loja '.$lojaId : ' central'),
            'padrao' => $provedor === 'pagbank',
            'ativo' => true,
        ]);
    }

    public static function gatewayPadrao(?int $lojaId = null, string $fallback = 'pagbank'): string
    {
        $conta = $lojaId
            ? self::query()->where('loja_id', $lojaId)->where('padrao', true)->first()
            : null;

        $conta ??= self::query()->whereNull('loja_id')->where('padrao', true)->first();

        return $conta?->provedor ?: $fallback;
    }

    public static function definirPadrao(string $provedor, ?int $lojaId = null): self
    {
        self::query()->where('loja_id', $lojaId)->update(['padrao' => false]);

        $conta = self::query()->firstOrCreate(
            ['loja_id' => $lojaId, 'provedor' => $provedor],
            self::defaults($provedor) + [
                'nome_conta' => ucfirst($provedor).($lojaId ? ' loja '.$lojaId : ' central'),
                'ativo' => true,
            ]
        );

        $conta->update(['padrao' => true, 'atualizado_em' => now()]);

        return $conta->fresh();
    }

    public static function defaults(string $provedor): array
    {
        return match ($provedor) {
            'asaas' => [
                'modo' => 'demo',
                'ambiente' => 'sandbox',
                'webhook_token' => RentalSupport::webhookToken('asaas'),
                'webhook_url' => route('rental.webhook-asaas'),
            ],
            'sicoob' => [
                'modo' => 'demo',
                'ambiente' => 'sandbox',
                'api_base_url' => SicoobService::DEFAULT_API_BASE_URL,
                'token_url' => SicoobService::DEFAULT_TOKEN_URL,
                'webhook_token' => RentalSupport::webhookToken('sicoob'),
                'webhook_url' => route('rental.webhook-sicoob', ['token' => RentalSupport::webhookToken('sicoob')]),
            ],
            'itau' => [
                'modo' => 'demo',
                'ambiente' => 'producao',
                'api_base_url' => ItauService::DEFAULT_API_BASE_URL,
                'token_url' => ItauService::DEFAULT_TOKEN_URL,
                'webhook_token' => RentalSupport::webhookToken('itau'),
                'webhook_url' => route('rental.webhook-itau', ['token' => RentalSupport::webhookToken('itau')]),
            ],
            default => [
                'modo' => 'demo',
                'ambiente' => 'sandbox',
                'merchant_reference' => config('branding.merchant_reference'),
                'webhook_url' => route('rental.webhook-pagbank'),
            ],
        };
    }
}
