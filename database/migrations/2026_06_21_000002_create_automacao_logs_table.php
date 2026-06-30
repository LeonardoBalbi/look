<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automacao_logs')) {
            return;
        }

        Schema::create('automacao_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('chave', 180)->unique();
            $table->string('tipo', 60)->index();
            $table->unsignedInteger('cobranca_id')->nullable()->index();
            $table->unsignedInteger('pagamento_id')->nullable()->index();
            $table->string('status', 30)->default('pendente')->index();
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->json('payload')->nullable();
            $table->text('erro')->nullable();
            $table->dateTime('executado_em')->nullable();
            $table->timestamp('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automacao_logs');
    }
};
