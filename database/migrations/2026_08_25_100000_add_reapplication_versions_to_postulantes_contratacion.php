<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            // Un RUT puede volver a postular con otra identidad Google. La relación
            // mantiene las versiones separadas y no entrega acceso al expediente previo.
            $table->dropUnique('uniq_postulantes_rut');
            $table->index('rut', 'idx_postulantes_rut');
            $table->foreignId('postulacion_anterior_id')
                ->nullable()
                ->after('google_avatar')
                ->constrained('postulantes_contratacion')
                ->nullOnDelete();
            $table->boolean('es_vigente')->default(true)->after('estado');
            $table->index(['rut', 'es_vigente'], 'idx_postulantes_rut_vigente');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            $table->dropForeign(['postulacion_anterior_id']);
            $table->dropIndex('idx_postulantes_rut_vigente');
            $table->dropIndex('idx_postulantes_rut');
            $table->dropColumn(['postulacion_anterior_id', 'es_vigente']);
        });

        // No se eliminan repostulaciones en un rollback. Solo se restaura la
        // restricción original si los datos aún permiten hacerlo sin pérdida.
        if (! DB::table('postulantes_contratacion')->select('rut')->groupBy('rut')->havingRaw('COUNT(*) > 1')->exists()) {
            Schema::table('postulantes_contratacion', function (Blueprint $table) {
                $table->unique('rut', 'uniq_postulantes_rut');
            });
        }
    }
};
