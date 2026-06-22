<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercial_cotizacion_secuencias', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio')->primary();
            $table->unsignedInteger('siguiente_numero')->default(1);
            $table->timestamps();
        });

        $this->sembrarSecuenciasExistentes();
    }

    public function down(): void
    {
        Schema::dropIfExists('comercial_cotizacion_secuencias');
    }

    private function sembrarSecuenciasExistentes(): void
    {
        if (! Schema::hasTable('comercial_cotizaciones')) {
            return;
        }

        $maximosPorAnio = [];

        foreach (DB::table('comercial_cotizaciones')->select(['numero', 'created_at'])->get() as $cotizacion) {
            $numero = (string) ($cotizacion->numero ?? '');

            if (preg_match('/^COTIZ-(\d{4})-(\d+)$/', $numero, $matches)) {
                $anio = (int) $matches[1];
                $correlativo = (int) $matches[2];
            } else {
                $anio = $cotizacion->created_at
                    ? Carbon::parse($cotizacion->created_at)->year
                    : now()->year;
                $correlativo = 0;
            }

            $maximosPorAnio[$anio] = max($maximosPorAnio[$anio] ?? 0, $correlativo);
        }

        foreach ($maximosPorAnio as $anio => $maximo) {
            DB::table('comercial_cotizacion_secuencias')->insert([
                'anio' => $anio,
                'siguiente_numero' => $maximo + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
