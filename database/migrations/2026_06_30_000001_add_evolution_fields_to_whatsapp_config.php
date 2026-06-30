<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table): void {
            if (! Schema::hasColumn('whatsapp_config', 'evolution_base_url')) {
                $table->string('evolution_base_url', 500)->nullable()->after('access_token');
            }
            if (! Schema::hasColumn('whatsapp_config', 'evolution_instance')) {
                $table->string('evolution_instance', 120)->nullable()->after('evolution_base_url');
            }
            if (! Schema::hasColumn('whatsapp_config', 'evolution_api_key')) {
                $table->text('evolution_api_key')->nullable()->after('evolution_instance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table): void {
            $table->dropColumn([
                'evolution_base_url',
                'evolution_instance',
                'evolution_api_key',
            ]);
        });
    }
};
