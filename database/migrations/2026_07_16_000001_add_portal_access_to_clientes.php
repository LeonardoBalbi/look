<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('senha', 255)->nullable()->after('email');
            $table->boolean('portal_ativo')->default(false)->after('senha');
            $table->dateTime('ultimo_login_em')->nullable()->after('portal_ativo');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn(['senha', 'portal_ativo', 'ultimo_login_em']);
        });
    }
};
