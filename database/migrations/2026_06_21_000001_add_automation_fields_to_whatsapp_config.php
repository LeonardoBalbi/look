<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table): void {
            $table->string('template_vencimento', 120)->default('locx_vencimento_pix')->after('template_lembrete');
            $table->string('template_pagamento', 120)->default('locx_pagamento_confirmado')->after('template_vencimento');
            $table->string('template_gerente', 120)->default('locx_aviso_gerente')->after('template_pagamento');
            $table->string('gerente_whatsapp', 30)->nullable()->after('template_gerente');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table): void {
            $table->dropColumn([
                'template_vencimento',
                'template_pagamento',
                'template_gerente',
                'gerente_whatsapp',
            ]);
        });
    }
};
