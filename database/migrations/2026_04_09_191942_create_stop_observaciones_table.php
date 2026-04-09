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
        Schema::create('stop_observaciones', function (Blueprint $table) {
            $table->id();
            $table->string('gdrive_file_id', 100)->nullable()->index();
            $table->unsignedInteger('row_number')->nullable();
            $table->timestamp('marca_temporal')->nullable();
            $table->string('correo', 200)->nullable();
            $table->date('fecha_tarjeta')->nullable();
            $table->string('hora_observacion', 20)->nullable();
            $table->string('centro', 150)->nullable();
            $table->string('empresa_observador', 200)->nullable();
            $table->string('nombre_observador', 200)->nullable();
            $table->string('rut_observador', 20)->nullable();
            $table->string('clasificacion', 30)->nullable();
            $table->string('turno', 50)->nullable();
            $table->string('nombre_observado', 200)->nullable();
            $table->string('interno_externo', 20)->nullable();
            $table->string('antiguedad', 80)->nullable();
            $table->string('area_proceso', 150)->nullable();
            $table->string('empresa_observado', 200)->nullable();
            $table->string('cargo_observado', 150)->nullable();
            $table->string('tipo_observacion', 200)->nullable();
            $table->string('empresa_ruta', 200)->nullable();
            $table->string('tipo_observacion_b', 200)->nullable();
            $table->json('checklist_data')->nullable();
            $table->timestamps();

            // Índices para consultas frecuentes del dashboard
            $table->index('fecha_tarjeta');
            $table->index('clasificacion');
            $table->index('centro');
            $table->index('empresa_observador');
            $table->index('empresa_observado');
            $table->index('tipo_observacion');
            $table->index(['fecha_tarjeta', 'clasificacion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stop_observaciones');
    }
};
