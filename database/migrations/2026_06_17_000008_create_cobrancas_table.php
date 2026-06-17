<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobrancas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('contrato_id');
            $table->unsignedInteger('cliente_id');
            $table->unsignedInteger('loja_id');
            $table->date('vencimento');
            $table->decimal('valor_principal', 10, 2);
            $table->decimal('valor_atualizado', 10, 2);
            $table->decimal('valor_pago', 10, 2)->default(0);
            $table->string('status', 30)->default('aberta');
            $table->text('pix_qrcode')->nullable();
            $table->text('pix_copia_cola')->nullable();
            $table->string('asaas_id', 120)->nullable();
            $table->string('whatsapp_status', 30)->default('pendente');
            $table->dateTime('atualizado_em')->nullable();
            $table->string('pagbank_order_id', 160)->nullable();
            $table->string('pagbank_charge_id', 160)->nullable();
            $table->string('pagbank_status', 60)->nullable();
            $table->mediumText('pagbank_payload')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
    }
};
