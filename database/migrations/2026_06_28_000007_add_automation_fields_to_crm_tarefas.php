<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tarefas', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_tarefas', 'cobranca_id')) {
                $table->unsignedInteger('cobranca_id')->nullable()->after('cliente_id')->index();
            }

            if (! Schema::hasColumn('crm_tarefas', 'chave')) {
                $table->string('chave', 160)->nullable()->after('tipo')->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_tarefas', function (Blueprint $table): void {
            if (Schema::hasColumn('crm_tarefas', 'chave')) {
                $table->dropUnique(['chave']);
                $table->dropColumn('chave');
            }

            if (Schema::hasColumn('crm_tarefas', 'cobranca_id')) {
                $table->dropColumn('cobranca_id');
            }
        });
    }
};
