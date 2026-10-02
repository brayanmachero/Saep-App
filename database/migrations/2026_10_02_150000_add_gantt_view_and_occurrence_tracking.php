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
        Schema::table('programas_sst', function (Blueprint $table) {
            $table->string('vista_inicial', 12)->default('ANUAL')->after('estado');
            $table->unsignedTinyInteger('mes_inicial')->nullable()->after('vista_inicial');
        });

        Schema::create('sst_seguimiento_ocurrencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('sst_actividades')->cascadeOnDelete();
            $table->string('tipo', 12);
            $table->date('fecha_programada');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('programado')->default(true);
            $table->boolean('realizado')->default(false);
            $table->text('observacion')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_actualizacion')->nullable();
            $table->timestamps();

            $table->unique(['actividad_id', 'tipo', 'fecha_programada'], 'sst_occurrence_unique');
            $table->index(['actividad_id', 'programado', 'fecha_programada'], 'sst_occurrence_activity_date');
        });

        $this->backfillExistingOccurrences();

        // Programa mensual informado para Logística Inversa: abrir directamente en octubre.
        DB::table('programas_sst')
            ->where('codigo', 'SST-2026-028')
            ->update(['vista_inicial' => 'MENSUAL', 'mes_inicial' => 10]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sst_seguimiento_ocurrencias');

        Schema::table('programas_sst', function (Blueprint $table) {
            $table->dropColumn(['vista_inicial', 'mes_inicial']);
        });
    }

    private function backfillExistingOccurrences(): void
    {
        $actividades = DB::table('sst_actividades as actividad')
            ->join('sst_categorias as categoria', 'categoria.id', '=', 'actividad.categoria_id')
            ->join('programas_sst as programa', 'programa.id', '=', 'categoria.programa_id')
            ->whereIn('actividad.periodicidad', ['DIARIA', 'SEMANAL'])
            ->select([
                'actividad.id', 'actividad.periodicidad', 'actividad.fecha_inicio', 'actividad.fecha_fin',
                'programa.anio',
            ])
            ->get();

        foreach ($actividades as $actividad) {
            $inicio = $actividad->fecha_inicio
                ? Carbon::parse($actividad->fecha_inicio)
                : Carbon::create($actividad->anio, 1, 1)->startOfDay();
            $fin = $actividad->fecha_fin
                ? Carbon::parse($actividad->fecha_fin)
                : Carbon::create($actividad->anio, 12, 31)->endOfDay();

            if ($fin->lt($inicio)) {
                continue;
            }

            $ocurrencias = $this->occurrencesForRange($actividad->periodicidad, $inicio, $fin);
            foreach ($ocurrencias as $ocurrencia) {
                DB::table('sst_seguimiento_ocurrencias')->insertOrIgnore([
                    'actividad_id'     => $actividad->id,
                    'tipo'             => $actividad->periodicidad,
                    'fecha_programada' => $ocurrencia['fecha_programada']->toDateString(),
                    'fecha_inicio'     => $ocurrencia['fecha_inicio']->toDateString(),
                    'fecha_fin'        => $ocurrencia['fecha_fin']->toDateString(),
                    'programado'       => true,
                    'realizado'        => false,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            $legacyByMonth = DB::table('sst_seguimiento')
                ->where('actividad_id', $actividad->id)
                ->get()
                ->keyBy('mes');

            foreach (collect($ocurrencias)->groupBy(fn ($o) => $o['fecha_programada']->month) as $mes => $items) {
                $legacy = $legacyByMonth->get($mes);
                $cantidad = $legacy
                    ? ((int) $legacy->cantidad_realizada ?: ($legacy->realizado ? 1 : 0))
                    : 0;

                if ($cantidad < 1) {
                    continue;
                }

                $ids = DB::table('sst_seguimiento_ocurrencias')
                    ->where('actividad_id', $actividad->id)
                    ->where('tipo', $actividad->periodicidad)
                    ->whereMonth('fecha_programada', $mes)
                    ->orderBy('fecha_programada')
                    ->limit($cantidad)
                    ->pluck('id');

                if ($ids->isNotEmpty()) {
                    DB::table('sst_seguimiento_ocurrencias')
                        ->whereIn('id', $ids)
                        ->update([
                            'realizado' => true,
                            'actualizado_por' => $legacy->actualizado_por,
                            'fecha_actualizacion' => $legacy->fecha_actualizacion,
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    private function occurrencesForRange(string $tipo, Carbon $inicio, Carbon $fin): array
    {
        $ocurrencias = [];

        if ($tipo === 'DIARIA') {
            for ($fecha = $inicio->copy()->startOfDay(); $fecha->lte($fin); $fecha->addDay()) {
                $ocurrencias[] = [
                    'fecha_programada' => $fecha->copy(),
                    'fecha_inicio' => $fecha->copy(),
                    'fecha_fin' => $fecha->copy(),
                ];
            }

            return $ocurrencias;
        }

        for ($periodoInicio = $inicio->copy()->startOfDay(); $periodoInicio->lte($fin);) {
            $periodoFin = $periodoInicio->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();
            if ($periodoFin->gt($fin)) {
                $periodoFin = $fin->copy()->startOfDay();
            }

            $ocurrencias[] = [
                'fecha_programada' => $periodoInicio->copy(),
                'fecha_inicio' => $periodoInicio->copy(),
                'fecha_fin' => $periodoFin->copy(),
            ];

            $periodoInicio = $periodoFin->copy()->addDay();
        }

        return $ocurrencias;
    }
};
