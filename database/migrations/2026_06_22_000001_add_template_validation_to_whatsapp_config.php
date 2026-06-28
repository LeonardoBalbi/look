<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table): void {
            $table->string('waba_id', 255)->nullable()->after('modo');
            $table->string('template_language', 20)->default('pt_BR')->after('template_cobranca');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table): void {
            $table->dropColumn(['waba_id', 'template_language']);
        });
    }
};
