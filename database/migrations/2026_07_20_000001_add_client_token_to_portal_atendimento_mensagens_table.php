<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_atendimento_mensagens', function (Blueprint $table): void {
            $table->string('client_token', 80)
                ->nullable()
                ->unique('portal_chat_client_token_unique')
                ->after('remetente');
        });
    }

    public function down(): void
    {
        Schema::table('portal_atendimento_mensagens', function (Blueprint $table): void {
            $table->dropUnique('portal_chat_client_token_unique');
            $table->dropColumn('client_token');
        });
    }
};
