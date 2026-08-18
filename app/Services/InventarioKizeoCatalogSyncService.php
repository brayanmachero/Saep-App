<?php

namespace App\Services;

use App\Models\InventarioKizeoCatalogItem;
use App\Models\InventarioVariante;
use Illuminate\Support\Str;

/**
 * Publica el catálogo maestro de SAEP en la lista avanzada de Kizeo.
 *
 * Esta integración es estrictamente unidireccional: Kizeo recibe la
 * información operativa de los productos y sus tallas, pero nunca modifica
 * precio, stock, mínimos ni ningún dato del catálogo de SAEP.
 */
class InventarioKizeoCatalogSyncService
{
    public function __construct(private readonly KizeoService $kizeo)
    {
    }

    /**
     * Calcula las acciones necesarias sin escribir en Kizeo ni en SAEP.
     *
     * @return array{listId:string,total:int,created:int,updated:int,unchanged:int,errors:array<int, string>,orphans:array<int, string>,deferred:int,dryRun:bool}
     */
    public function preview(): array
    {
        return $this->synchronize(true);
    }

    /**
     * Crea y actualiza los ítems que SAEP administra en Kizeo.
     * Los ítems remotos que no provengan de SAEP se informan como huérfanos;
     * jamás se eliminan automáticamente.
     *
     * @return array{listId:string,total:int,created:int,updated:int,unchanged:int,errors:array<int, string>,orphans:array<int, string>,deferred:int,dryRun:bool}
     */
    public function synchronize(bool $dryRun = false, int $limit = 80): array
    {
        $listId = trim((string) config('services.kizeo.inventory_catalog_list_id'));
        if ($listId === '') {
            throw new \LogicException('Falta configurar KIZEO_INVENTORY_CATALOG_LIST_ID para publicar el catálogo.');
        }

        $propertyIds = $this->propertyIds($this->kizeo->getListDefinition($listId));
        $this->requireProperties($propertyIds);

        $remoteItems = collect($this->kizeo->getListItems($listId, true))->values();
        $remoteById = $remoteItems->keyBy(fn (array $item) => (string) ($item['id'] ?? ''));
        $remoteByIdentity = $remoteItems
            ->filter(fn (array $item) => filled($item['id'] ?? null))
            ->groupBy(fn (array $item) => $this->itemIdentity($item['label'] ?? ''));
        $claimedRemoteIds = [];

        $mappings = InventarioKizeoCatalogItem::query()
            ->where('kizeo_list_id', $listId)
            ->get()
            ->keyBy('variante_id');
        $variants = InventarioVariante::query()
            ->with('producto')
            ->where('activo', true)
            ->whereHas('producto', fn ($query) => $query->where('activo', true))
            ->orderBy('producto_id')
            ->orderBy('talla')
            ->get();

        $summary = [
            'listId' => $listId,
            'total' => $variants->count(),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'errors' => [],
            'orphans' => [],
            'deferred' => 0,
            'dryRun' => $dryRun,
        ];
        $writes = 0;

        foreach ($variants as $variant) {
            if ($this->hasEmbeddedSize($variant)) {
                $summary['errors'][] = trim($variant->producto->nombre.' · '.$variant->talla).': el nombre del producto contiene una talla (T-...). Corrige ese producto en SAEP antes de publicarlo en Kizeo.';

                continue;
            }
            $payload = $this->payloadFor($variant, $propertyIds);
            /** @var InventarioKizeoCatalogItem|null $mapping */
            $mapping = $mappings->get($variant->id);
            $remoteItem = $mapping?->kizeo_item_id ? $remoteById->get($mapping->kizeo_item_id) : null;

            if (! $remoteItem) {
                $candidates = $remoteByIdentity->get($this->itemIdentity($payload['label']), collect())
                    ->filter(fn (array $item) => ! in_array((string) $item['id'], $claimedRemoteIds, true))
                    ->values();
                if ($candidates->count() === 1) {
                    $remoteItem = $candidates->first();
                }
            }

            if ($remoteItem) {
                $remoteId = (string) $remoteItem['id'];
                $claimedRemoteIds[] = $remoteId;
                $changed = ! $this->samePayload($remoteItem, $payload, $propertyIds);

                if ($changed && ! $dryRun && $writes >= max(1, $limit)) {
                    $summary['deferred']++;
                    continue;
                }

                try {
                    if ($changed && ! $dryRun) {
                        $this->kizeo->updateListItem($listId, $remoteId, $payload);
                        $writes++;
                    }
                    if (! $dryRun) {
                        $this->storeMapping($variant, $listId, $remoteId, $payload);
                    }
                    $summary[$changed ? 'updated' : 'unchanged']++;
                } catch (\Throwable $exception) {
                    $summary['errors'][] = $this->errorFor($variant, $exception);
                    if (! $dryRun) {
                        $this->storeMappingError($variant, $listId, $mapping?->kizeo_item_id, $exception);
                    }
                }

                continue;
            }

            if (! $dryRun && $writes >= max(1, $limit)) {
                $summary['deferred']++;
                continue;
            }

            try {
                if ($dryRun) {
                    $summary['created']++;
                    continue;
                }

                $response = $this->kizeo->createListItem($listId, $payload);
                $remoteId = $this->responseItemId($response);
                if ($remoteId === '') {
                    throw new \RuntimeException('Kizeo confirmó la creación, pero no devolvió el identificador del ítem.');
                }
                $this->storeMapping($variant, $listId, $remoteId, $payload);
                $claimedRemoteIds[] = $remoteId;
                $writes++;
                $summary['created']++;
            } catch (\Throwable $exception) {
                $summary['errors'][] = $this->errorFor($variant, $exception);
                if (! $dryRun) {
                    $this->storeMappingError($variant, $listId, $mapping?->kizeo_item_id, $exception);
                }
            }
        }

        $summary['orphans'] = $remoteItems
            ->filter(fn (array $item) => ! in_array((string) ($item['id'] ?? ''), $claimedRemoteIds, true))
            ->pluck('label')
            ->filter()
            ->values()
            ->all();

        return $summary;
    }

    /** @return array<string, string> */
    private function propertyIds(array $definition): array
    {
        return collect($definition['properties_definition'] ?? [])
            ->mapWithKeys(function (array $property) {
                $name = $this->comparisonKey($property['display_name'] ?? '');

                return $name === '' || ! filled($property['id'] ?? null)
                    ? []
                    : [$name => (string) $property['id']];
            })
            ->all();
    }

    /** @param array<string, string> $propertyIds */
    private function requireProperties(array $propertyIds): void
    {
        $missing = collect(['tipo', 'categoria', 'sub categoria', 'formato'])
            ->reject(fn (string $name) => isset($propertyIds[$name]))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new \LogicException('La lista avanzada de Kizeo no tiene las columnas requeridas: '.$missing->implode(', ').'.');
        }
    }

    /** @param array<string, string> $propertyIds */
    private function payloadFor(InventarioVariante $variant, array $propertyIds): array
    {
        $product = $variant->producto;
        $properties = [
            $propertyIds['tipo'] => trim((string) $product->tipo),
            $propertyIds['categoria'] => trim((string) $product->categoria),
            $propertyIds['sub categoria'] => trim((string) ($product->subcategoria ?: $product->nombre)),
            $propertyIds['formato'] => trim((string) ($product->unidad_medida ?: 'Unidad')),
        ];

        return [
            'label' => trim(preg_replace('/\\s+/', ' ', $product->nombre).' T-'.$this->kizeoSize($variant->talla)),
            'properties' => $properties,
        ];
    }

    private function kizeoSize(?string $size): string
    {
        $value = trim((string) $size);

        return in_array($this->comparisonKey($value), ['', 'na', 'n a', 'estandar', 'sin talla', 'unica', 'unitalla'], true)
            ? 'NA'
            : Str::upper($value);
    }

    private function hasEmbeddedSize(InventarioVariante $variant): bool
    {
        return (bool) preg_match('/\\s+T-[^\\s]+$/iu', trim((string) $variant->producto->nombre));
    }

    private function itemIdentity(?string $label): string
    {
        return $this->comparisonKey($label);
    }

    /** @param array<string, string> $propertyIds */
    private function samePayload(array $remoteItem, array $payload, array $propertyIds): bool
    {
        if ($this->itemIdentity($remoteItem['label'] ?? '') !== $this->itemIdentity($payload['label'])) {
            return false;
        }

        $remoteProperties = $remoteItem['properties'] ?? [];
        foreach ($payload['properties'] as $propertyId => $value) {
            if ($this->comparisonKey($remoteProperties[$propertyId] ?? '') !== $this->comparisonKey($value)) {
                return false;
            }
        }

        return true;
    }

    private function storeMapping(InventarioVariante $variant, string $listId, string $itemId, array $payload): void
    {
        InventarioKizeoCatalogItem::query()->updateOrCreate([
            'variante_id' => $variant->id,
            'kizeo_list_id' => $listId,
        ], [
            'kizeo_item_id' => $itemId,
            'source_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'sincronizado_en' => now(),
            'ultimo_error' => null,
        ]);
    }

    private function storeMappingError(InventarioVariante $variant, string $listId, ?string $itemId, \Throwable $exception): void
    {
        InventarioKizeoCatalogItem::query()->updateOrCreate([
            'variante_id' => $variant->id,
            'kizeo_list_id' => $listId,
        ], [
            'kizeo_item_id' => $itemId,
            'ultimo_error' => Str::limit($exception->getMessage(), 2000, ''),
        ]);
    }

    private function responseItemId(array $response): string
    {
        foreach ([
            $response['id'] ?? null,
            $response['item']['id'] ?? null,
            $response['items'][0]['id'] ?? null,
            $response['data']['id'] ?? null,
            $response['data']['items'][0]['id'] ?? null,
        ] as $itemId) {
            if (filled($itemId)) {
                return (string) $itemId;
            }
        }

        return '';
    }

    private function errorFor(InventarioVariante $variant, \Throwable $exception): string
    {
        return trim($variant->producto->nombre.' · '.$variant->talla).': '.Str::limit($exception->getMessage(), 260, '…');
    }

    private function comparisonKey(?string $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->toString();
    }
}
