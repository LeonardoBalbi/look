<?php

use App\Support\RentalSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_config')) {
            $prefixoLegado = strtolower('LOCX');
            DB::table('whatsapp_config')->where('verify_token', $prefixoLegado.'_webhook_token')
                ->update(['verify_token' => RentalSupport::webhookToken('whatsapp')]);

            foreach ([
                'template_cobranca' => 'cobranca_atraso',
                'template_lembrete' => 'lembrete_vencimento',
                'template_bloqueio' => 'aviso_bloqueio',
                'template_vencimento' => 'vencimento_pix',
                'template_pagamento' => 'pagamento_confirmado',
                'template_gerente' => 'aviso_gerente',
            ] as $campo => $sufixo) {
                if (Schema::hasColumn('whatsapp_config', $campo)) {
                    DB::table('whatsapp_config')->where($campo, $prefixoLegado.'_'.$sufixo)
                        ->update([$campo => 'rental_'.$sufixo]);
                }
            }
        }

        if (Schema::hasTable('pagbank_config')) {
            DB::table('pagbank_config')->where('merchant_reference', 'LOCX')
                ->update(['merchant_reference' => config('branding.merchant_reference')]);
        }

        if (Schema::hasTable('contas_bancarias')) {
            DB::table('contas_bancarias')->where('merchant_reference', 'LOCX')
                ->update(['merchant_reference' => config('branding.merchant_reference')]);
        }

        if (Schema::hasTable('usuarios')) {
            DB::table('usuarios')->where('nome', 'Super Admin LocX')->update(['nome' => 'Super Administrador']);
            DB::table('usuarios')->where('nome', 'Administrador LocX')->update(['nome' => 'Administrador']);
        }
    }

    public function down(): void
    {
        // Alterações de identidade não são revertidas para evitar reintroduzir valores legados.
    }
};
