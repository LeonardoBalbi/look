<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranca_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cobranca_id');
            $table->string('tipo', 60)->nullable();
            $table->text('mensagem')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_logs');
    }
};
