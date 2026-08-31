<?php

namespace App\Services;

use App\Models\InventarioCentroCosto;
use App\Models\TalanaContrato;
use App\Models\TalanaKizeoPersonalItem;
use App\Models\TalanaPersona;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Mantiene la lista avanzada Kizeo 501626 como una proyección del personal
 * vigente de Talana. El contrato vigente define la pertenencia; la ficha de
 * persona sólo complementa nombres y correo cuando está disponible.
 */
class TalanaKizeoPersonalSyncService
{
    public function __construct(private readonly KizeoService $kizeo)
    {
    }

    /** @return array<string, mixed> */
    public function preview(bool $reconcile = false): array
    {
        return $this->synchronize(true, 250, $reconcile);
    }

    /**
     * Sincroniza por RUT: cada RUT vigente tiene a lo más un ítem canónico en
     * Kizeo. En modo reconcile también retira duplicados y RUT obsoletos que
     * no estén vinculados en SAEP.
     *
     * @return array<string, mixed>
     */
    public function synchronize(bool $dryRun = false, int $limit = 250, bool $reconcile = false): array
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
        $remoteByRut = $this->remoteByRut($remoteItems);
        $mappings = TalanaKizeoPersonalItem::query()
            ->where('kizeo_list_id', $listId)
            ->get()
            ->keyBy('rut');
        $jefesPorCentro = $this->jefesPorCentro();
        $removalSafety = $this->removalSafety($people);

        $summary = [
            'listId' => $listId,
            'total' => $people->count(),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'removed' => 0,
            'duplicates' => 0,
            'stale' => 0,
            'conflicts' => 0,
            'deferred' => 0,
            'errors' => [],
            'orphans' => [],
            'reconcile' => $reconcile,
            'removalSafety' => $removalSafety,
            'dryRun' => $dryRun,
        ];

        $maxWrites = max(1, $limit);
        $writes = 0;
        $vigenteKeys = $people->pluck('rut_key')->all();
        $canonicalRemoteIds = [];
        $pendingUpdates = [];
        $pendingCreates = [];

        foreach ($people as $row) {
            $rutKey = $row['rut_key'];
            $payload = $this->payloadFor($row, $mappedProperties, $jefesPorCentro);
            /** @var TalanaKizeoPersonalItem|null $mapping */
            $mapping = $mappings->get($rutKey);
            $candidates = $remoteByRut->get($rutKey, collect())->values();

            if ($candidates->isNotEmpty()) {
                $remoteItem = $this->canonicalRemote($candidates, $mapping, $payload);
                $remoteId = (string) $remoteItem['id'];
                $canonicalRemoteIds[$rutKey] = $remoteId;
                $summary['duplicates'] += max(0, $candidates->count() - 1);

                $changed = ! $this->samePayload($remoteItem, $payload);
                if ($changed) {
                    if ($dryRun) {
                        $summary['updated']++;
                        continue;
                    }
                    if ($writes + count($pendingUpdates) + count($pendingCreates) >= $maxWrites) {
                        $summary['deferred']++;
                        continue;
                    }

                    $pendingUpdates[] = [
                        'row' => $row,
                        'payload' => $payload,
                        'mapping' => $mapping,
                        'remote_id' => $remoteId,
                    ];

                    continue;
                }

                if (! $dryRun) {
                    $this->storeMapping($rutKey, $listId, $remoteId, $payload);
                }
                $summary['unchanged']++;

                continue;
            }

            if ($mapping?->proximo_intento_en?->isFuture()) {
                $summary['deferred']++;
                continue;
            }

            if ($dryRun) {
                $summary['created']++;
                continue;
            }

            if ($writes + count($pendingUpdates) + count($pendingCreates) >= $maxWrites) {
                $summary['deferred']++;
                continue;
            }

            $pendingCreates[] = [
                'row' => $row,
                'payload' => $payload,
                'mapping' => $mapping,
            ];
        }

        if (! $dryRun && $pendingUpdates !== []) {
            $this->updateExistingPeople($listId, $pendingUpdates, $writes, $summary);
        }

        if (! $dryRun && $pendingCreates !== []) {
            $acceptedCreates = $this->createMissingPeople($listId, $pendingCreates, $writes, $summary);
            $remoteItems = collect($this->kizeo->getListItems($listId, true))->values();
            $remoteByRut = $this->remoteByRut($remoteItems);

            foreach ($pendingCreates as $pending) {
                $row = $pending['row'];
                $rutKey = $row['rut_key'];
                if (! in_array($rutKey, $acceptedCreates, true)) {
                    continue;
                }
                $candidates = $remoteByRut->get($rutKey, collect())->values();

                if ($candidates->count() === 1) {
                    $remoteId = (string) $candidates->first()['id'];
                    $canonicalRemoteIds[$rutKey] = $remoteId;
                    $this->storeMapping($rutKey, $listId, $remoteId, $pending['payload']);
                    $summary['created']++;
                    continue;
                }

                $summary['conflicts'] += $candidates->count() > 1 ? 1 : 0;
                $exception = new \RuntimeException(
                    $candidates->isEmpty()
                        ? 'Kizeo aceptó el lote, pero el ítem aún no está visible. Se reintentará la lectura antes de crear nuevamente.'
                        : 'Kizeo contiene más de un ítem para este RUT. No se creará otro hasta resolver el duplicado.'
                );
                $summary['errors'][] = $this->errorFor($row, $exception);
                $this->storeMappingError($rutKey, $listId, $pending['mapping']?->kizeo_item_id, $exception, true);
            }
        }

        if ($removalSafety === null) {
            $this->removeInactivePublishedItems($listId, $vigenteKeys, $summary, $dryRun, $maxWrites, $writes);
            if (! $dryRun && $summary['removed'] > 0) {
                $remoteItems = collect($this->kizeo->getListItems($listId, true))->values();
                $remoteByRut = $this->remoteByRut($remoteItems);
            }
        }

        if ($reconcile && $removalSafety === null && $summary['errors'] === [] && $summary['deferred'] === 0) {
            $this->reconcileRemoteItems(
                $listId,
                $remoteByRut,
                $vigenteKeys,
                $canonicalRemoteIds,
                $summary,
                $dryRun,
                $maxWrites,
                $writes,
            );
        }

        $managedIds = array_values($canonicalRemoteIds);
        $summary['orphans'] = $remoteItems
            ->filter(fn (array $item) => ! in_array((string) ($item['id'] ?? ''), $managedIds, true))
            ->pluck('label')
            ->filter()
            ->values()
            ->all();

        return $summary;
    }

    /**
     * El contrato vigente es la fuente de pertenencia. Si la tabla de personas
     * quedó incompleta, conserva el contrato y usa sus datos embebidos.
     *
     * @return Collection<int, array{persona:array<string,string>,contrato:TalanaContrato,rut:string,rut_key:string}>
     */
    private function vigentePeople(): Collection
    {
        $today = now('America/Santiago')->toDateString();
        $personas = TalanaPersona::query()
            ->whereNotNull('rut')
            ->where('rut', '<>', '')
            ->get();
        $personasPorId = $personas->keyBy('talana_id');
        $personasPorRut = $personas
            ->groupBy(fn (TalanaPersona $persona) => $this->rutKey($persona->rut))
            ->map(fn (Collection $group) => $group->sortByDesc('activo')->first());

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
            ->get();

        $rows = [];
        foreach ($contratos as $contrato) {
            $persona = $personasPorId->get($contrato->persona_talana_id);
            $rut = $persona?->rut ?: $contrato->persona_rut;
            $rutKey = $this->rutKey($rut);
            if (! $this->isRutKey($rutKey) || isset($rows[$rutKey])) {
                continue;
            }

            $persona ??= $personasPorRut->get($rutKey);
            $fallback = $this->nameParts($contrato->persona_nombre);
            $nombres = trim((string) ($persona?->nombre ?: $fallback['nombres']));
            $apellidoPaterno = trim((string) ($persona?->apellido_paterno ?: $fallback['apellido_paterno']));
            $apellidoMaterno = trim((string) ($persona?->apellido_materno ?: $fallback['apellido_materno']));

            $rows[$rutKey] = [
                'persona' => [
                    'nombres' => $nombres,
                    'apellido_paterno' => $apellidoPaterno,
                    'apellido_materno' => $apellidoMaterno,
                    'nombre_completo' => $this->fullName($nombres, $apellidoPaterno, $apellidoMaterno, $contrato->persona_nombre),
                    'email' => trim((string) ($persona?->email ?: $contrato->persona_email)),
                    'fecha_nacimiento' => $persona?->fecha_nacimiento ?: $contrato->persona_fecha_nacimiento,
                ],
                'contrato' => $contrato,
                'rut' => $this->formatRut($rut),
                'rut_key' => $rutKey,
            ];
        }

        return collect($rows)->sortBy('rut')->values();
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

    /** @return Collection<string, Collection<int, array<string, mixed>>> */
    private function remoteByRut(Collection $remoteItems): Collection
    {
        return $remoteItems
            ->filter(fn (array $item) => filled($item['id'] ?? null) && $this->itemRutKey($item['label'] ?? null) !== null)
            ->groupBy(fn (array $item) => $this->itemRutKey($item['label'] ?? ''));
    }

    /** @param Collection<int, array<string, mixed>> $candidates */
    private function canonicalRemote(Collection $candidates, ?TalanaKizeoPersonalItem $mapping, array $payload): array
    {
        if ($mapping?->kizeo_item_id) {
            $mapped = $candidates->firstWhere('id', $mapping->kizeo_item_id);
            if (is_array($mapped)) {
                return $mapped;
            }
        }

        $same = $candidates->first(fn (array $item) => $this->samePayload($item, $payload));
        if (is_array($same)) {
            return $same;
        }

        return $candidates
            ->sortBy(fn (array $item) => sprintf('%s|%s', $item['created_at'] ?? '', $item['id'] ?? ''))
            ->first();
    }

    /**
     * @param array<int, array{row:array<string,mixed>,payload:array<string,mixed>,mapping:?TalanaKizeoPersonalItem}> $pendingCreates
     * @param array<string,mixed> $summary
     * @return array<int,string> RUT de filas aceptadas por Kizeo
     */
    private function createMissingPeople(string $listId, array $pendingCreates, int &$writes, array &$summary): array
    {
        $accepted = [];
        foreach (array_chunk($pendingCreates, 500) as $chunk) {
            try {
                $this->kizeo->createListItems($listId, array_column($chunk, 'payload'));
                $writes += count($chunk);
                $accepted = array_merge($accepted, array_column(array_column($chunk, 'row'), 'rut_key'));
            } catch (\Throwable $exception) {
                foreach ($chunk as $pending) {
                    $summary['errors'][] = $this->errorFor($pending['row'], $exception);
                    $this->storeMappingError(
                        $pending['row']['rut_key'],
                        $listId,
                        $pending['mapping']?->kizeo_item_id,
                        $exception,
                    );
                }
            }
        }

        return $accepted;
    }

    /**
     * @param array<int, array{row:array<string,mixed>,payload:array<string,mixed>,mapping:?TalanaKizeoPersonalItem,remote_id:string}> $pendingUpdates
     * @param array<string,mixed> $summary
     */
    private function updateExistingPeople(string $listId, array $pendingUpdates, int &$writes, array &$summary): void
    {
        foreach (array_chunk($pendingUpdates, 500) as $chunk) {
            try {
                $items = array_map(fn (array $pending) => $this->batchUpdateItem($pending['remote_id'], $pending['payload']), $chunk);
                $this->kizeo->updateListItems($listId, $items);
                $writes += count($chunk);

                foreach ($chunk as $pending) {
                    $this->storeMapping(
                        $pending['row']['rut_key'],
                        $listId,
                        $pending['remote_id'],
                        $pending['payload'],
                    );
                    $summary['updated']++;
                }
            } catch (\Throwable $exception) {
                foreach ($chunk as $pending) {
                    $summary['errors'][] = $this->errorFor($pending['row'], $exception);
                    $this->storeMappingError(
                        $pending['row']['rut_key'],
                        $listId,
                        $pending['mapping']?->kizeo_item_id,
                        $exception,
                    );
                }
            }
        }
    }

    /**
     * @param array<int, string> $vigenteKeys
     * @param array<string,mixed> $summary
     */
    private function removeInactivePublishedItems(
        string $listId,
        array $vigenteKeys,
        array &$summary,
        bool $dryRun,
        int $limit,
        int &$writes,
    ): void {
        $inactiveMappings = TalanaKizeoPersonalItem::query()
            ->where('kizeo_list_id', $listId)
            ->when($vigenteKeys !== [], fn ($query) => $query->whereNotIn('rut', $vigenteKeys))
            ->get();

        foreach ($inactiveMappings as $mapping) {
            if (! $dryRun && $writes >= $limit) {
                $summary['deferred']++;
                continue;
            }

            try {
                if (! $dryRun) {
                    if (filled($mapping->kizeo_item_id)) {
                        try {
                            $this->kizeo->deleteListItem($listId, (string) $mapping->kizeo_item_id);
                        } catch (\RuntimeException $exception) {
                            if (! str_contains($exception->getMessage(), 'Kizeo API v4 error [404]')) {
                                throw $exception;
                            }
                        }
                    }
                    $mapping->delete();
                    $writes++;
                }
                $summary['removed']++;
            } catch (\Throwable $exception) {
                $summary['errors'][] = $this->formatRut($mapping->rut).': '.Str::limit($exception->getMessage(), 260, '…');
            }
        }
    }

    /**
     * Elimina únicamente registros con etiqueta RUT: duplicados del mismo RUT
     * o RUT que ya no existe en Talana. Las etiquetas ajenas a ese formato se
     * reportan como huérfanas y nunca se borran automáticamente.
     *
     * @param Collection<string, Collection<int, array<string,mixed>>> $remoteByRut
     * @param array<int,string> $vigenteKeys
     * @param array<string,string> $canonicalRemoteIds
     * @param array<string,mixed> $summary
     */
    private function reconcileRemoteItems(
        string $listId,
        Collection $remoteByRut,
        array $vigenteKeys,
        array $canonicalRemoteIds,
        array &$summary,
        bool $dryRun,
        int $limit,
        int &$writes,
    ): void {
        $vigentes = array_flip($vigenteKeys);
        foreach ($remoteByRut as $rutKey => $items) {
            $canonicalId = $canonicalRemoteIds[$rutKey] ?? null;
            foreach ($items as $item) {
                $remoteId = (string) ($item['id'] ?? '');
                $isStale = ! isset($vigentes[$rutKey]);
                $isDuplicate = ! $isStale && $canonicalId !== null && $remoteId !== $canonicalId;
                if (! $isStale && ! $isDuplicate) {
                    continue;
                }

                if (! $dryRun && $writes >= $limit) {
                    $summary['deferred']++;
                    return;
                }

                try {
                    if (! $dryRun) {
                        $this->kizeo->deleteListItem($listId, $remoteId);
                        $writes++;
                    }
                    if ($isStale) {
                        $summary['stale']++;
                    } else {
                        $summary['removed']++;
                    }
                } catch (\Throwable $exception) {
                    $summary['errors'][] = $this->formatRut($rutKey).': '.Str::limit($exception->getMessage(), 260, '…');
                }
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

    /** @return array<string, string> */
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
            'edad' => ['edad', 'edad trabajador'],
            'antiguedad' => ['antiguedad', 'antiguedad empresa', 'antiguedad en la empresa', 'anos de servicio'],
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

    /** @param array{persona:array<string,string>,contrato:TalanaContrato,rut:string,rut_key:string} $row */
    private function payloadFor(array $row, array $mapped, array $jefesPorCentro): array
    {
        $persona = $row['persona'];
        $contrato = $row['contrato'];
        $cdd = trim((string) $contrato->centro_costo_nombre);
        $values = [
            'cdd' => $cdd,
            'nombres' => $persona['nombres'],
            'apellido' => $persona['apellido_paterno'],
            'nombre_completo' => $persona['nombre_completo'],
            'email' => $persona['email'],
            'cargo' => trim((string) $contrato->cargo_nombre),
            'jefe' => $jefesPorCentro[$this->comparisonKey($cdd)] ?? trim((string) $contrato->jefe_nombre),
            'edad' => $this->age($persona['fecha_nacimiento'] ?? null),
            'antiguedad' => $this->seniority($contrato->fecha_contratacion),
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

    private function age(mixed $birthDate): string
    {
        if (! $birthDate instanceof CarbonInterface || $birthDate->isFuture()) {
            return '';
        }

        return (string) ((int) $birthDate->copy()->startOfDay()->diffInYears(now('America/Santiago')->startOfDay()));
    }

    private function seniority(mixed $hireDate): string
    {
        if (! $hireDate instanceof CarbonInterface || $hireDate->isFuture()) {
            return '';
        }

        $months = (int) $hireDate->copy()->startOfDay()->diffInMonths(now('America/Santiago')->startOfDay());
        $years = intdiv($months, 12);
        $remainingMonths = $months % 12;

        if ($years === 0 && $remainingMonths === 0) {
            return 'Menos de 1 mes';
        }

        $parts = [];
        if ($years > 0) {
            $parts[] = $years.' '.($years === 1 ? 'año' : 'años');
        }
        if ($remainingMonths > 0) {
            $parts[] = $remainingMonths.' '.($remainingMonths === 1 ? 'mes' : 'meses');
        }

        return implode(' y ', $parts);
    }

    private function samePayload(array $remoteItem, array $payload): bool
    {
        if ($this->itemIdentity($remoteItem['label'] ?? '') !== $this->itemIdentity($payload['label'])) {
            return false;
        }

        $remoteProperties = $remoteItem['properties'] ?? [];
        foreach ($payload['properties'] as $propertyId => $value) {
            // Kizeo no acepta vacíos en su PATCH masivo. Si Talana no entrega
            // un valor, se conserva el dato existente en lugar de borrarlo.
            if (! filled($value)) {
                continue;
            }
            if ($this->comparisonKey($remoteProperties[$propertyId] ?? '') !== $this->comparisonKey($value)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{item_id:string,label:string,properties:array<string,string>} */
    private function batchUpdateItem(string $remoteId, array $payload): array
    {
        return [
            'item_id' => $remoteId,
            'label' => (string) $payload['label'],
            'properties' => collect($payload['properties'])
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => (string) $value)
                ->all(),
        ];
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
            'proximo_intento_en' => null,
        ]);
    }

    private function storeMappingError(string $rutKey, string $listId, ?string $itemId, \Throwable $exception, bool $waitForVisibility = false): void
    {
        TalanaKizeoPersonalItem::query()->updateOrCreate([
            'rut' => $rutKey,
            'kizeo_list_id' => $listId,
        ], [
            'kizeo_item_id' => $itemId,
            'ultimo_error' => Str::limit($exception->getMessage(), 2000, ''),
            'proximo_intento_en' => $waitForVisibility ? now()->addMinutes(15) : null,
        ]);
    }

    /** @param array{persona:array<string,string>,rut:string} $row */
    private function errorFor(array $row, \Throwable $exception): string
    {
        return trim($row['rut'].' · '.$row['persona']['nombre_completo']).': '.Str::limit($exception->getMessage(), 260, '…');
    }

    private function removalSafety(Collection $people): ?string
    {
        $minimum = max(0, (int) config('services.kizeo.personal_cdd_minimum_count', 1500));
        if ($minimum > 0 && $people->count() < $minimum) {
            return "Se bloqueó la eliminación: Talana entregó {$people->count()} personas y el mínimo seguro es {$minimum}.";
        }

        $maxAgeMinutes = max(0, (int) config('services.kizeo.personal_cdd_max_source_age_minutes', 480));
        if ($maxAgeMinutes === 0) {
            return null;
        }

        $lastSync = TalanaContrato::query()->max('synced_at');
        if (! $lastSync) {
            return 'Se bloqueó la eliminación: no hay fecha de sincronización de contratos Talana.';
        }

        if (now()->diffInMinutes($lastSync) > $maxAgeMinutes) {
            return "Se bloqueó la eliminación: la fuente Talana tiene más de {$maxAgeMinutes} minutos.";
        }

        return null;
    }

    /** @return array{nombres:string,apellido_paterno:string,apellido_materno:string} */
    private function nameParts(?string $fullName): array
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];
        if (count($parts) < 3) {
            return [
                'nombres' => trim((string) $fullName),
                'apellido_paterno' => '',
                'apellido_materno' => '',
            ];
        }

        return [
            'nombres' => implode(' ', array_slice($parts, 0, -2)),
            'apellido_paterno' => (string) $parts[count($parts) - 2],
            'apellido_materno' => (string) $parts[count($parts) - 1],
        ];
    }

    private function fullName(string $nombres, string $apellidoPaterno, string $apellidoMaterno, ?string $fallback): string
    {
        $name = trim(preg_replace('/\s+/', ' ', trim("{$nombres} {$apellidoPaterno} {$apellidoMaterno}")) ?: '');

        return $name !== '' ? $name : trim((string) $fallback);
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

    private function itemRutKey(?string $label): ?string
    {
        $key = $this->rutKey($label);

        return $this->isRutKey($key) ? $key : null;
    }

    private function isRutKey(string $rutKey): bool
    {
        return (bool) preg_match('/^\d{7,8}[0-9K]$/', $rutKey);
    }

    private function itemIdentity(?string $label): string
    {
        return $this->itemRutKey($label) ?: $this->comparisonKey($label);
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
