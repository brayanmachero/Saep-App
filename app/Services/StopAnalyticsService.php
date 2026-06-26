<?php

namespace App\Services;

use App\Models\StopObservacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StopAnalyticsService
{
    /**
     * Check if data has been synced to the local database.
     */
    public function hasSyncedData(): bool
    {
        return StopObservacion::exists();
    }

    /**
     * Get the latest sync info (file id, row count, last update).
     */
    public function getSyncInfo(): ?array
    {
        $row = StopObservacion::selectRaw('COUNT(*) as total, MAX(updated_at) as last_sync')
            ->first();

        if (!$row || (int) $row->total === 0) return null;

        $fileId = StopObservacion::whereNotNull('gdrive_file_id')
            ->latest('updated_at')
            ->value('gdrive_file_id');

        return [
            'fileId'   => $fileId,
            'total'    => $row->total,
            'lastSync' => $row->last_sync,
        ];
    }

    /**
     * Get filtered analytics from MySQL — replaces GoogleDriveService::getFilteredAnalytics().
     * Returns the same data structure for full backward compatibility.
     */
    public function getFilteredAnalytics(array $filters = []): array
    {
        $query = StopObservacion::query();
        $this->applyFilters($query, $filters);

        // Total count
        $totalRows = (clone $query)->count();

        if ($totalRows === 0) {
            return ['totalRows' => 0, 'filterOptions' => $this->getFilterOptions()];
        }

        // All aggregations in parallel using raw SQL for performance
        $baseWhere = $this->buildWhereClause($filters);
        $whereSQL = $baseWhere['sql'];
        $bindings = $baseWhere['bindings'];

        // Single comprehensive query using conditional aggregation
        $clasificacion = DB::table('stop_observaciones')
            ->selectRaw('clasificacion, COUNT(*) as cnt')
            ->whereRaw($whereSQL ?: '1=1', $bindings)
            ->where('clasificacion', '!=', '')
            ->whereNotNull('clasificacion')
            ->groupBy('clasificacion')
            ->pluck('cnt', 'clasificacion')
            ->toArray();
        arsort($clasificacion);

        $centros = $this->groupCount('centro', $whereSQL, $bindings);
        $areas = $this->groupCount('area_proceso', $whereSQL, $bindings);
        $tiposObservacion = $this->groupCount('tipo_observacion', $whereSQL, $bindings);
        $internoExterno = $this->groupCount('interno_externo', $whereSQL, $bindings);
        $turnos = $this->groupCount('turno', $whereSQL, $bindings);
        $antiguedades = $this->groupCount('antiguedad', $whereSQL, $bindings);

        $empresas = $this->groupCount('empresa_observado', $whereSQL, $bindings, 15);
        $empresasObs = $this->groupCount('empresa_observador', $whereSQL, $bindings, 15);
        $cargos = $this->groupCount('cargo_observado', $whereSQL, $bindings, 15);

        // Top observadores (por nombre_observador)
        $topObservadores = $this->groupCountUpper('nombre_observador', $whereSQL, $bindings, 20);

        // Rankings neg/pos trabajadores (nombre_observado)
        $topNegTrabajadores = $this->groupCountUpperWithClasif('nombre_observado', 'Negativa', $whereSQL, $bindings, 20);
        $topPosTrabajadores = $this->groupCountUpperWithClasif('nombre_observado', 'Positiva', $whereSQL, $bindings, 20);

        // Neg/Pos por tipo
        $negPorTipo = $this->groupCountWithClasif('tipo_observacion', 'Negativa', $whereSQL, $bindings);
        $posPorTipo = $this->groupCountWithClasif('tipo_observacion', 'Positiva', $whereSQL, $bindings);

        // By month timeline
        $byMonth = $this->monthlyBreakdown($whereSQL, $bindings);
        $byMonthNeg = $this->monthlyBreakdown($whereSQL, $bindings, 'Negativa');
        $byMonthPos = $this->monthlyBreakdown($whereSQL, $bindings, 'Positiva');

        // By year
        $byYear = DB::table('stop_observaciones')
            ->selectRaw("YEAR(COALESCE(fecha_tarjeta, marca_temporal)) as yr, COUNT(*) as cnt")
            ->whereRaw($whereSQL ?: '1=1', $bindings)
            ->whereRaw("COALESCE(fecha_tarjeta, marca_temporal) IS NOT NULL")
            ->groupBy('yr')
            ->orderBy('yr')
            ->pluck('cnt', 'yr')
            ->mapWithKeys(fn($v, $k) => [(string)$k => $v])
            ->toArray();

        // Desgloses neg/pos por dimensión
        $centrosNeg = $this->groupCountWithClasif('centro', 'Negativa', $whereSQL, $bindings);
        $centrosPos = $this->groupCountWithClasif('centro', 'Positiva', $whereSQL, $bindings);
        $areasNeg = $this->groupCountWithClasif('area_proceso', 'Negativa', $whereSQL, $bindings);
        $areasPos = $this->groupCountWithClasif('area_proceso', 'Positiva', $whereSQL, $bindings);
        $empresasNeg = $this->groupCountWithClasif('empresa_observado', 'Negativa', $whereSQL, $bindings);
        $empresasPos = $this->groupCountWithClasif('empresa_observado', 'Positiva', $whereSQL, $bindings);
        $observadoresNeg = $this->groupCountUpperWithClasif('nombre_observador', 'Negativa', $whereSQL, $bindings);
        $observadoresPos = $this->groupCountUpperWithClasif('nombre_observador', 'Positiva', $whereSQL, $bindings);

        return [
            'totalRows'           => $totalRows,
            'clasificacion'       => $clasificacion,
            'centros'             => $centros,
            'areas'               => $areas,
            'tiposObservacion'    => $tiposObservacion,
            'internoExterno'      => $internoExterno,
            'empresas'            => $empresas,
            'empresasObservador'  => $empresasObs,
            'turnos'              => $turnos,
            'antiguedades'        => $antiguedades,
            'cargos'              => $cargos,
            'topObservadores'     => $topObservadores,
            'negPorTipo'          => $negPorTipo,
            'posPorTipo'          => $posPorTipo,
            'topNegTrabajadores'  => $topNegTrabajadores,
            'topPosTrabajadores'  => $topPosTrabajadores,
            'byMonth'             => $byMonth,
            'byMonthNeg'          => $byMonthNeg,
            'byMonthPos'          => $byMonthPos,
            'byYear'              => $byYear,
            'centrosNeg'          => $centrosNeg,
            'centrosPos'          => $centrosPos,
            'areasNeg'            => $areasNeg,
            'areasPos'            => $areasPos,
            'empresasNeg'         => $empresasNeg,
            'empresasPos'         => $empresasPos,
            'observadoresNeg'     => $observadoresNeg,
            'observadoresPos'     => $observadoresPos,
            'filterOptions'       => $this->getFilterOptions(),
        ];
    }

    /**
     * Get checklist analytics from MySQL — replaces GoogleDriveService::getChecklistAnalytics().
     */
    public function getChecklistAnalytics(array $filters = []): ?array
    {
        $query = StopObservacion::whereNotNull('checklist_data');
        $this->applyFilters($query, $filters);

        $rows = $query->select('checklist_data')->cursor();

        $catStats = [];
        $questionStats = [];

        foreach ($rows as $row) {
            $items = $row->checklist_data;
            if (!is_array($items)) continue;

            foreach ($items as $item) {
                $cat = $item['cat'] ?? '';
                $q = $item['q'] ?? '';
                $val = strtoupper($item['val'] ?? '');

                if ($cat === '' || $val === '') continue;

                if (!isset($catStats[$cat])) {
                    $catStats[$cat] = ['cumple' => 0, 'no_cumple' => 0, 'total' => 0];
                }
                if (!isset($questionStats[$cat][$q])) {
                    $questionStats[$cat][$q] = ['cumple' => 0, 'no_cumple' => 0];
                }

                if (str_contains($val, 'NO CUMPLE')) {
                    $catStats[$cat]['no_cumple']++;
                    $catStats[$cat]['total']++;
                    $questionStats[$cat][$q]['no_cumple']++;
                } elseif (str_contains($val, 'CUMPLE')) {
                    $catStats[$cat]['cumple']++;
                    $catStats[$cat]['total']++;
                    $questionStats[$cat][$q]['cumple']++;
                }
            }
        }

        $categories = [];
        foreach ($catStats as $catName => $stats) {
            if ($stats['total'] === 0) continue;

            $pct = round(($stats['cumple'] / $stats['total']) * 100, 1);
            $questions = $questionStats[$catName] ?? [];
            uasort($questions, fn($a, $b) => $b['no_cumple'] - $a['no_cumple']);

            $categories[$catName] = [
                'cumple'     => $stats['cumple'],
                'no_cumple'  => $stats['no_cumple'],
                'total'      => $stats['total'],
                'pct_cumple' => $pct,
                'questions'  => $questions,
            ];
        }

        uasort($categories, fn($a, $b) => $a['pct_cumple'] <=> $b['pct_cumple']);

        return ['categories' => $categories];
    }

    /**
     * Get evaluation detail from MySQL — replaces GoogleDriveService::getEvaluationDetail().
     */
    public function getEvaluationDetail(array $filters = []): ?array
    {
        $query = StopObservacion::query()
            ->whereNotNull('checklist_data')
            ->where('checklist_data', '!=', 'null');

        $this->applyFilters($query, $filters);

        $rows = $query->select([
            'marca_temporal', 'fecha_tarjeta', 'centro', 'empresa_observador',
            'nombre_observador', 'clasificacion', 'turno', 'nombre_observado',
            'antiguedad', 'area_proceso', 'empresa_observado', 'cargo_observado',
            'tipo_observacion', 'checklist_data',
        ])->cursor();

        $workerRows = [];
        $itemNoCumple = [];
        $itemCumple = [];

        foreach ($rows as $row) {
            $items = $row->checklist_data;
            if (!is_array($items)) continue;

            $noCumpleItems = [];
            $cumpleItems = [];

            foreach ($items as $item) {
                $val = strtoupper($item['val'] ?? '');
                $cat = $item['cat'] ?? '';
                $q = $item['q'] ?? '';
                $key = $cat . ' | ' . $q;

                if (str_contains($val, 'NO CUMPLE')) {
                    $noCumpleItems[] = $cat . ' → ' . $q;
                    $itemNoCumple[$key] = ($itemNoCumple[$key] ?? 0) + 1;
                } elseif (str_contains($val, 'CUMPLE')) {
                    $cumpleItems[] = $cat . ' → ' . $q;
                    $itemCumple[$key] = ($itemCumple[$key] ?? 0) + 1;
                }
            }

            $isNeg = strtolower($row->clasificacion ?? '') === 'negativa';
            if ($isNeg && (!empty($noCumpleItems) || !empty($cumpleItems))) {
                $fecha = $row->fecha_tarjeta
                    ? $row->fecha_tarjeta->format('d/m/Y')
                    : ($row->marca_temporal ? $row->marca_temporal->format('d/m/Y') : '');
                $monthKey = $row->fecha_tarjeta
                    ? $row->fecha_tarjeta->format('Y-m')
                    : ($row->marca_temporal ? $row->marca_temporal->format('Y-m') : null);

                $workerRows[] = [
                    'fecha'       => $fecha,
                    'mes'         => $monthKey,
                    'trabajador'  => $row->nombre_observado ?? '',
                    'centro'      => $row->centro ?? '',
                    'area'        => $row->area_proceso ?? '',
                    'empresa'     => $row->empresa_observado ?? '',
                    'cargo'       => $row->cargo_observado ?? '',
                    'antiguedad'  => $row->antiguedad ?? '',
                    'tipoObs'     => $row->tipo_observacion ?? '',
                    'observador'  => $row->nombre_observador ?? '',
                    'turno'       => $row->turno ?? '',
                    'noCumple'    => $noCumpleItems,
                    'cumple'      => $cumpleItems,
                    'totalNC'     => count($noCumpleItems),
                    'totalC'      => count($cumpleItems),
                ];
            }
        }

        arsort($itemNoCumple);
        arsort($itemCumple);

        usort($workerRows, fn($a, $b) => $b['totalNC'] <=> $a['totalNC'] ?: strcmp($a['trabajador'], $b['trabajador']));

        return [
            'workers'     => $workerRows,
            'itemRanking' => array_slice($itemNoCumple, 0, 30, true),
            'itemCumple'  => array_slice($itemCumple, 0, 30, true),
            'totalNeg'    => count($workerRows),
            'evalCols'    => [],
        ];
    }

    /**
     * Get filter options (always unfiltered — all distinct values).
     */
    public function getFilterOptions(): array
    {
        return [
            'empresas_observador' => StopObservacion::distinct()
                ->whereNotNull('empresa_observador')->where('empresa_observador', '!=', '')
                ->orderBy('empresa_observador')
                ->pluck('empresa_observador')->toArray(),
            'empresas_observado' => StopObservacion::distinct()
                ->whereNotNull('empresa_observado')->where('empresa_observado', '!=', '')
                ->orderBy('empresa_observado')
                ->pluck('empresa_observado')->toArray(),
            'tipos_observacion' => StopObservacion::distinct()
                ->whereNotNull('tipo_observacion')->where('tipo_observacion', '!=', '')
                ->orderBy('tipo_observacion')
                ->pluck('tipo_observacion')->toArray(),
            'centros' => StopObservacion::distinct()
                ->whereNotNull('centro')->where('centro', '!=', '')
                ->orderBy('centro')
                ->pluck('centro')->toArray(),
            'anios' => StopObservacion::selectRaw("DISTINCT YEAR(COALESCE(fecha_tarjeta, marca_temporal)) as yr")
                ->whereRaw("COALESCE(fecha_tarjeta, marca_temporal) IS NOT NULL")
                ->orderBy('yr')
                ->pluck('yr')
                ->map(fn($v) => (string) $v)
                ->toArray(),
        ];
    }

    // ─── Private helpers ──────────────────────────────────────────

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['empresa_observador'])) {
            $query->where('empresa_observador', $filters['empresa_observador']);
        }
        if (!empty($filters['empresa_observado'])) {
            $query->where('empresa_observado', $filters['empresa_observado']);
        }
        if (!empty($filters['tipo_observacion'])) {
            $query->where('tipo_observacion', $filters['tipo_observacion']);
        }
        if (!empty($filters['centro'])) {
            $query->where('centro', $filters['centro']);
        }
        if (!empty($filters['clasificacion'])) {
            $query->where('clasificacion', $filters['clasificacion']);
        }
        if (!empty($filters['fecha_desde'])) {
            $query->whereRaw("COALESCE(fecha_tarjeta, DATE(marca_temporal)) >= ?", [$filters['fecha_desde']]);
        }
        if (!empty($filters['fecha_hasta'])) {
            $query->whereRaw("COALESCE(fecha_tarjeta, DATE(marca_temporal)) <= ?", [$filters['fecha_hasta']]);
        }
        if (!empty($filters['mes'])) {
            $query->whereRaw("DATE_FORMAT(COALESCE(fecha_tarjeta, marca_temporal), '%Y-%m') = ?", [$filters['mes']]);
        }
        if (!empty($filters['anio'])) {
            $query->whereRaw("YEAR(COALESCE(fecha_tarjeta, marca_temporal)) = ?", [$filters['anio']]);
        }
    }

    private function buildWhereClause(array $filters): array
    {
        $conditions = [];
        $bindings = [];

        if (!empty($filters['empresa_observador'])) {
            $conditions[] = "empresa_observador = ?";
            $bindings[] = $filters['empresa_observador'];
        }
        if (!empty($filters['empresa_observado'])) {
            $conditions[] = "empresa_observado = ?";
            $bindings[] = $filters['empresa_observado'];
        }
        if (!empty($filters['tipo_observacion'])) {
            $conditions[] = "tipo_observacion = ?";
            $bindings[] = $filters['tipo_observacion'];
        }
        if (!empty($filters['centro'])) {
            $conditions[] = "centro = ?";
            $bindings[] = $filters['centro'];
        }
        if (!empty($filters['clasificacion'])) {
            $conditions[] = "clasificacion = ?";
            $bindings[] = $filters['clasificacion'];
        }
        if (!empty($filters['fecha_desde'])) {
            $conditions[] = "COALESCE(fecha_tarjeta, DATE(marca_temporal)) >= ?";
            $bindings[] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $conditions[] = "COALESCE(fecha_tarjeta, DATE(marca_temporal)) <= ?";
            $bindings[] = $filters['fecha_hasta'];
        }
        if (!empty($filters['mes'])) {
            $conditions[] = "DATE_FORMAT(COALESCE(fecha_tarjeta, marca_temporal), '%Y-%m') = ?";
            $bindings[] = $filters['mes'];
        }
        if (!empty($filters['anio'])) {
            $conditions[] = "YEAR(COALESCE(fecha_tarjeta, marca_temporal)) = ?";
            $bindings[] = $filters['anio'];
        }

        return [
            'sql'      => !empty($conditions) ? implode(' AND ', $conditions) : '1=1',
            'bindings' => $bindings,
        ];
    }

    private function groupCount(string $column, string $whereSQL, array $bindings, ?int $limit = null): array
    {
        $q = DB::table('stop_observaciones')
            ->selectRaw("{$column} as val, COUNT(*) as cnt")
            ->whereRaw($whereSQL, $bindings)
            ->where($column, '!=', '')
            ->whereNotNull($column)
            ->groupBy('val')
            ->orderByDesc('cnt');

        if ($limit) $q->limit($limit);

        return $q->pluck('cnt', 'val')->toArray();
    }

    private function groupCountUpper(string $column, string $whereSQL, array $bindings, ?int $limit = null): array
    {
        $invalidNames = ['', 'SIN NOMBRE', 'N/A', 'S/N', '-', '.', 'X', 'XX', 'XXX', 'PRUEBA'];
        $placeholders = implode(',', array_fill(0, count($invalidNames), '?'));

        $q = DB::table('stop_observaciones')
            ->selectRaw("UPPER({$column}) as val, COUNT(*) as cnt")
            ->whereRaw($whereSQL, $bindings)
            ->where($column, '!=', '')
            ->whereNotNull($column)
            ->whereRaw("UPPER(TRIM({$column})) NOT IN ({$placeholders})", $invalidNames)
            ->whereRaw("CHAR_LENGTH(TRIM({$column})) >= 3")
            ->groupBy('val')
            ->orderByDesc('cnt');

        if ($limit) $q->limit($limit);

        return $q->pluck('cnt', 'val')->toArray();
    }

    private function groupCountWithClasif(string $column, string $clasificacion, string $whereSQL, array $bindings): array
    {
        return DB::table('stop_observaciones')
            ->selectRaw("{$column} as val, COUNT(*) as cnt")
            ->whereRaw($whereSQL, array_merge($bindings))
            ->where($column, '!=', '')
            ->whereNotNull($column)
            ->where('clasificacion', $clasificacion)
            ->groupBy('val')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'val')
            ->toArray();
    }

    private function groupCountUpperWithClasif(string $column, string $clasificacion, string $whereSQL, array $bindings, ?int $limit = null): array
    {
        $invalidNames = ['', 'SIN NOMBRE', 'N/A', 'S/N', '-', '.', 'X', 'XX', 'XXX', 'PRUEBA'];
        $placeholders = implode(',', array_fill(0, count($invalidNames), '?'));

        $q = DB::table('stop_observaciones')
            ->selectRaw("UPPER({$column}) as val, COUNT(*) as cnt")
            ->whereRaw($whereSQL, $bindings)
            ->where($column, '!=', '')
            ->whereNotNull($column)
            ->where('clasificacion', $clasificacion)
            ->whereRaw("UPPER(TRIM({$column})) NOT IN ({$placeholders})", $invalidNames)
            ->whereRaw("CHAR_LENGTH(TRIM({$column})) >= 3")
            ->groupBy('val')
            ->orderByDesc('cnt');

        if ($limit) $q->limit($limit);

        return $q->pluck('cnt', 'val')->toArray();
    }

    private function monthlyBreakdown(string $whereSQL, array $bindings, ?string $clasificacion = null): array
    {
        $q = DB::table('stop_observaciones')
            ->selectRaw("DATE_FORMAT(COALESCE(fecha_tarjeta, marca_temporal), '%Y-%m') as ym, COUNT(*) as cnt")
            ->whereRaw($whereSQL, $bindings)
            ->whereRaw("COALESCE(fecha_tarjeta, marca_temporal) IS NOT NULL");

        if ($clasificacion) {
            $q->where('clasificacion', $clasificacion);
        }

        return $q->groupBy('ym')
            ->orderBy('ym')
            ->pluck('cnt', 'ym')
            ->toArray();
    }

    /**
     * Build year-over-year comparison from SQL data.
     * Carries forward all non-date filters (empresa_observado, centro, etc.).
     */
    public function buildComparison(array $baseFilters): array
    {
        $period = $this->resolveComparisonPeriod($baseFilters);

        // Carry over non-date filters
        $carryFilters = array_filter([
            'empresa_observador' => $baseFilters['empresa_observador'] ?? null,
            'empresa_observado'  => $baseFilters['empresa_observado'] ?? null,
            'centro'             => $baseFilters['centro'] ?? null,
            'tipo_observacion'   => $baseFilters['tipo_observacion'] ?? null,
            'clasificacion'      => $baseFilters['clasificacion'] ?? null,
        ]);

        try {
            $ytdData = $this->getFilteredAnalytics(array_merge($period['current_ytd'], $carryFilters));
            $prevData = $this->getFilteredAnalytics(array_merge($period['previous_period'], $carryFilters));
            $prevYtdData = $this->getFilteredAnalytics(array_merge($period['previous_ytd'], $carryFilters));
        } catch (\Throwable $e) {
            return [];
        }

        $ytdClasif  = $ytdData['clasificacion'] ?? [];
        $prevPeriodClasif = $prevData['clasificacion'] ?? [];
        $prevYtdClasif = $prevYtdData['clasificacion'] ?? [];

        $samePeriodTotal = $prevData['totalRows'] ?? 0;
        $samePeriodPos = $prevPeriodClasif['Positiva'] ?? $prevPeriodClasif['positiva'] ?? 0;
        $samePeriodNeg = $prevPeriodClasif['Negativa'] ?? $prevPeriodClasif['negativa'] ?? 0;

        return [
            'currentYear' => $period['current_year'],
            'ytd' => [
                'total'      => $ytdData['totalRows'] ?? 0,
                'pos'        => $ytdClasif['Positiva'] ?? $ytdClasif['positiva'] ?? 0,
                'neg'        => $ytdClasif['Negativa'] ?? $ytdClasif['negativa'] ?? 0,
                'topNeg'     => $ytdData['topNegTrabajadores'] ?? [],
                'negPorTipo' => $ytdData['negPorTipo'] ?? [],
                'byMonth'    => $ytdData['byMonth'] ?? [],
                'byMonthNeg' => $ytdData['byMonthNeg'] ?? [],
                'byMonthPos' => $ytdData['byMonthPos'] ?? [],
            ],
            'prevYear' => [
                'year'           => $period['previous_year'],
                'total'          => $prevData['totalRows'] ?? 0,
                'pos'            => $samePeriodPos,
                'neg'            => $samePeriodNeg,
                'sameMonthTotal' => $samePeriodTotal,
                'sameMonthPos'   => $samePeriodPos,
                'sameMonthNeg'   => $samePeriodNeg,
                'ytdTotal'       => $prevYtdData['totalRows'] ?? 0,
                'ytdPos'         => $prevYtdClasif['Positiva'] ?? $prevYtdClasif['positiva'] ?? 0,
                'ytdNeg'         => $prevYtdClasif['Negativa'] ?? $prevYtdClasif['negativa'] ?? 0,
                'byMonth'        => $prevYtdData['byMonth'] ?? [],
                'byMonthNeg'     => $prevYtdData['byMonthNeg'] ?? [],
                'byMonthPos'     => $prevYtdData['byMonthPos'] ?? [],
            ],
        ];
    }

    private function resolveComparisonPeriod(array $baseFilters): array
    {
        if (!empty($baseFilters['fecha_desde']) && !empty($baseFilters['fecha_hasta'])) {
            $startDate = Carbon::parse($baseFilters['fecha_desde']);
            $endDate = Carbon::parse($baseFilters['fecha_hasta']);

            if ($endDate->lt($startDate)) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            $previousStart = $startDate->copy()->subYear();
            $previousEnd = $endDate->copy()->subYear();

            return [
                'current_year' => (int) $endDate->format('Y'),
                'previous_year' => (int) $previousEnd->format('Y'),
                'current_ytd' => [
                    'fecha_desde' => $endDate->copy()->startOfYear()->format('Y-m-d'),
                    'fecha_hasta' => $endDate->format('Y-m-d'),
                ],
                'previous_period' => [
                    'fecha_desde' => $previousStart->format('Y-m-d'),
                    'fecha_hasta' => $previousEnd->format('Y-m-d'),
                ],
                'previous_ytd' => [
                    'fecha_desde' => $previousEnd->copy()->startOfYear()->format('Y-m-d'),
                    'fecha_hasta' => $previousEnd->format('Y-m-d'),
                ],
            ];
        }

        if (!empty($baseFilters['mes'])) {
            $month = Carbon::createFromFormat('Y-m', $baseFilters['mes'])->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();
            $previousMonth = $month->copy()->subYear();

            return [
                'current_year' => (int) $month->format('Y'),
                'previous_year' => (int) $previousMonth->format('Y'),
                'current_ytd' => [
                    'fecha_desde' => $month->copy()->startOfYear()->format('Y-m-d'),
                    'fecha_hasta' => $monthEnd->format('Y-m-d'),
                ],
                'previous_period' => [
                    'mes' => $previousMonth->format('Y-m'),
                ],
                'previous_ytd' => [
                    'fecha_desde' => $previousMonth->copy()->startOfYear()->format('Y-m-d'),
                    'fecha_hasta' => $previousMonth->copy()->endOfMonth()->format('Y-m-d'),
                ],
            ];
        }

        if (!empty($baseFilters['anio'])) {
            $currentYear = (int) $baseFilters['anio'];
            $previousYear = $currentYear - 1;

            return [
                'current_year' => $currentYear,
                'previous_year' => $previousYear,
                'current_ytd' => ['anio' => (string) $currentYear],
                'previous_period' => ['anio' => (string) $previousYear],
                'previous_ytd' => ['anio' => (string) $previousYear],
            ];
        }

        $today = now();
        $previousDate = $today->copy()->subYear();

        return [
            'current_year' => (int) $today->format('Y'),
            'previous_year' => (int) $previousDate->format('Y'),
            'current_ytd' => [
                'fecha_desde' => $today->copy()->startOfYear()->format('Y-m-d'),
                'fecha_hasta' => $today->format('Y-m-d'),
            ],
            'previous_period' => [
                'mes' => $previousDate->format('Y-m'),
            ],
            'previous_ytd' => [
                'fecha_desde' => $previousDate->copy()->startOfYear()->format('Y-m-d'),
                'fecha_hasta' => $previousDate->format('Y-m-d'),
            ],
        ];
    }
}
