<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('loja_id')->nullable()->index();
            $table->unsignedInteger('cliente_id')->nullable()->index();
            $table->unsignedInteger('motocicleta_id')->nullable()->index();
            $table->string('categoria', 120)->nullable();
            $table->dateTime('retirada_em')->index();
            $table->dateTime('devolucao_em')->index();
            $table->decimal('valor_estimado', 12, 2)->default(0);
            $table->decimal('caucao', 12, 2)->default(0);
            $table->string('origem', 40)->default('balcao');
            $table->string('status', 40)->default('solicitada')->index();
            $table->text('observacoes')->nullable();
            $table->unsignedInteger('criado_por')->nullable()->index();
            $table->timestamp('criado_em')->useCurrent();
            $table->timestamp('atualizado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
