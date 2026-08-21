<?php

namespace App\Services;

use App\Models\InventarioCentroCosto;
use App\Models\TalanaContrato;
use App\Models\TalanaKizeoPersonalItem;
use App\Models\TalanaPersona;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Publica el personal vigente de Talana en la lista avanzada Kizeo 501626.
 *
 * Talana manda y Kizeo solo recibe. La etiqueta del ítem es siempre el RUT.
 * El jefe es el de operaciones del CDD (maestra de centros), no el jefe del contrato.
 */
class TalanaKizeoPersonalSyncService
{
    public function __construct(private readonly KizeoService $kizeo)
    {
    }

    /**
     * @return array{listId:string,total:int,created:int,updated:int,unchanged:int,removed:int,errors:array<int, string>,orphans:array<int, string>,deferred:int,dryRun:bool}
     */
    public function preview(): array
    {
        return $this->synchronize(true);
    }

    /**
     * @return array{listId:string,total:int,created:int,updated:int,unchanged:int,removed:int,errors:array<int, string>,orphans:array<int, string>,deferred:int,dryRun:bool}
     */
    public function synchronize(bool $dryRun = false, int $limit = 250): array
    {
        $listId = trim((string) config('services.kizeo.personal_cdd_list_id'));
        if ($listId === '') {
            throw new \LogicException('Falta configurar KIZEO_PERSONAL_CDD_LIST_ID para publicar el personal vigente.');
        }

        $propertyIds = $this->propertyIds($this->kizeo->getListDefinition($listId));
        $mappedProperties = $this->mappedPropertyIds($propertyIds);
        $this->requireProperties($mappedProperties, $propertyIds);

        $people = $this->vigentePeople();
        $remoteItems = collect($this->kizeo->getListItems($listId, true))->values();
        $remoteById = $remoteItems->keyBy(fn (array $item) => (string) ($item['id'] ?? ''));
        $remoteByIdentity = $remoteItems
            ->filter(fn (array $item) => filled($item['id'] ?? null))
            ->groupBy(fn (array $item) => $this->itemIdentity($item['label'] ?? ''));
        $claimedRemoteIds = [];
        $mappings = TalanaKizeoPersonalItem::query()
            ->where('kizeo_list_id', $listId)
            ->get()
            ->keyBy('rut');
        $jefesPorCentro = $this->jefesPorCentro();

        $summary = [
            'listId' => $listId,
            'total' => $people->count(),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'removed' => 0,
            'errors' => [],
            'orphans' => [],
            'deferred' => 0,
            'dryRun' => $dryRun,
        ];
        $writes = 0;
        $vigenteKeys = [];

        foreach ($people as $row) {
            $vigenteKeys[] = $row['rut_key'];
            $payload = $this->payloadFor($row, $mappedProperties, $jefesPorCentro);
            /** @var TalanaKizeoPersonalItem|null $mapping */
            $mapping = $mappings->get($row['rut_key']);
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
                $changed = ! $this->samePayload($remoteItem, $payload);

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
                        $this->storeMapping($row['rut_key'], $listId, $remoteId, $payload);
                    }
                    $summary[$changed ? 'updated' : 'unchanged']++;
                } catch (\Throwable $exception) {
                    $summary['errors'][] = $this->errorFor($row, $exception);
                    if (! $dryRun) {
                        $this->storeMappingError($row['rut_key'], $listId, $mapping?->kizeo_item_id, $exception);
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
                    $remoteId = $this->findCreatedItemId($listId, $payload);
                }
                if ($remoteId === '') {
                    throw new \RuntimeException('Kizeo confirmó la creación, pero no devolvió el identificador del ítem.');
                }
                $this->storeMapping($row['rut_key'], $listId, $remoteId, $payload);
                $claimedRemoteIds[] = $remoteId;
                $writes++;
                $summary['created']++;
            } catch (\Throwable $exception) {
                $summary['errors'][] = $this->errorFor($row, $exception);
                if (! $dryRun) {
                    $this->storeMappingError($row['rut_key'], $listId, $mapping?->kizeo_item_id, $exception);
                }
            }
        }

        $this->removeInactivePublishedItems($listId, $vigenteKeys, $claimedRemoteIds, $summary, $dryRun, $limit, $writes);

        $summary['orphans'] = $remoteItems
            ->filter(fn (array $item) => ! in_array((string) ($item['id'] ?? ''), $claimedRemoteIds, true))
            ->pluck('label')
            ->filter()
            ->values()
            ->all();

        return $summary;
    }

    /**
     * @return Collection<int, array{persona:TalanaPersona,contrato:TalanaContrato,rut:string,rut_key:string}>
     */
    private function vigentePeople(): Collection
    {
        $today = now('America/Santiago')->toDateString();
        $personas = TalanaPersona::query()
            ->where('activo', true)
            ->whereNotNull('rut')
            ->where('rut', '<>', '')
            ->get()
            ->keyBy('talana_id');

        $contratos = TalanaContrato::query()
            ->where('finiquitado', false)
            ->where(function ($query) use ($today) {
                $query->whereNull('hasta')->orWhereDate('hasta', '>=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('desde')->orWhereDate('desde', '<=', $today);
            })
            ->orderByDesc('desde')
            ->orderByDesc('id')
            ->get()
            ->groupBy('persona_talana_id');

        return $personas
            ->map(function (TalanaPersona $persona) use ($contratos) {
                $contrato = $contratos->get($persona->talana_id)?->first();
                if (! $contrato) {
                    return null;
                }

                $rutKey = $this->rutKey($persona->rut);
                if ($rutKey === '') {
                    return null;
                }

                return [
                    'persona' => $persona,
                    'contrato' => $contrato,
                    'rut' => $this->formatRut($persona->rut),
                    'rut_key' => $rutKey,
                ];
            })
            ->filter()
            ->sortBy('rut')
            ->values();
    }

    /** @return array<string, string> */
    private function jefesPorCentro(): array
    {
        if (! Schema::hasTable('inventario_centros_costo')) {
            return [];
        }

        return InventarioCentroCosto::query()
            ->where('activo', true)
            ->whereNotNull('jefe_operaciones')
            ->where('jefe_operaciones', '<>', '')
            ->get(['nombre_normalizado', 'jefe_operaciones'])
            ->mapWithKeys(fn (InventarioCentroCosto $centro) => [
                (string) $centro->nombre_normalizado => trim((string) $centro->jefe_operaciones),
            ])
            ->all();
    }

    /**
     * @param array<int, string> $vigenteKeys
     * @param array<int, string> $claimedRemoteIds
     * @param array{removed:int,errors:array<int,string>,deferred:int} $summary
     */
    private function removeInactivePublishedItems(
        string $listId,
        array $vigenteKeys,
        array &$claimedRemoteIds,
        array &$summary,
        bool $dryRun,
        int $limit,
        int &$writes
    ): void {
        $inactiveMappings = TalanaKizeoPersonalItem::query()
            ->where('kizeo_list_id', $listId)
            ->when($vigenteKeys !== [], fn ($query) => $query->whereNotIn('rut', $vigenteKeys))
            ->get();

        foreach ($inactiveMappings as $mapping) {
            $remoteId = trim((string) $mapping->kizeo_item_id);
            $label = $this->formatRut($mapping->rut);

            if (! $dryRun && $writes >= max(1, $limit)) {
                $summary['deferred']++;
                continue;
            }

            try {
                if (! $dryRun) {
                    if ($remoteId !== '') {
                        try {
                            $this->kizeo->deleteListItem($listId, $remoteId);
                        } catch (\RuntimeException $exception) {
                            if (! str_contains($exception->getMessage(), 'Kizeo API v4 error [404]')) {
                                throw $exception;
                            }
                        }
                    }
                    $mapping->delete();
                    $writes++;
                }
                if ($remoteId !== '') {
                    $claimedRemoteIds[] = $remoteId;
                }
                $summary['removed']++;
            } catch (\Throwable $exception) {
                $summary['errors'][] = trim($label.': '.Str::limit($exception->getMessage(), 260, '…'));
            }
        }
    }

    /** @return array<string, string> */
    private function propertyIds(array $definition): array
    {
        return collect($definition['properties_definition'] ?? [])
            ->mapWithKeys(function ($property) {
                if (! is_array($property)) {
                    return [];
                }
                $name = $this->comparisonKey($property['display_name'] ?? '');

                return $name === '' || ! filled($property['id'] ?? null)
                    ? []
                    : [$name => (string) $property['id']];
            })
            ->all();
    }

    /**
     * @param array<string, string> $propertyIds
     * @return array<string, string>
     */
    private function mappedPropertyIds(array $propertyIds): array
    {
        $aliases = [
            'cdd' => ['cd', 'cdd', 'centro', 'centro de costo', 'centro de costos', 'centro de distribucion', 'cd d'],
            'nombres' => ['nombres', 'nombre', 'primer nombre', 'nombres trabajador'],
            'apellido' => ['apellido', 'apellidos', 'apellido paterno'],
            'nombre_completo' => ['nombre completo', 'nombrecompleto'],
            'email' => ['email', 'correo', 'mail', 'e mail', 'correo electronico'],
            'cargo' => ['cargo', 'puesto', 'cargo trabajador'],
            'jefe' => ['jefe', 'jefe de operaciones', 'jefe operaciones', 'jefe cdd', 'jefe vd', 'jefatura'],
        ];

        $mapped = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $name) {
                if (isset($propertyIds[$name])) {
                    $mapped[$field] = $propertyIds[$name];
                    break;
                }
            }
        }

        return $mapped;
    }

    /**
     * @param array<string, string> $mapped
     * @param array<string, string> $propertyIds
     */
    private function requireProperties(array $mapped, array $propertyIds): void
    {
        $missing = collect(['cdd', 'nombres', 'apellido', 'cargo', 'jefe'])
            ->reject(fn (string $field) => isset($mapped[$field]))
            ->values();

        if ($missing->isEmpty()) {
            return;
        }

        $available = collect(array_keys($propertyIds))->implode(', ');
        throw new \LogicException(
            'La lista avanzada de Kizeo no tiene las columnas requeridas ('. $missing->implode(', ').'). Columnas actuales: '.($available !== '' ? $available : 'ninguna').'.'
        );
    }

    /**
     * @param array{persona:TalanaPersona,contrato:TalanaContrato,rut:string,rut_key:string} $row
     * @param array<string, string> $mapped
     * @param array<string, string> $jefesPorCentro
     */
    private function payloadFor(array $row, array $mapped, array $jefesPorCentro): array
    {
        $persona = $row['persona'];
        $contrato = $row['contrato'];
        $cdd = trim((string) $contrato->centro_costo_nombre);
        $nombres = trim((string) $persona->nombre);
        $apellido = trim((string) $persona->apellido_paterno);
        $nombreCompleto = trim($nombres.' '.$apellido.' '.trim((string) $persona->apellido_materno));
        $values = [
            'cdd' => $cdd,
            'nombres' => $nombres,
            'apellido' => $apellido,
            'nombre_completo' => preg_replace('/\s+/', ' ', $nombreCompleto) ?: $nombres,
            'email' => trim((string) ($persona->email ?: $contrato->persona_email)),
            'cargo' => trim((string) $contrato->cargo_nombre),
            'jefe' => $jefesPorCentro[$this->comparisonKey($cdd)] ?? '',
        ];

        $properties = [];
        foreach ($mapped as $field => $propertyId) {
            $properties[$propertyId] = $values[$field] ?? '';
        }

        return [
            'label' => $row['rut'],
            'properties' => $properties,
        ];
    }

    private function samePayload(array $remoteItem, array $payload): bool
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

    private function storeMapping(string $rutKey, string $listId, string $itemId, array $payload): void
    {
        TalanaKizeoPersonalItem::query()->updateOrCreate([
            'rut' => $rutKey,
            'kizeo_list_id' => $listId,
        ], [
            'kizeo_item_id' => $itemId,
            'source_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'sincronizado_en' => now(),
            'ultimo_error' => null,
        ]);
    }

    private function storeMappingError(string $rutKey, string $listId, ?string $itemId, \Throwable $exception): void
    {
        TalanaKizeoPersonalItem::query()->updateOrCreate([
            'rut' => $rutKey,
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

    private function findCreatedItemId(string $listId, array $payload): string
    {
        $matches = collect($this->kizeo->getListItems($listId, true))
            ->filter(fn (array $item) => $this->itemIdentity($item['label'] ?? '') === $this->itemIdentity($payload['label'] ?? ''))
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        return $matches->count() === 1 ? (string) $matches->first() : '';
    }

    /** @param array{persona:TalanaPersona,rut:string} $row */
    private function errorFor(array $row, \Throwable $exception): string
    {
        return trim($row['rut'].' · '.$row['persona']->nombre).': '.Str::limit($exception->getMessage(), 260, '…');
    }

    private function formatRut(?string $rut): string
    {
        $clean = $this->rutKey($rut);
        if (strlen($clean) < 2) {
            return $clean;
        }

        return substr($clean, 0, -1).'-'.substr($clean, -1);
    }

    private function rutKey(?string $rut): string
    {
        return strtoupper((string) preg_replace('/[^0-9kK]/', '', (string) $rut));
    }

    private function itemIdentity(?string $label): string
    {
        return $this->rutKey($label) ?: $this->comparisonKey($label);
    }

    private function comparisonKey(?string $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
}
