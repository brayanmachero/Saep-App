<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comercial_cotizaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('comercial_cotizaciones', 'fecha_fin_vigencia_real')) {
                $table->timestamp('fecha_fin_vigencia_real')->nullable()->after('fecha_vigencia');
            }
        });

        if (Schema::hasColumn('comercial_cotizaciones', 'fecha_fin_vigencia_real')) {
            DB::table('comercial_cotizaciones')
                ->whereIn('estado', ['no_vigente', 'cancelada'])
                ->whereNull('fecha_fin_vigencia_real')
                ->update([
                    'fecha_fin_vigencia_real' => DB::raw('COALESCE(fecha_cancelacion, fecha_vigencia_hasta, updated_at)'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('comercial_cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('comercial_cotizaciones', 'fecha_fin_vigencia_real')) {
                $table->dropColumn('fecha_fin_vigencia_real');
            }
        });
    }
};
