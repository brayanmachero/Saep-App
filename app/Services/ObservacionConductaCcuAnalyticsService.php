<?php

namespace App\Services;

use App\Models\ObservacionConductaCcu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ObservacionConductaCcuAnalyticsService
{
    private const TURNOS_KIZEO = ['Turno A', 'Turno B', 'Turno C'];

    public function hasSyncedData(): bool
    {
        return ObservacionConductaCcu::query()->exists();
    }

    public function getSyncInfo(): ?array
    {
        $summary = ObservacionConductaCcu::query()
            ->selectRaw('COUNT(*) as total, MAX(synced_at) as last_sync, MAX(fecha_observacion) as latest_observation')
            ->first();

        if (!$summary || (int) $summary->total === 0) {
            return null;
        }

        return [
            'total' => (int) $summary->total,
            'last_sync' => $summary->last_sync,
            'latest_observation' => $summary->latest_observation,
        ];
    }

    public function getFilteredAnalytics(array $filters = []): array
    {
        $query = ObservacionConductaCcu::query();
        $this->applyFilters($query, $filters);
        $rows = $query->orderByDesc('fecha_observacion')->orderByDesc('id')->get();
        $total = $rows->count();
        $positivas = $rows->where('clasificacion', 'Positiva')->count();
        $negativas = $rows->where('clasificacion', 'Negativa')->count();
        $porRevisar = $rows->where('clasificacion', 'Por revisar')->count();

        return [
            'total' => $total,
            'positivas' => $positivas,
            'negativas' => $negativas,
            'por_revisar' => $porRevisar,
            'porcentaje_positivo' => $total > 0 ? round(($positivas / $total) * 100, 1) : 0,
            'centros_activos' => $rows->pluck('centro')->filter()->unique()->count(),
            'observadores_activos' => $rows->pluck('observador_nombre')->filter()->unique()->count(),
            'trabajadores_activos' => $rows->pluck('trabajador_nombre')->filter()->unique()->count(),
            'by_month' => $this->monthlyBreakdown($rows),
            'centros' => $this->groupCount($rows, 'centro', 10),
            'turnos' => $rows
                ->map(fn ($row) => $row->turno ?: 'Sin turno')
                ->countBy()
                ->sortDesc()
                ->all(),
            'tipos' => $this->groupCount($rows, 'tipo_observacion', 10),
            'cargos' => $this->groupCount($rows, 'trabajador_cargo', 10),
            'antiguedades' => $this->groupCount($rows, 'antiguedad_cargo', 10),
            'medidas' => $this->controlMeasureCount($rows->where('clasificacion', '!=', 'Positiva'), 8),
            'top_observadores' => $this->groupCount($rows, 'observador_nombre', 10),
            'top_trabajadores_negativos' => $this->groupCount($rows->where('clasificacion', 'Negativa'), 'trabajador_nombre', 10),
            'recent' => $rows->take(12)->values(),
            'filter_options' => $this->getFilterOptions(),
        ];
    }

    public function getFilteredRecords(array $filters = []): Collection
    {
        $query = ObservacionConductaCcu::query();
        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('fecha_observacion')
            ->orderByDesc('id')
            ->get();
    }

    public function getFilterOptions(): array
    {
        return [
            'centros' => $this->distinctValues('centro'),
            'turnos' => collect([...self::TURNOS_KIZEO, ...$this->distinctValues('turno')])
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'observadores' => $this->distinctValues('observador_nombre'),
            'trabajadores' => $this->distinctValues('trabajador_nombre'),
            'tipos' => $this->distinctValues('tipo_observacion'),
            'anios' => ObservacionConductaCcu::query()
                ->whereNotNull('fecha_observacion')
                ->get(['fecha_observacion'])
                ->pluck('fecha_observacion')
                ->filter()
                ->map(fn ($date) => (string) $date->year)
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['centro', 'turno', 'clasificacion', 'observador_nombre', 'trabajador_nombre', 'tipo_observacion'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (!empty($filters['fecha_desde'])) {
            $query->whereDate('fecha_observacion', '>=', $filters['fecha_desde']);
        }

        if (!empty($filters['fecha_hasta'])) {
            $query->whereDate('fecha_observacion', '<=', $filters['fecha_hasta']);
        }
    }

    private function distinctValues(string $field): array
    {
        return ObservacionConductaCcu::query()
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->distinct()
            ->orderBy($field)
            ->pluck($field)
            ->all();
    }

    private function groupCount(Collection $rows, string $field, int $limit): array
    {
        return $rows
            ->pluck($field)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take($limit)
            ->all();
    }

    private function controlMeasureCount(Collection $rows, int $limit): array
    {
        return $rows
            ->map(fn ($row) => $this->controlMeasureLabel($row->medida_control))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take($limit)
            ->all();
    }

    private function controlMeasureLabel(?string $value): ?string
    {
        return match (strtoupper(trim((string) $value))) {
            'RI' => 'Reinducción inmediata (RI)',
            '' => null,
            default => $value,
        };
    }

    private function monthlyBreakdown(Collection $rows): array
    {
        return $rows
            ->filter(fn ($row) => $row->fecha_observacion)
            ->groupBy(fn ($row) => $row->fecha_observacion->format('Y-m'))
            ->sortKeys()
            ->map(function (Collection $monthRows, string $month) {
                return [
                    'label' => $month,
                    'total' => $monthRows->count(),
                    'positivas' => $monthRows->where('clasificacion', 'Positiva')->count(),
                    'negativas' => $monthRows->where('clasificacion', 'Negativa')->count(),
                    'por_revisar' => $monthRows->where('clasificacion', 'Por revisar')->count(),
                ];
            })
            ->values()
            ->all();
    }
}
