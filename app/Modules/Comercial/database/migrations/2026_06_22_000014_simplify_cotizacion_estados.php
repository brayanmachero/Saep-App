<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('comercial_cotizaciones')
            ->where('estado', 'aprobada')
            ->update([
                'estado' => 'vigente',
                'fecha_vigencia' => DB::raw('COALESCE(fecha_vigencia, fecha_aprobacion, updated_at)'),
            ]);

        DB::table('comercial_cotizaciones')
            ->whereIn('estado', ['rechazada', 'cancelada'])
            ->update([
                'estado' => 'no_vigente',
                'fecha_fin_vigencia_real' => DB::raw('COALESCE(fecha_fin_vigencia_real, fecha_cancelacion, updated_at)'),
            ]);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE comercial_cotizaciones MODIFY estado ENUM('en_cotizacion', 'vigente', 'no_vigente') NOT NULL DEFAULT 'en_cotizacion'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE comercial_cotizaciones MODIFY estado ENUM('en_cotizacion', 'aprobada', 'vigente', 'no_vigente', 'rechazada', 'cancelada') NOT NULL DEFAULT 'en_cotizacion'");
        }
    }
};
