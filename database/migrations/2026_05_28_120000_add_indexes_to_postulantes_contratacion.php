<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deduplicar RUT antes de aplicar unique (deja el más antiguo)
        $duplicados = DB::table('postulantes_contratacion')
            ->select('rut', DB::raw('MIN(id) as keep_id'))
            ->groupBy('rut')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicados as $dup) {
            DB::table('postulantes_contratacion')
                ->where('rut', $dup->rut)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            $table->index('google_id', 'idx_postulantes_google_id');
            $table->index('estado',    'idx_postulantes_estado');
            $table->index('email',     'idx_postulantes_email');
            $table->unique('rut',      'uniq_postulantes_rut');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            $table->dropUnique('uniq_postulantes_rut');
            $table->dropIndex('idx_postulantes_email');
            $table->dropIndex('idx_postulantes_estado');
            $table->dropIndex('idx_postulantes_google_id');
        });
    }
};
