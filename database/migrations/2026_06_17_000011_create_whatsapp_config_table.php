<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_config', function (Blueprint $table) {
            $table->increments('id');
            $table->string('modo', 20)->default('demo');
            $table->string('phone_number_id', 255)->nullable();
            $table->text('access_token')->nullable();
            $table->string('verify_token', 255)->nullable();
            $table->string('template_cobranca', 120)->default('locx_cobranca_atraso');
            $table->string('template_lembrete', 120)->default('locx_lembrete_vencimento');
            $table->string('template_bloqueio', 120)->default('locx_aviso_bloqueio');
            $table->boolean('ativo')->default(true);
            $table->dateTime('atualizado_em')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_config');
    }
};
