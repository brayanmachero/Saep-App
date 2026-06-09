<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comercial_cotizacion_auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('comercial_cotizaciones')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('accion'); // creada, actualizada, aprobada, vigente, cancelada, versionada
            $table->string('descripcion')->nullable();
            $table->json('cambios')->nullable()->comment('Qué cambios se hicieron (old values → new values)');

            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index('cotizacion_id');
            $table->index('usuario_id');
            $table->index('accion');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comercial_cotizacion_auditorias');
    }
};
