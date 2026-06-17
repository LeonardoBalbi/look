<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cobranca_id');
            $table->decimal('valor', 10, 2);
            $table->string('forma', 30)->default('pix');
            $table->dateTime('pago_em');
            $table->string('comprovante', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
