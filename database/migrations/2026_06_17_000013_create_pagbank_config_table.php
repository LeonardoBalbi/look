<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagbank_config', function (Blueprint $table) {
            $table->increments('id');
            $table->string('modo', 20)->default('demo');
            $table->string('ambiente', 20)->default('sandbox');
            $table->string('client_id', 255)->nullable();
            $table->text('client_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->string('webhook_url', 500)->nullable();
            $table->string('merchant_reference', 80)->default('LOCX');
            $table->boolean('ativo')->default(true);
            $table->dateTime('atualizado_em')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagbank_config');
    }
};
