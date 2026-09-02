<?php

namespace App\Services;

use App\Models\EntregaBodega;
use App\Models\EntregaBodegaItem;
use App\Models\InventarioVariante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        $valuedItems = $items->filter(fn (EntregaBodegaItem $item) => $item->tiene_precio_referencia);
        $unvaluedItems = $items->reject(fn (EntregaBodegaItem $item) => $item->tiene_precio_referencia);
        $referenceValue = $this->referenceValue($items);
        $valuedUnits = (int) $valuedItems->sum('cantidad');

        return [
            'total' => $rows->count(),
            'unidades' => (int) $rows->sum('unidades_total'),
            'lineas' => (int) $rows->sum('lineas_count'),
            'valor_referencial' => $referenceValue,
            'unidades_valorizadas' => $valuedUnits,
            'unidades_sin_precio' => (int) $unvaluedItems->sum('cantidad'),
            'lineas_sin_precio' => $unvaluedItems->count(),
            'precio_referencia_promedio' => $valuedUnits > 0 ? round($referenceValue / $valuedUnits, 2) : null,
            'personas' => $people->pluck('nombre')->unique()->count(),
            'centros_activos' => $rows->pluck('centro')->filter()->unique()->count(),
            'promedio_unidades' => $rows->isNotEmpty() ? round($rows->avg('unidades_total'), 1) : 0,
            'by_day' => $this->dailyBreakdown($rows),
            'by_month' => $this->monthlyBreakdown($rows),
            'centros' => $this->groupCount($rows, 'centro', 10),
            'articulos' => $this->groupItemUnits($items, 'articulo', 8),
            'articulos_valor' => $this->groupItemReferenceValues($items, 'articulo', 8),
            'tallas' => $this->groupItemUnits($items, 'talla', 8),
            'personas_top' => $this->peopleBreakdown($people),
            'relaciones' => $this->centerPeopleBreakdown($people),
            'recent' => $rows->take(14)->values(),
            'filter_options' => $this->getFilterOptions(),
        ];
    }

    public function getFilteredRecords(array $filters = []): Collection
    {
        $query = $this->currentDeliveries()->with([
            'items',
            'inventarioAplicacion.lineas.variante',
        ]);
        $this->applyFilters($query, $filters);

        $rows = $query->orderByDesc('fecha_pedido')->orderByDesc('id')->get();
        $this->attachReferenceValues($rows);

        return $rows;
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

    private function groupItemReferenceValues(Collection $items, string $field, int $limit): array
    {
        return $items
            ->filter(fn (EntregaBodegaItem $item) => filled($item->{$field}) && $item->tiene_precio_referencia)
            ->groupBy($field)
            ->map(fn (Collection $group) => round($this->referenceValue($group), 2))
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
                'valor_referencial' => round((float) $group->sum('valor_referencial'), 2),
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
                    'valor_referencial' => round((float) $group->sum('valor_referencial'), 2),
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
                'valor_referencial' => round((float) $monthRows->sum('valor_referencial'), 2),
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
                'valor_referencial' => round((float) $dayRows->sum('valor_referencial'), 2),
            ])->values()->all();
    }

    /** Adds a current catalog reference cost while preserving how it was matched. */
    private function attachReferenceValues(Collection $rows): void
    {
        $snapshotVariantIds = $rows
            ->map(fn (EntregaBodega $delivery) => $delivery->inventarioAplicacion?->correccion_snapshot ?? [])
            ->flatten(1)
            ->filter(fn ($line) => is_array($line) && filled($line['variante_id'] ?? null))
            ->pluck('variante_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $snapshotVariants = $snapshotVariantIds->isEmpty()
            ? collect()
            : InventarioVariante::query()->whereKey($snapshotVariantIds)->get()->keyBy('id');
        $catalogueVariants = $this->catalogueVariantLookup();

        foreach ($rows as $delivery) {
            $application = $delivery->inventarioAplicacion;
            $snapshot = collect($application?->correccion_snapshot ?? [])
                ->filter(fn ($line) => is_array($line) && filled($line['linea_fuente'] ?? null) && filled($line['variante_id'] ?? null))
                ->mapWithKeys(fn (array $line) => [(int) $line['linea_fuente'] => $snapshotVariants->get((int) $line['variante_id'])]);

            $lineVariants = $snapshot->isNotEmpty()
                ? $snapshot
                : ($application?->lineas ?? collect())->mapWithKeys(
                    fn ($line) => [(int) $line->linea_fuente => $line->variante],
                );

            $valuedUnits = 0;
            $unvaluedUnits = 0;
            $referenceValue = 0.0;

            foreach ($delivery->items as $item) {
                /** @var InventarioVariante|null $variant */
                $mappedVariant = $lineVariants->get((int) $item->linea);
                $variant = $mappedVariant ?: $catalogueVariants->get($this->catalogueVariantKey($item->articulo, $item->talla));
                $price = $variant && (float) $variant->costo_referencia > 0
                    ? round((float) $variant->costo_referencia, 2)
                    : null;
                $quantity = (int) $item->cantidad;
                $value = $price === null ? null : round($quantity * $price, 2);

                $item->setAttribute('variante_id_referencia', $variant?->id);
                $item->setAttribute('precio_referencia', $price);
                $item->setAttribute('valor_referencial', $value);
                $item->setAttribute('tiene_precio_referencia', $price !== null);
                $item->setAttribute('origen_precio_referencia', $price === null ? null : ($mappedVariant ? 'Vínculo de inventario' : 'Coincidencia exacta de catálogo'));

                if ($value === null) {
                    $unvaluedUnits += $quantity;
                    continue;
                }

                $valuedUnits += $quantity;
                $referenceValue += $value;
            }

            $delivery->setAttribute('unidades_valorizadas', $valuedUnits);
            $delivery->setAttribute('unidades_sin_precio', $unvaluedUnits);
            $delivery->setAttribute('valor_referencial', round($referenceValue, 2));
        }
    }

    private function referenceValue(Collection $items): float
    {
        return round((float) $items->sum(fn (EntregaBodegaItem $item) => (float) ($item->valor_referencial ?? 0)), 2);
    }

    /**
     * Exact product/talla matches allow old dashboard records to be valued
     * without inferring an article from similar wording. Ambiguous labels are
     * intentionally excluded from the lookup.
     */
    private function catalogueVariantLookup(): Collection
    {
        $lookup = [];

        InventarioVariante::query()->with('producto:id,codigo,nombre')->get()->each(function (InventarioVariante $variant) use (&$lookup) {
            foreach ([$variant->producto?->nombre, $variant->producto?->codigo] as $article) {
                $key = $this->catalogueVariantKey($article, $variant->talla);
                if ($key === null) {
                    continue;
                }

                if (! array_key_exists($key, $lookup)) {
                    $lookup[$key] = $variant;
                    continue;
                }

                if ($lookup[$key]?->id !== $variant->id) {
                    $lookup[$key] = null;
                }
            }
        });

        return collect($lookup);
    }

    private function catalogueVariantKey(?string $article, ?string $size): ?string
    {
        $article = Str::lower(preg_replace('/\s+/', ' ', trim((string) $article)) ?: '');
        $size = Str::lower(preg_replace('/\s+/', ' ', trim((string) $size)) ?: '');

        return $article !== '' && $size !== '' ? $article.'|'.$size : null;
    }
}
