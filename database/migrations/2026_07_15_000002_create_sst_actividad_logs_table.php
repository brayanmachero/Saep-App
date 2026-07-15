<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sst_actividad_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->nullable()->constrained('programas_sst')->nullOnDelete();
            $table->foreignId('actividad_id')->nullable()->constrained('sst_actividades')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 80);
            $table->string('resumen', 300)->nullable();
            $table->json('cambios')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['programa_id', 'created_at']);
            $table->index(['actividad_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sst_actividad_logs');
    }
};
