<?php

namespace App\Services;

use App\Models\EntregaBodega;
use App\Models\EntregaBodegaItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EntregaBodegaAnalyticsService
{
    public function hasSyncedData(): bool
    {
        return $this->currentDeliveries()->exists();
    }

    public function getSyncInfo(): ?array
    {
        $summary = $this->currentDeliveries()
            ->selectRaw('COUNT(*) as total, MAX(synced_at) as last_sync, MAX(fecha_pedido) as latest_delivery')
            ->first();

        return ! $summary || (int) $summary->total === 0 ? null : [
            'total' => (int) $summary->total,
            'last_sync' => $summary->last_sync,
            'latest_delivery' => $summary->latest_delivery,
        ];
    }

    public function getFilteredAnalytics(array $filters = []): array
    {
        $rows = $this->getFilteredRecords($filters);
        $items = $rows->flatMap(fn (EntregaBodega $entrega) => $entrega->items);
        $people = $rows->filter(fn (EntregaBodega $entrega) => filled($entrega->nombre));

        return [
            'total' => $rows->count(),
            'unidades' => (int) $rows->sum('unidades_total'),
            'lineas' => (int) $rows->sum('lineas_count'),
            'personas' => $people->pluck('nombre')->unique()->count(),
            'centros_activos' => $rows->pluck('centro')->filter()->unique()->count(),
            'promedio_unidades' => $rows->isNotEmpty() ? round($rows->avg('unidades_total'), 1) : 0,
            'by_day' => $this->dailyBreakdown($rows),
            'by_month' => $this->monthlyBreakdown($rows),
            'centros' => $this->groupCount($rows, 'centro', 10),
            'articulos' => $this->groupItemUnits($items, 'articulo', 8),
            'tallas' => $this->groupItemUnits($items, 'talla', 8),
            'personas_top' => $this->peopleBreakdown($people),
            'relaciones' => $this->centerPeopleBreakdown($people),
            'recent' => $rows->take(14)->values(),
            'filter_options' => $this->getFilterOptions(),
        ];
    }

    public function getFilteredRecords(array $filters = []): Collection
    {
        $query = $this->currentDeliveries()->with('items');
        $this->applyFilters($query, $filters);

        return $query->orderByDesc('fecha_pedido')->orderByDesc('id')->get();
    }

    public function getFilterOptions(): array
    {
        return [
            'centros' => $this->distinctValues($this->currentDeliveries(), 'centro'),
            'trabajadores' => $this->distinctValues($this->currentDeliveries(), 'nombre'),
            'articulos' => $this->distinctValues(
                EntregaBodegaItem::query()->whereHas('entrega', fn (Builder $query) => $this->onlyCurrentForms($query)),
                'articulo',
            ),
            'tallas' => $this->distinctValues(
                EntregaBodegaItem::query()->whereHas('entrega', fn (Builder $query) => $this->onlyCurrentForms($query)),
                'talla',
            ),
        ];
    }

    private function currentDeliveries(): Builder
    {
        return $this->onlyCurrentForms(EntregaBodega::query());
    }

    private function onlyCurrentForms(Builder $query): Builder
    {
        return $query->whereIn('kizeo_form_id', EntregaBodegaSyncService::currentFormIds());
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['centro', 'trabajador'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field === 'trabajador' ? 'nombre' : $field, $filters[$field]);
            }
        }

        foreach (['articulo', 'talla'] as $field) {
            if (! empty($filters[$field])) {
                $query->whereHas('items', fn (Builder $items) => $items->where($field, $filters[$field]));
            }
        }

        if (! empty($filters['fecha_desde'])) {
            $query->whereDate('fecha_pedido', '>=', $filters['fecha_desde']);
        }
        if (! empty($filters['fecha_hasta'])) {
            $query->whereDate('fecha_pedido', '<=', $filters['fecha_hasta']);
        }
    }

    private function distinctValues(Builder $query, string $field): array
    {
        return $query->whereNotNull($field)->where($field, '!=', '')
            ->distinct()->orderBy($field)->pluck($field)->all();
    }

    private function groupCount(Collection $rows, string $field, int $limit): array
    {
        return $rows->pluck($field)->filter()->countBy()->sortDesc()->take($limit)->all();
    }

    private function groupItemUnits(Collection $items, string $field, int $limit): array
    {
        return $items->filter(fn (EntregaBodegaItem $item) => filled($item->{$field}))
            ->groupBy($field)
            ->map(fn (Collection $group) => (int) $group->sum('cantidad'))
            ->sortDesc()
            ->take($limit)
            ->all();
    }

    private function peopleBreakdown(Collection $rows): array
    {
        return $rows->groupBy('nombre')->map(function (Collection $group, string $name) {
            return [
                'nombre' => $name,
                'entregas' => $group->count(),
                'unidades' => (int) $group->sum('unidades_total'),
                'centro' => (string) $group->pluck('centro')->filter()->countBy()->sortDesc()->keys()->first(),
            ];
        })->sortByDesc('unidades')->take(10)->values()->all();
    }

    private function centerPeopleBreakdown(Collection $rows): array
    {
        return $rows->groupBy(fn (EntregaBodega $entrega) => ($entrega->centro ?: 'Sin centro').'|'.($entrega->nombre ?: 'Sin identificar'))
            ->map(function (Collection $group, string $key) {
                [$centro, $nombre] = array_pad(explode('|', $key, 2), 2, '');

                return [
                    'centro' => $centro,
                    'nombre' => $nombre,
                    'entregas' => $group->count(),
                    'unidades' => (int) $group->sum('unidades_total'),
                ];
            })->sortByDesc('unidades')->take(10)->values()->all();
    }

    private function monthlyBreakdown(Collection $rows): array
    {
        return $rows->filter(fn (EntregaBodega $entrega) => $entrega->fecha_pedido)
            ->groupBy(fn (EntregaBodega $entrega) => $entrega->fecha_pedido->format('Y-m'))
            ->sortKeys()
            ->map(fn (Collection $monthRows, string $month) => [
                'label' => $month,
                'entregas' => $monthRows->count(),
                'unidades' => (int) $monthRows->sum('unidades_total'),
            ])->values()->all();
    }

    private function dailyBreakdown(Collection $rows): array
    {
        return $rows->filter(fn (EntregaBodega $entrega) => $entrega->fecha_pedido)
            ->groupBy(fn (EntregaBodega $entrega) => $entrega->fecha_pedido->toDateString())
            ->sortKeys()
            ->map(fn (Collection $dayRows, string $day) => [
                'label' => $day,
                'entregas' => $dayRows->count(),
                'unidades' => (int) $dayRows->sum('unidades_total'),
            ])->values()->all();
    }
}
