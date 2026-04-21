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
            if (!Schema::hasColumn('accidentes_sst', 'fecha_accidente')) {
                $table->date('fecha_accidente')->nullable()->after('centro_costo_id');
            }
            if (!Schema::hasColumn('accidentes_sst', 'hora_accidente')) {
                $table->time('hora_accidente')->nullable()->after('fecha_accidente');
            }
            if (!Schema::hasColumn('accidentes_sst', 'lesiones')) {
                $table->text('lesiones')->nullable()->after('descripcion');
            }
            if (!Schema::hasColumn('accidentes_sst', 'medidas_preventivas')) {
                $table->text('medidas_preventivas')->nullable()->after('medidas_correctivas');
            }
            if (!Schema::hasColumn('accidentes_sst', 'trabajador_kizeo_id')) {
                $table->string('trabajador_kizeo_id', 100)->nullable()->after('trabajador_rut');
            }
            if (!Schema::hasColumn('accidentes_sst', 'trabajador_cargo')) {
                $table->string('trabajador_cargo', 200)->nullable()->after('trabajador_kizeo_id');
            }
            if (!Schema::hasColumn('accidentes_sst', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Hacer nullable columnas que la vista no siempre llena
        if (Schema::hasColumn('accidentes_sst', 'trabajador_nombre')) {
            Schema::table('accidentes_sst', function (Blueprint $table) {
                $table->string('trabajador_nombre', 200)->nullable()->change();
            });
        }
        if (Schema::hasColumn('accidentes_sst', 'lugar')) {
            Schema::table('accidentes_sst', function (Blueprint $table) {
                $table->string('lugar', 300)->nullable()->change();
            });
        }
        if (Schema::hasColumn('accidentes_sst', 'fecha_hora_accidente')) {
            Schema::table('accidentes_sst', function (Blueprint $table) {
                $table->dateTime('fecha_hora_accidente')->nullable()->change();
            });
        }

        // En MySQL/MariaDB se ajustan los tipos; en SQLite no es necesario.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE accidentes_sst MODIFY COLUMN tipo VARCHAR(50) DEFAULT 'accidente_trabajo'");
            DB::statement("ALTER TABLE accidentes_sst MODIFY COLUMN gravedad VARCHAR(30) DEFAULT 'leve'");
            DB::statement("ALTER TABLE accidentes_sst MODIFY COLUMN estado VARCHAR(30) DEFAULT 'notificado'");
        }
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
