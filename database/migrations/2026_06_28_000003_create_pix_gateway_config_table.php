<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pix_gateway_config', function (Blueprint $table) {
            $table->increments('id');
            $table->string('gateway', 30)->default('pagbank');
            $table->dateTime('atualizado_em')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pix_gateway_config');
    }
};
