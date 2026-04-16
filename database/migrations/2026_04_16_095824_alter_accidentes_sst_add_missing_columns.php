<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accidentes_sst', function (Blueprint $table) {
            // Campos separados de fecha/hora usados por las vistas
            $table->date('fecha_accidente')->nullable()->after('centro_costo_id');
            $table->time('hora_accidente')->nullable()->after('fecha_accidente');

            // Campos que usan las vistas pero no existían
            $table->text('lesiones')->nullable()->after('descripcion');
            $table->text('medidas_preventivas')->nullable()->after('medidas_correctivas');

            // Trabajador de Kizeo (Personal Vigente)
            $table->string('trabajador_kizeo_id', 100)->nullable()->after('trabajador_rut');
            $table->string('trabajador_cargo', 200)->nullable()->after('trabajador_kizeo_id');

            // Soft deletes (el modelo ya lo usa)
            $table->softDeletes();
        });

        // Hacer nullable columnas que la vista no siempre llena
        Schema::table('accidentes_sst', function (Blueprint $table) {
            $table->string('trabajador_nombre', 200)->nullable()->change();
            $table->string('lugar', 300)->nullable()->change();
            $table->dateTime('fecha_hora_accidente')->nullable()->change();
        });

        // Convertir enums a strings para flexibilidad (lowercase desde vistas)
        // tipo
        DB::statement("ALTER TABLE accidentes_sst MODIFY COLUMN tipo VARCHAR(50) DEFAULT 'accidente_trabajo'");
        // gravedad
        DB::statement("ALTER TABLE accidentes_sst MODIFY COLUMN gravedad VARCHAR(30) DEFAULT 'leve'");
        // estado
        DB::statement("ALTER TABLE accidentes_sst MODIFY COLUMN estado VARCHAR(30) DEFAULT 'notificado'");
    }

    public function down(): void
    {
        Schema::table('accidentes_sst', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_accidente', 'hora_accidente',
                'lesiones', 'medidas_preventivas',
                'trabajador_kizeo_id', 'trabajador_cargo',
                'deleted_at',
            ]);
        });
    }
};
