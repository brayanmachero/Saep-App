<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comercial_cotizaciones')) {
            return;
        }

        DB::table('comercial_cotizaciones')
            ->where('estado', 'aprobada')
            ->update(['estado' => 'vigente']);

        DB::table('comercial_cotizaciones')
            ->whereIn('estado', ['rechazada', 'cancelada'])
            ->update(['estado' => 'no_vigente']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE comercial_cotizaciones MODIFY estado ENUM('en_cotizacion','vigente','no_vigente') NOT NULL DEFAULT 'en_cotizacion'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('comercial_cotizaciones')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE comercial_cotizaciones MODIFY estado ENUM('en_cotizacion','aprobada','vigente','no_vigente','rechazada','cancelada') NOT NULL DEFAULT 'en_cotizacion'");
        }
    }
};
