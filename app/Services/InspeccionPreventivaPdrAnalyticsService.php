<?php

namespace App\Services;

use App\Models\InspeccionPreventivaPdr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InspeccionPreventivaPdrAnalyticsService
{
    public function hasSyncedData(): bool
    {
        return InspeccionPreventivaPdr::query()->exists();
    }

    public function getSyncInfo(): ?array
    {
        $summary = InspeccionPreventivaPdr::query()
            ->selectRaw('COUNT(*) as total, MAX(synced_at) as last_sync, MAX(fecha_inspeccion) as latest_inspection')
            ->first();

        return !$summary || (int) $summary->total === 0 ? null : [
            'total' => (int) $summary->total,
            'last_sync' => $summary->last_sync,
            'latest_inspection' => $summary->latest_inspection,
        ];
    }

    public function getFilteredAnalytics(array $filters = []): array
    {
        $rows = $this->getFilteredRecords($filters);
        $total = $rows->count();
        $conditions = (int) $rows->sum('condiciones_count');
        $measures = (int) $rows->sum('medidas_count');
        $frequencies = $this->tokenCounts($rows, 'frecuencias_text');
        $verifications = $this->tokenCounts($rows, 'verificaciones_text');

        return [
            'total' => $total,
            'condiciones' => $conditions,
            'medidas' => $measures,
            'inmediatas' => $frequencies['Inmediata'] ?? 0,
            'porcentaje_inmediata' => $measures > 0 ? round((($frequencies['Inmediata'] ?? 0) / $measures) * 100, 1) : 0,
            'evidencias' => (int) $rows->sum('evidencias_count'),
            'centros_activos' => $rows->pluck('centro')->filter()->unique()->count(),
            'by_month' => $this->monthlyBreakdown($rows),
            'centros' => $this->groupCount($rows, 'centro', 10),
            'objetivos' => $this->groupCount($rows, 'objetivo', 10),
            'areas' => $this->groupCount($rows, 'area_inspeccionada', 10),
            'frecuencias' => $frequencies,
            'verificaciones' => $verifications,
            'inspectores' => $this->groupCount($rows, 'inspector_nombre', 10),
            'responsables' => $this->groupCount($rows, 'responsable_area', 10),
            'recent' => $rows->take(12)->values(),
            'filter_options' => $this->getFilterOptions(),
        ];
    }

    public function getFilteredRecords(array $filters = []): Collection
    {
        $query = InspeccionPreventivaPdr::query();
        $this->applyFilters($query, $filters);

        return $query->orderByDesc('fecha_inspeccion')->orderByDesc('id')->get();
    }

    public function getFilterOptions(): array
    {
        return [
            'centros' => $this->distinctValues('centro'),
            'objetivos' => $this->distinctValues('objetivo'),
            'inspectores' => $this->distinctValues('inspector_nombre'),
            'responsables' => $this->distinctValues('responsable_area'),
            'frecuencias' => array_keys($this->tokenCounts(InspeccionPreventivaPdr::query()->get(), 'frecuencias_text')),
            'verificaciones' => array_keys($this->tokenCounts(InspeccionPreventivaPdr::query()->get(), 'verificaciones_text')),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['centro', 'objetivo', 'inspector_nombre', 'responsable_area'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        foreach (['frecuencia' => 'frecuencias_text', 'verificacion' => 'verificaciones_text'] as $filter => $field) {
            if (!empty($filters[$filter])) {
                $query->where($field, 'like', '%|' . str_replace(['%', '_'], ['\\%', '\\_'], $filters[$filter]) . '|%');
            }
        }

        if (!empty($filters['fecha_desde'])) {
            $query->whereDate('fecha_inspeccion', '>=', $filters['fecha_desde']);
        }
        if (!empty($filters['fecha_hasta'])) {
            $query->whereDate('fecha_inspeccion', '<=', $filters['fecha_hasta']);
        }
    }

    private function distinctValues(string $field): array
    {
        return InspeccionPreventivaPdr::query()->whereNotNull($field)->where($field, '!=', '')
            ->distinct()->orderBy($field)->pluck($field)->all();
    }

    private function groupCount(Collection $rows, string $field, int $limit): array
    {
        return $rows->pluck($field)->filter()->countBy()->sortDesc()->take($limit)->all();
    }

    private function tokenCounts(Collection $rows, string $field): array
    {
        return $rows->flatMap(function ($row) use ($field) {
            return array_filter(explode('|', trim((string) $row->{$field}, '|')));
        })->countBy()->sortDesc()->all();
    }

    private function monthlyBreakdown(Collection $rows): array
    {
        return $rows->filter(fn ($row) => $row->fecha_inspeccion)
            ->groupBy(fn ($row) => $row->fecha_inspeccion->format('Y-m'))
            ->sortKeys()->map(function (Collection $monthRows, string $month) {
                return [
                    'label' => $month,
                    'inspecciones' => $monthRows->count(),
                    'condiciones' => (int) $monthRows->sum('condiciones_count'),
                    'medidas' => (int) $monthRows->sum('medidas_count'),
                ];
            })->values()->all();
    }
}
