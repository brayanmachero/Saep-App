<?php

namespace App\Services;

use App\Models\EntregaBodega;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioEntregaKizeoLinea;
use App\Models\InventarioMovimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Read model for the EPP dashboard.
 *
 * Kizeo remains the source document, while stock, imputations and corrections
 * are confirmed in inventario_movimientos. The dashboard intentionally uses
 * that ledger as its source of truth.
 */
class EntregaBodegaAnalyticsService
{
    /** @var Collection<int, stdClass>|null */
    private ?Collection $unfilteredEntries = null;

    public function hasSyncedData(): bool
    {
        return $this->appliedKizeoMovements()->exists();
    }

    public function getSyncInfo(): ?array
    {
        $summary = EntregaBodega::query()
            ->whereIn('kizeo_form_id', EntregaBodegaSyncService::currentFormIds())
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
        $entries = $this->getFilteredEntries($filters);
        $records = $this->recordsFromEntries($entries);
        $valuedEntries = $entries->filter(fn (stdClass $entry) => $entry->precio_referencia !== null);
        $unvaluedEntries = $entries->reject(fn (stdClass $entry) => $entry->precio_referencia !== null);
        $valuedNetUnits = $this->absoluteSum($valuedEntries, 'netas');

        return [
            'total' => $entries->pluck('aplicacion_id')->unique()->count(),
            'entregadas' => $this->sum($entries, 'entregadas'),
            'devueltas' => $this->sum($entries, 'devueltas'),
            'netas' => $this->sum($entries, 'netas'),
            'lineas' => $entries->count(),
            'valor_entregado' => $this->sum($entries, 'valor_entregado'),
            'valor_devuelto' => $this->sum($entries, 'valor_devuelto'),
            'valor_neto' => $this->sum($entries, 'valor_neto'),
            'unidades_valorizadas' => $valuedNetUnits,
            'unidades_sin_precio' => $this->absoluteSum($unvaluedEntries, 'netas'),
            'precio_referencia_promedio' => $valuedNetUnits > 0
                ? round(abs($this->sum($valuedEntries, 'valor_neto')) / $valuedNetUnits, 2)
                : null,
            'personas' => $entries->pluck('persona')->filter()->unique()->count(),
            'centros_activos' => $entries->pluck('centro')
                ->reject(fn (string $center) => $center === 'Sin centro imputado')
                ->unique()
                ->count(),
            'by_day' => $this->dailyBreakdown($entries),
            'centros' => $this->centerBreakdown($entries),
            'articulos' => $this->articleBreakdown($entries, 'articulo'),
            'tallas' => $this->articleBreakdown($entries, 'talla'),
            'articulos_distribucion' => $this->positiveNetDistribution($entries, 'articulo'),
            'tallas_distribucion' => $this->positiveNetDistribution($entries, 'talla'),
            'personas_top' => $this->peopleBreakdown($entries),
            'relaciones' => $this->centerPeopleBreakdown($entries),
            'recent' => $records->take(14)->values(),
            'filter_options' => $this->getFilterOptions(),
        ];
    }

    /**
     * One visible operation per Kizeo application, operational date and
     * assigned cost centre. Corrections remain visible on the date when they
     * affected stock, while retaining the original Kizeo document.
     *
     * @return Collection<int, stdClass>
     */
    public function getFilteredRecords(array $filters = []): Collection
    {
        return $this->recordsFromEntries($this->getFilteredEntries($filters));
    }

    public function getFilterOptions(): array
    {
        $entries = $this->allEntries();

        return [
            'centros' => $entries->pluck('centro')->filter()->unique()->sort()->values()->all(),
            'trabajadores' => $entries->pluck('persona')->filter()->unique()->sort()->values()->all(),
            'articulos' => $entries->pluck('articulo')->filter()->unique()->sort()->values()->all(),
            'tallas' => $entries->pluck('talla')->filter()->unique()->sort()->values()->all(),
        ];
    }

    /** @return Collection<int, stdClass> */
    private function getFilteredEntries(array $filters): Collection
    {
        $query = $this->appliedKizeoMovements()->with([
            'centroCosto',
            'producto',
            'variante',
            'entregaKizeoLinea.aplicacion.entrega',
            'entregaKizeoAplicacion.entrega',
        ]);
        $this->applyFilters($query, $filters);

        return $query->orderByDesc('ocurrido_en')->orderByDesc('id')->get()
            ->map(fn (InventarioMovimiento $movement) => $this->entryFromMovement($movement))
            ->filter()
            ->values();
    }

    /** @return Collection<int, stdClass> */
    private function allEntries(): Collection
    {
        return $this->unfilteredEntries ??= $this->getFilteredEntries([]);
    }

    private function appliedKizeoMovements(): Builder
    {
        $formIds = EntregaBodegaSyncService::currentFormIds();
        $activeApplication = static function (Builder $query) use ($formIds): void {
            $query->whereIn('estado', ['APLICADA', 'CORREGIDA'])
                ->whereHas('entrega', fn (Builder $delivery) => $delivery->whereIn('kizeo_form_id', $formIds));
        };

        return InventarioMovimiento::query()
            ->whereIn('origen', [
                'KIZEO_EPP',
                'KIZEO_EPP_DEVOLUCION',
                'CORRECCION_KIZEO_EPP',
                'REVERSO_KIZEO_EPP',
            ])
            ->where(function (Builder $query) use ($activeApplication): void {
                $query->where(function (Builder $lineReference) use ($activeApplication): void {
                    $lineReference
                        ->where('referencia_tipo', InventarioEntregaKizeoLinea::class)
                        ->whereHas('entregaKizeoLinea.aplicacion', $activeApplication);
                })->orWhere(function (Builder $applicationReference) use ($activeApplication): void {
                    $applicationReference
                        ->where('referencia_tipo', InventarioEntregaKizeoAplicacion::class)
                        ->whereHas('entregaKizeoAplicacion', $activeApplication);
                });
            });
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['centro'])) {
            $query->where('centro_costo', $filters['centro']);
        }
        if (! empty($filters['trabajador'])) {
            $query->where('destinatario_nombre', $filters['trabajador']);
        }
        if (! empty($filters['articulo'])) {
            $query->whereHas('producto', fn (Builder $product) => $product->where('nombre', $filters['articulo']));
        }
        if (! empty($filters['talla'])) {
            $query->whereHas('variante', fn (Builder $variant) => $variant->where('talla', $filters['talla']));
        }
        if (! empty($filters['fecha_desde'])) {
            $query->whereDate('ocurrido_en', '>=', $filters['fecha_desde']);
        }
        if (! empty($filters['fecha_hasta'])) {
            $query->whereDate('ocurrido_en', '<=', $filters['fecha_hasta']);
        }
    }

    private function entryFromMovement(InventarioMovimiento $movement): ?stdClass
    {
        $application = match ($movement->referencia_tipo) {
            InventarioEntregaKizeoLinea::class => $movement->entregaKizeoLinea?->aplicacion,
            InventarioEntregaKizeoAplicacion::class => $movement->entregaKizeoAplicacion,
            default => null,
        };
        $delivery = $application?->entrega;
        if (! $application || ! $delivery) {
            return null;
        }

        $isReturn = $delivery->flujo_inventario === 'ENTRADA';
        $quantity = round((float) $movement->cantidad, 3);
        $delivered = $isReturn ? 0.0 : -$quantity;
        $returned = $isReturn ? $quantity : 0.0;
        $net = $delivered - $returned;
        $price = $movement->costo_unitario !== null && (float) $movement->costo_unitario > 0
            ? round((float) $movement->costo_unitario, 2)
            : (($movement->variante && (float) $movement->variante->costo_referencia > 0)
                ? round((float) $movement->variante->costo_referencia, 2)
                : null);

        $entry = new stdClass;
        $entry->movimiento_id = $movement->id;
        $entry->aplicacion_id = $application->id;
        $entry->entrega = $delivery;
        $entry->fecha = $movement->ocurrido_en?->toDateString() ?: $delivery->fecha_pedido?->toDateString();
        $entry->orden = $movement->id;
        $entry->tipo = $isReturn ? 'Devolución' : 'Entrega';
        $entry->persona = $movement->destinatario_nombre ?: $delivery->nombre ?: 'Sin identificar';
        $entry->rut = $movement->destinatario_rut ?: $delivery->rut;
        $entry->centro = $movement->centroCosto?->nombre ?: ($movement->centro_costo ?: 'Sin centro imputado');
        $entry->centro_id = $movement->centro_costo_id;
        $entry->articulo = $movement->producto?->nombre ?: 'Artículo sin catálogo';
        $entry->talla = $movement->variante?->talla ?: 'Sin talla';
        $entry->entregadas = round($delivered, 3);
        $entry->devueltas = round($returned, 3);
        $entry->netas = round($net, 3);
        $entry->precio_referencia = $price;
        $entry->origen_precio = $price === null
            ? null
            : ($movement->costo_unitario !== null ? 'Costo registrado en movimiento' : 'Precio vigente de catálogo');
        $entry->valor_entregado = $price === null ? 0.0 : round($delivered * $price, 2);
        $entry->valor_devuelto = $price === null ? 0.0 : round($returned * $price, 2);
        $entry->valor_neto = $price === null ? 0.0 : round($net * $price, 2);
        $entry->tiene_precio = $price !== null;
        $entry->origen = $movement->origen;
        $entry->registrado_por = $movement->registrado_por_nombre ?: 'Kizeo automático';

        return $entry;
    }

    /** @param Collection<int, stdClass> $entries @return Collection<int, stdClass> */
    private function recordsFromEntries(Collection $entries): Collection
    {
        return $entries
            ->groupBy(fn (stdClass $entry) => implode('|', [$entry->aplicacion_id, $entry->fecha, $entry->centro_id ?: $entry->centro]))
            ->map(function (Collection $group): stdClass {
                /** @var stdClass $first */
                $first = $group->first();
                $items = $group->groupBy(fn (stdClass $entry) => $entry->articulo.'|'.$entry->talla)
                    ->map(function (Collection $itemGroup): stdClass {
                        /** @var stdClass $item */
                        $item = $itemGroup->first();
                        $summary = new stdClass;
                        $summary->articulo = $item->articulo;
                        $summary->talla = $item->talla;
                        $summary->entregadas = $this->sum($itemGroup, 'entregadas');
                        $summary->devueltas = $this->sum($itemGroup, 'devueltas');
                        $summary->netas = $this->sum($itemGroup, 'netas');
                        $summary->precio_referencia = $item->precio_referencia;
                        $summary->origen_precio = $item->origen_precio;
                        $summary->valor_neto = $this->sum($itemGroup, 'valor_neto');
                        $summary->tiene_precio = $itemGroup->contains(fn (stdClass $entry) => $entry->tiene_precio);

                        return $summary;
                    })->values();

                $record = new stdClass;
                $record->aplicacion_id = $first->aplicacion_id;
                $record->entrega = $first->entrega;
                $record->fecha = $first->fecha;
                $record->orden = $group->max('orden');
                $record->tipo = $first->tipo;
                $record->persona = $first->persona;
                $record->rut = $first->rut;
                $record->centro = $first->centro;
                $record->registrado_por = $first->registrado_por;
                $record->entregadas = $this->sum($group, 'entregadas');
                $record->devueltas = $this->sum($group, 'devueltas');
                $record->netas = $this->sum($group, 'netas');
                $record->valor_neto = $this->sum($group, 'valor_neto');
                $record->unidades_valorizadas = $this->absoluteSum($group->filter(fn (stdClass $entry) => $entry->tiene_precio), 'netas');
                $record->unidades_sin_precio = $this->absoluteSum($group->reject(fn (stdClass $entry) => $entry->tiene_precio), 'netas');
                $record->items = $items;

                return $record;
            })
            ->sortByDesc(fn (stdClass $record) => ($record->fecha ?: '').'|'.str_pad((string) $record->orden, 12, '0', STR_PAD_LEFT))
            ->values();
    }

    /** @param Collection<int, stdClass> $entries */
    private function dailyBreakdown(Collection $entries): array
    {
        return $entries->filter(fn (stdClass $entry) => filled($entry->fecha))
            ->groupBy(fn (stdClass $entry) => $entry->fecha)
            ->sortKeys()
            ->map(fn (Collection $group, string $date) => [
                'label' => $date,
                'documentos' => $group->pluck('aplicacion_id')->unique()->count(),
                'entregadas' => $this->sum($group, 'entregadas'),
                'devueltas' => $this->sum($group, 'devueltas'),
                'netas' => $this->sum($group, 'netas'),
                'valor_neto' => $this->sum($group, 'valor_neto'),
            ])->values()->all();
    }

    /** @param Collection<int, stdClass> $entries */
    private function centerBreakdown(Collection $entries): array
    {
        return $entries->groupBy('centro')->map(function (Collection $group, string $center): array {
            return [
                'centro' => $center,
                'comprobantes' => $group->pluck('aplicacion_id')->unique()->count(),
                'entregadas' => $this->sum($group, 'entregadas'),
                'devueltas' => $this->sum($group, 'devueltas'),
                'netas' => $this->sum($group, 'netas'),
                'valor_neto' => $this->sum($group, 'valor_neto'),
            ];
        })->sortByDesc('netas')->take(10)->values()->all();
    }

    /** @param Collection<int, stdClass> $entries */
    private function articleBreakdown(Collection $entries, string $field): array
    {
        return $entries->groupBy($field)->map(function (Collection $group, string $label) use ($field): array {
            return [
                'label' => $label ?: ($field === 'talla' ? 'Sin talla' : 'Artículo sin catálogo'),
                'entregadas' => $this->sum($group, 'entregadas'),
                'devueltas' => $this->sum($group, 'devueltas'),
                'netas' => $this->sum($group, 'netas'),
                'valor_neto' => $this->sum($group, 'valor_neto'),
            ];
        })->sortByDesc('netas')->take(8)->values()->all();
    }

    /** @param Collection<int, stdClass> $entries */
    private function positiveNetDistribution(Collection $entries, string $field): array
    {
        return $entries->groupBy($field)
            ->map(fn (Collection $group, string $label) => [
                'label' => $label ?: ($field === 'talla' ? 'Sin talla' : 'Artículo sin catálogo'),
                'netas' => $this->sum($group, 'netas'),
            ])
            ->filter(fn (array $row) => $row['netas'] > 0)
            ->sortByDesc('netas')
            ->take(8)
            ->values()
            ->all();
    }

    /** @param Collection<int, stdClass> $entries */
    private function peopleBreakdown(Collection $entries): array
    {
        return $entries->groupBy('persona')->map(function (Collection $group, string $person): array {
            return [
                'nombre' => $person,
                'centro' => (string) $group->pluck('centro')->countBy()->sortDesc()->keys()->first(),
                'entregadas' => $this->sum($group, 'entregadas'),
                'devueltas' => $this->sum($group, 'devueltas'),
                'netas' => $this->sum($group, 'netas'),
                'valor_neto' => $this->sum($group, 'valor_neto'),
            ];
        })->sortByDesc('netas')->take(10)->values()->all();
    }

    /** @param Collection<int, stdClass> $entries */
    private function centerPeopleBreakdown(Collection $entries): array
    {
        return $entries->groupBy(fn (stdClass $entry) => $entry->centro.'|'.$entry->persona)
            ->map(function (Collection $group, string $key): array {
                [$center, $person] = array_pad(explode('|', $key, 2), 2, '');

                return [
                    'centro' => $center,
                    'nombre' => $person,
                    'entregadas' => $this->sum($group, 'entregadas'),
                    'devueltas' => $this->sum($group, 'devueltas'),
                    'netas' => $this->sum($group, 'netas'),
                    'valor_neto' => $this->sum($group, 'valor_neto'),
                ];
            })->sortByDesc('netas')->take(12)->values()->all();
    }

    /** @param Collection<int, stdClass> $entries */
    private function sum(Collection $entries, string $field): float
    {
        return round((float) $entries->sum(fn (stdClass $entry) => (float) $entry->{$field}), 3);
    }

    /** @param Collection<int, stdClass> $entries */
    private function absoluteSum(Collection $entries, string $field): float
    {
        return round((float) $entries->sum(fn (stdClass $entry) => abs((float) $entry->{$field})), 3);
    }
}
