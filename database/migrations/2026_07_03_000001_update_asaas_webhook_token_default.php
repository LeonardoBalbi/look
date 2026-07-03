<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('asaas_config')
            ->where('webhook_token', 'locx_asaas_webhook_token')
            ->orWhereNull('webhook_token')
            ->update(['webhook_token' => 'locx_asaas_webhook_token_2026_secure']);
    }

    public function down(): void
    {
        DB::table('asaas_config')
            ->where('webhook_token', 'locx_asaas_webhook_token_2026_secure')
            ->update(['webhook_token' => 'locx_asaas_webhook_token']);
    }
};
