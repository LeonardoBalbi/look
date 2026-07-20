<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_atendimentos', function (Blueprint $table): void {
            $table->unsignedInteger('atendente_id')->nullable()->index()->after('assistente');
            $table->dateTime('assumido_em')->nullable()->after('lido_em');
            $table->dateTime('encerrado_em')->nullable()->after('assumido_em');
            $table->dateTime('ultima_mensagem_em')->nullable()->after('encerrado_em');
            $table->dateTime('atualizado_em')->nullable()->after('ultima_mensagem_em');
        });

        Schema::table('portal_atendimento_mensagens', function (Blueprint $table): void {
            $table->unsignedInteger('usuario_id')->nullable()->index()->after('remetente');
            $table->string('remetente_nome', 120)->nullable()->after('usuario_id');
        });

        DB::table('portal_atendimentos')->update([
            'ultima_mensagem_em' => DB::raw('criado_em'),
            'atualizado_em' => DB::raw('criado_em'),
        ]);
    }

    public function down(): void
    {
        Schema::table('portal_atendimento_mensagens', function (Blueprint $table): void {
            $table->dropIndex(['usuario_id']);
            $table->dropColumn(['usuario_id', 'remetente_nome']);
        });

        Schema::table('portal_atendimentos', function (Blueprint $table): void {
            $table->dropIndex(['atendente_id']);
            $table->dropColumn([
                'atendente_id',
                'assumido_em',
                'encerrado_em',
                'ultima_mensagem_em',
                'atualizado_em',
            ]);
        });
    }
};
