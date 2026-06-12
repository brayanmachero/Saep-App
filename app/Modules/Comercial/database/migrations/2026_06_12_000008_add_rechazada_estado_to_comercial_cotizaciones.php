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

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE comercial_cotizaciones MODIFY estado ENUM('en_cotizacion','aprobada','vigente','no_vigente','rechazada','cancelada') NOT NULL DEFAULT 'en_cotizacion'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('comercial_cotizaciones')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::table('comercial_cotizaciones')
                ->where('estado', 'rechazada')
                ->update(['estado' => 'cancelada']);

            DB::statement("ALTER TABLE comercial_cotizaciones MODIFY estado ENUM('en_cotizacion','aprobada','vigente','no_vigente','cancelada') NOT NULL DEFAULT 'en_cotizacion'");
        }
    }
};
