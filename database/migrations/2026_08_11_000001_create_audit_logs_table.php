<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('actor_type', 30)->nullable()->index();
            $table->unsignedInteger('actor_id')->nullable()->index();
            $table->string('action', 160)->index();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->string('ip', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
