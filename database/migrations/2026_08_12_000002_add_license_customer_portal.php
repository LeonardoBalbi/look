<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('licenca_portal_clientes', 'senha')) {
            Schema::table('licenca_portal_clientes', function (Blueprint $table): void {
                $table->string('senha')->nullable()->after('email');
            });
        }
        if (! Schema::hasColumn('licenca_portal_clientes', 'portal_ativo')) {
            Schema::table('licenca_portal_clientes', function (Blueprint $table): void {
                $table->boolean('portal_ativo')->default(false)->after('senha');
            });
        }
        if (! Schema::hasColumn('licenca_portal_clientes', 'ultimo_login_em')) {
            Schema::table('licenca_portal_clientes', function (Blueprint $table): void {
                $table->dateTime('ultimo_login_em')->nullable()->after('portal_ativo');
            });
        }
        if (! Schema::hasTable('licenca_cliente_password_reset_tokens')) {
            Schema::create('licenca_cliente_password_reset_tokens', function (Blueprint $table): void {
                $table->string('email', 190)->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('licenca_cliente_password_reset_tokens');
        foreach (['ultimo_login_em', 'portal_ativo', 'senha'] as $coluna) {
            if (Schema::hasColumn('licenca_portal_clientes', $coluna)) {
                Schema::table('licenca_portal_clientes', function (Blueprint $table) use ($coluna): void {
                    $table->dropColumn($coluna);
                });
            }
        }
    }
};
