<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'asaas_customer_id')) {
                $table->string('asaas_customer_id', 120)->nullable()->after('email');
            }
        });

        Schema::table('cobrancas', function (Blueprint $table) {
            if (! Schema::hasColumn('cobrancas', 'asaas_status')) {
                $table->string('asaas_status', 60)->nullable()->after('asaas_id');
            }
            if (! Schema::hasColumn('cobrancas', 'asaas_payload')) {
                $table->mediumText('asaas_payload')->nullable()->after('asaas_status');
            }
        });

        DB::table('asaas_config')->insertOrIgnore([
            'id' => 1,
            'modo' => 'demo',
            'ambiente' => 'sandbox',
            'ativo' => 1,
            'webhook_token' => 'locx_asaas_webhook_token',
        ]);

        DB::table('pix_gateway_config')->insertOrIgnore([
            'id' => 1,
            'gateway' => 'pagbank',
        ]);

        foreach (['visualizar', 'criar', 'editar', 'excluir'] as $acao) {
            DB::table('usuario_permissoes')->updateOrInsert(
                ['usuario_id' => 1, 'modulo' => 'asaas', 'acao' => $acao],
                ['usuario_id' => 1, 'modulo' => 'asaas', 'acao' => $acao]
            );
        }
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            if (Schema::hasColumn('cobrancas', 'asaas_payload')) {
                $table->dropColumn('asaas_payload');
            }
            if (Schema::hasColumn('cobrancas', 'asaas_status')) {
                $table->dropColumn('asaas_status');
            }
        });

        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'asaas_customer_id')) {
                $table->dropColumn('asaas_customer_id');
            }
        });

        DB::table('usuario_permissoes')->where('modulo', 'asaas')->delete();
    }
};
