<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tarefas', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_tarefas', 'disparado_em')) {
                $table->dateTime('disparado_em')->nullable()->after('prazo_em')->index();
            }

            if (! Schema::hasColumn('crm_tarefas', 'disparo_status')) {
                $table->string('disparo_status', 30)->nullable()->after('disparado_em');
            }

            if (! Schema::hasColumn('crm_tarefas', 'disparo_erro')) {
                $table->text('disparo_erro')->nullable()->after('disparo_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_tarefas', function (Blueprint $table): void {
            foreach (['disparo_erro', 'disparo_status', 'disparado_em'] as $coluna) {
                if (Schema::hasColumn('crm_tarefas', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
