<?php

namespace App\Services;

use App\Models\ProgramaSst;
use App\Models\SstActividad;
use Carbon\Carbon;

class CartaGanttPdfReportData
{
    public function build(ProgramaSst $programa, string $tipo, ?int $mes = null, ?int $semestre = null): array
    {
        $anio = (int) $programa->anio;
        $meses = match ($tipo) {
            'mensual' => [$mes],
            'semestral' => $semestre === 1 ? range(1, 6) : range(7, 12),
            default => range(1, 12),
        };
        $mesesNombres = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $mesesLargos = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $periodoEtiqueta = match ($tipo) {
            'mensual' => ucfirst($mesesLargos[$mes]) . ' ' . $anio,
            'semestral' => ($semestre === 1 ? 'Primer' : 'Segundo') . ' semestre ' . $anio,
            default => 'Año ' . $anio,
        };

        $todas = $programa->categorias->flatMap->actividades;
        $resumenes = [];
        $mesesData = array_fill_keys($meses, ['prog' => 0, 'real' => 0, 'pct' => 0]);
        $seleccionadas = $todas->filter(function (SstActividad $actividad) use ($meses, $anio, &$resumenes, &$mesesData) {
            $programado = 0;
            $realizado = 0;
            $vencida = false;
            foreach ($meses as $m) {
                $seg = $actividad->seguimiento_por_mes[$m] ?? null;
                if (!$seg || !$seg['programado']) {
                    continue;
                }
                $meta = max(1, (int) ($seg['cantidad_programada'] ?? $actividad->cantidad_programada ?? 1));
                $avance = max(0, (int) ($seg['cantidad_realizada'] ?? 0));
                $programado += $meta;
                $realizado += $avance;
                $mesesData[$m]['prog'] += $meta;
                $mesesData[$m]['real'] += $avance;
                if ($actividad->estado !== 'CANCELADA' && $avance < $meta && Carbon::create($anio, $m, 1)->endOfMonth()->isPast()) {
                    $vencida = true;
                }
            }
            if ($programado === 0) {
                return false;
            }
            $estado = $actividad->estado === 'CANCELADA' ? 'CANCELADA'
                : ($realizado >= $programado ? 'COMPLETADA' : ($realizado > 0 ? 'EN_PROGRESO' : 'PENDIENTE'));
            $resumenes[$actividad->id] = [
                'programado' => $programado,
                'realizado' => $realizado,
                'pct' => (int) round($realizado / $programado * 100),
                'estado' => $estado,
                'vencida' => $vencida,
            ];
            return true;
        })->values();

        foreach ($mesesData as &$dato) {
            $dato['pct'] = $dato['prog'] > 0 ? (int) round($dato['real'] / $dato['prog'] * 100) : 0;
        }
        unset($dato);

        $totalProgramado = array_sum(array_column($mesesData, 'prog'));
        $totalRealizado = array_sum(array_column($mesesData, 'real'));
        $categorias = [];
        foreach ($programa->categorias->sortBy('orden') as $categoria) {
            $actividades = $categoria->actividades->filter(fn ($actividad) => isset($resumenes[$actividad->id]));
            if ($actividades->isEmpty()) {
                continue;
            }
            $ids = $actividades->pluck('id');
            $meta = $ids->sum(fn ($id) => $resumenes[$id]['programado']);
            $real = $ids->sum(fn ($id) => $resumenes[$id]['realizado']);
            $categorias[] = [
                'nombre' => $categoria->nombre,
                'total' => $actividades->count(),
                'comp' => $ids->filter(fn ($id) => $resumenes[$id]['estado'] === 'COMPLETADA')->count(),
                'prog' => $ids->filter(fn ($id) => $resumenes[$id]['estado'] === 'EN_PROGRESO')->count(),
                'pend' => $ids->filter(fn ($id) => $resumenes[$id]['estado'] === 'PENDIENTE')->count(),
                'pct' => $meta > 0 ? (int) round($real / $meta * 100) : 0,
            ];
        }

        $semanas = [];
        $semanasPorActividad = [];
        $sinSemanaPorActividad = [];
        if ($tipo === 'mensual') {
            for ($dia = 1, $n = 1, $ultimo = Carbon::create($anio, $mes, 1)->daysInMonth; $dia <= $ultimo; $n++) {
                $inicio = Carbon::create($anio, $mes, $dia);
                $fin = min($ultimo, $dia + ((7 - $inicio->dayOfWeek) % 7));
                $clave = $inicio->toDateString();
                $semanas[$clave] = ['numero' => $n, 'inicio' => $clave, 'desde' => $dia, 'hasta' => $fin, 'realizado' => 0];
                $dia = $fin + 1;
            }

            foreach ($seleccionadas as $actividad) {
                $valores = array_fill_keys(array_keys($semanas), 0);
                if ($actividad->periodicidad === 'MENSUAL') {
                    foreach ($actividad->seguimientoSemanas->where('mes', $mes) as $registro) {
                        $clave = $registro->semana_inicio?->toDateString();
                        if (isset($valores[$clave])) {
                            $valores[$clave] += (int) $registro->cantidad_realizada;
                        }
                    }
                } elseif ($actividad->usaSeguimientoPorOcurrencia()) {
                    foreach ($actividad->ocurrencias->where('programado', true)->where('realizado', true) as $ocurrencia) {
                        $fecha = $ocurrencia->fecha_programada;
                        if (!$fecha || $fecha->year !== $anio || $fecha->month !== $mes) {
                            continue;
                        }
                        foreach ($semanas as $clave => $semana) {
                            if ($fecha->day >= $semana['desde'] && $fecha->day <= $semana['hasta']) {
                                $valores[$clave]++;
                                break;
                            }
                        }
                    }
                }
                $semanasPorActividad[$actividad->id] = $valores;
                $sinSemanaPorActividad[$actividad->id] = max(0, $resumenes[$actividad->id]['realizado'] - array_sum($valores));
                foreach ($valores as $clave => $cantidad) {
                    $semanas[$clave]['realizado'] += $cantidad;
                }
            }
        }

        return [
            'tipoReporte' => $tipo,
            'periodoEtiqueta' => $periodoEtiqueta,
            'mesesSeleccionados' => $meses,
            'mesesNombres' => $mesesNombres,
            'mesSeleccionado' => $mes,
            'semestreSeleccionado' => $semestre,
            'mesesData' => $mesesData,
            'actividadesPeriodo' => $seleccionadas,
            'resumenActividades' => $resumenes,
            'catStats' => $categorias,
            'semanasReporte' => $semanas,
            'semanasPorActividad' => $semanasPorActividad,
            'sinSemanaPorActividad' => $sinSemanaPorActividad,
            'totalProgramado' => $totalProgramado,
            'totalRealizado' => $totalRealizado,
            'pct' => $totalProgramado > 0 ? (int) round($totalRealizado / $totalProgramado * 100) : 0,
        ];
    }
}
