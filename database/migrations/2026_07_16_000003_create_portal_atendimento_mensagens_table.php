<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_atendimento_mensagens', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('portal_atendimento_id')->index();
            $table->string('remetente', 30);
            $table->text('mensagem');
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_atendimento_mensagens');
    }
};
