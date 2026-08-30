<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_cliente_mensagens')) {
            return;
        }

        Schema::create('portal_cliente_mensagens', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('cliente_id')->index();
            $table->unsignedInteger('loja_id')->nullable()->index();
            $table->unsignedInteger('usuario_id')->nullable()->index();
            $table->string('tipo', 30)->default('informacao')->index();
            $table->string('assunto', 160);
            $table->text('mensagem');
            $table->dateTime('enviada_em')->index();
            $table->dateTime('lida_em')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_cliente_mensagens');
    }
};
