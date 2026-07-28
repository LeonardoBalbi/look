<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motocicletas', function (Blueprint $table) {
            if (! Schema::hasColumn('motocicletas', 'cor')) {
                $table->string('cor', 40)->nullable()->after('placa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('motocicletas', function (Blueprint $table) {
            if (Schema::hasColumn('motocicletas', 'cor')) {
                $table->dropColumn('cor');
            }
        });
    }
};
