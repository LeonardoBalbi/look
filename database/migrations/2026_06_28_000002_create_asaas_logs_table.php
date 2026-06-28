<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asaas_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cobranca_id')->nullable();
            $table->string('tipo', 60)->nullable();
            $table->string('status', 80)->nullable();
            $table->integer('http_code')->nullable();
            $table->mediumText('payload')->nullable();
            $table->mediumText('resposta_api')->nullable();
            $table->mediumText('erro')->nullable();
            $table->dateTime('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asaas_logs');
    }
};
