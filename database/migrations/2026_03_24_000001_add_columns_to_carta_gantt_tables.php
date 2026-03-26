<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar columnas faltantes a programas_sst
        Schema::table('programas_sst', function (Blueprint $table) {
            $table->string('codigo', 50)->nullable()->after('id');
            $table->foreignId('centro_costo_id')->nullable()->after('estado')
                  ->constrained('centros_costo')->onDelete('set null');
            $table->foreignId('responsable_id')->nullable()->after('centro_costo_id')
                  ->constrained('users')->onDelete('set null');
        });

        // Mejorar sst_actividades: responsable real, fechas, prioridad, estado, periodicidad
        Schema::table('sst_actividades', function (Blueprint $table) {
            $table->foreignId('responsable_id')->nullable()->after('responsable')
                  ->constrained('users')->onDelete('set null');
            $table->date('fecha_inicio')->nullable()->after('responsable_id');
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            $table->string('prioridad', 20)->default('MEDIA')->after('fecha_fin');
            $table->string('estado', 20)->default('PENDIENTE')->after('prioridad');
            $table->string('periodicidad', 20)->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('sst_actividades', function (Blueprint $table) {
            $table->dropForeign(['responsable_id']);
            $table->dropColumn(['responsable_id', 'fecha_inicio', 'fecha_fin', 'prioridad', 'estado', 'periodicidad']);
        });

        Schema::table('programas_sst', function (Blueprint $table) {
            $table->dropForeign(['centro_costo_id']);
            $table->dropForeign(['responsable_id']);
            $table->dropColumn(['codigo', 'centro_costo_id', 'responsable_id']);
        });
    }
};
