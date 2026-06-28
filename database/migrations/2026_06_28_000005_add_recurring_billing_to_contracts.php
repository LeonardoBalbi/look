<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table): void {
            if (! Schema::hasColumn('contratos', 'cobranca_automatica')) {
                $table->boolean('cobranca_automatica')->default(true)->after('forma_cobranca');
            }
            if (! Schema::hasColumn('contratos', 'proxima_cobranca_em')) {
                $table->date('proxima_cobranca_em')->nullable()->after('cobranca_automatica');
            }
            if (! Schema::hasColumn('contratos', 'ultima_cobranca_gerada_em')) {
                $table->dateTime('ultima_cobranca_gerada_em')->nullable()->after('proxima_cobranca_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table): void {
            if (Schema::hasColumn('contratos', 'ultima_cobranca_gerada_em')) {
                $table->dropColumn('ultima_cobranca_gerada_em');
            }
            if (Schema::hasColumn('contratos', 'proxima_cobranca_em')) {
                $table->dropColumn('proxima_cobranca_em');
            }
            if (Schema::hasColumn('contratos', 'cobranca_automatica')) {
                $table->dropColumn('cobranca_automatica');
            }
        });
    }
};
