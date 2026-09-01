<?php

namespace App\Services;

use App\Models\EntregaBodega;
use App\Models\InventarioKizeoCatalogItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EntregaBodegaSyncService
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $advancedCatalogItemsById = null;

    /** Formulario antiguo de Control de Entrega Bodega. Se conserva para consulta y no descuenta stock. */
    public const LEGACY_FORM_ID = '947762';

    /**
     * Formularios Kizeo que actualmente alimentan la conciliación de Bodega.
     * Cada formulario conserva sus propias claves para no depender de un esquema fijo.
     */
    private const FORMS = [
        '1196386' => [
            'name' => 'Entrega de EPP Masiva - CD',
            'centro' => 'centro_de_costo',
            'nombre' => 'solicitud_de_',
            'fecha' => 'fecha_y_hora_de_despacho',
            'tipo' => 'tipo_de_solicitud',
            'subform' => 'epp',
            'articulo' => 'uniforme_y_epp',
            'talla' => null,
            'flujo' => 'SALIDA',
        ],
        '1195951' => [
            'name' => 'Registro de Entrega Bodega',
            'centro' => 'centro_de_costo1',
            'rut' => 'rut',
            'nombre' => 'nombre',
            'fecha' => 'fecha_del_pedido',
            'tipo' => 'tipo_de_ingreso',
            'subform' => 'epi',
            'articulo' => 'concepto',
            'talla' => null,
            'flujo' => 'SALIDA',
            'entradas' => ['Devolución PT. (entrada de inventario)'],
        ],
    ];

    public function __construct(
        private readonly KizeoService $kizeo,
        private readonly ?InventarioStockService $inventoryStock = null,
    ) {}

    public function supportsForm(string|int $formId): bool
    {
        return self::isCurrentBodegaForm($formId);
    }

    public static function isCurrentBodegaForm(string|int|null $formId): bool
    {
        return array_key_exists((string) $formId, self::FORMS);
    }

    public static function isHistoricalStockForm(string|int|null $formId): bool
    {
        return (string) $formId === self::LEGACY_FORM_ID;
    }

    /** @return array<int, string> */
    public static function currentFormIds(): array
    {
        return array_keys(self::FORMS);
    }

    /**
     * Sincroniza de inmediato una respuesta avisada por el webhook de Kizeo.
     * El contenido se vuelve a consultar desde la API, por lo que el webhook
     * solo actúa como señal y no como fuente de verdad para inventario.
     */
    public function syncSourceRecord(string $formId, string $dataId, array $webhookData = []): EntregaBodega
    {
        if (! $this->supportsForm($formId)) {
            throw new \InvalidArgumentException("El formulario Kizeo {$formId} no está configurado para Bodega.");
        }

        $mapping = self::FORMS[$formId];
        $stored = EntregaBodega::query()
            ->with('inventarioAplicacion:id,entrega_bodega_id,estado')
            ->where('kizeo_form_id', $formId)
            ->where('kizeo_data_id', $dataId)
            ->first();

        Cache::forget("kizeo_record_{$formId}_{$dataId}");

        try {
            $record = $this->kizeo->getRecord($formId, $dataId);
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), '[404]')) {
                $delivery = $this->markSourceRecordMissing(
                    $formId,
                    $dataId,
                    'El registro ya no está disponible en Kizeo. No puede aplicarse como una nueva salida de stock.',
                );

                if ($delivery) {
                    return $delivery;
                }
            }

            throw $exception;
        }

        if (! $record) {
            $delivery = $this->markSourceRecordMissing(
                $formId,
                $dataId,
                'El registro ya no está disponible en Kizeo. No puede aplicarse como una nueva salida de stock.',
            );

            if ($delivery) {
                return $delivery;
            }

            throw new \RuntimeException("Kizeo no devolvió la respuesta {$dataId} del formulario {$formId}.");
        }

        $metadata = $this->webhookMetadata($webhookData, $record, $dataId);
        $normalized = $this->normalizeRecord($record, $metadata, $formId, $mapping, $stored);

        return $this->persistNormalizedRecord($formId, $dataId, $normalized);
    }

    /**
     * Registra una eliminación notificada por Kizeo, sin ejecutar ninguna
     * reversa automática de inventario.
     */
    public function markSourceRecordMissing(string $formId, string $dataId, string $alert): ?EntregaBodega
    {
        $delivery = EntregaBodega::query()
            ->with('inventarioAplicacion:id,entrega_bodega_id,estado')
            ->where('kizeo_form_id', $formId)
            ->where('kizeo_data_id', $dataId)
            ->first();

        if (! $delivery || $delivery->fuente_ausente_desde) {
            return $delivery;
        }

        return $this->markDeliverySourceMissing($delivery, $alert);
    }

    public function sync(int $limit = 250, bool $force = false): array
    {
        $created = 0;
        $updated = 0;
        $failed = 0;
        $totalSource = 0;
        $pendingCount = 0;
        $remaining = 0;
        $remainingCapacity = max(1, $limit);

        foreach (self::FORMS as $formId => $mapping) {
            try {
                $metadata = array_values(array_filter(
                    $this->kizeo->getFormData($formId, $force),
                    fn (array $record) => ! empty($record['id']),
                ));
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('No se pudo consultar un formulario de Bodega en Kizeo.', [
                    'form_id' => $formId,
                    'error' => $exception->getMessage(),
                ]);

                // Solo un 404 confirma que el formulario completo fue eliminado. Un error
                // transitorio nunca debe modificar ni devolver stock de forma automática.
                if (str_contains($exception->getMessage(), '[404]')) {
                    $this->markMissingSourceRecords(
                        $formId,
                        null,
                        'El formulario de origen ya no está disponible en Kizeo. Revisa el comprobante y la trazabilidad antes de corregir inventario.',
                    );
                }

                continue;
            }

            usort($metadata, fn (array $left, array $right) => strcmp(
                (string) ($right['update_time'] ?? $right['create_time'] ?? ''),
                (string) ($left['update_time'] ?? $left['create_time'] ?? ''),
            ));

            $totalSource += count($metadata);
            $sourceDataIds = array_map(fn (array $record) => (string) $record['id'], $metadata);
            $known = EntregaBodega::query()
                ->with([
                    'inventarioAplicacion:id,entrega_bodega_id,estado',
                    'items:id,entrega_bodega_id,articulo',
                ])
                ->where('kizeo_form_id', $formId)
                ->whereIn('kizeo_data_id', $sourceDataIds)
                ->get([
                    'id',
                    'kizeo_form_id',
                    'kizeo_data_id',
                    'kizeo_updated_at',
                    'estado_fuente',
                    'alerta_fuente',
                    'fuente_ausente_desde',
                ])
                ->keyBy(fn (EntregaBodega $delivery) => $this->sourceKey($delivery->kizeo_form_id, $delivery->kizeo_data_id));

            $allPending = $force
                ? $metadata
                : array_values(array_filter($metadata, fn (array $record) => $this->needsSync(
                    $record,
                    $known->get($this->sourceKey($formId, (string) $record['id'])),
                )));
            $pending = array_slice($allPending, 0, $remainingCapacity);
            $pendingCount += count($pending);
            $remaining += max(0, count($allPending) - count($pending));
            $remainingCapacity -= count($pending);

            foreach ($pending as $metadataRecord) {
                $dataId = (string) $metadataRecord['id'];

                try {
                    $sourceKey = $this->sourceKey($formId, $dataId);
                    if ($force || $this->needsSync($metadataRecord, $known->get($sourceKey))) {
                        Cache::forget("kizeo_record_{$formId}_{$dataId}");
                    }

                    $record = $this->kizeo->getRecord($formId, $dataId);
                    if (! $record) {
                        $failed++;

                        continue;
                    }

                    $stored = $known->get($sourceKey);
                    $normalized = $this->normalizeRecord($record, $metadataRecord, $formId, $mapping, $stored);
                    $exists = $known->has($sourceKey);

                    $this->persistNormalizedRecord($formId, $dataId, $normalized);

                    $exists ? $updated++ : $created++;
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('No se pudo sincronizar una entrega de bodega desde Kizeo.', [
                        'form_id' => $formId,
                        'data_id' => $dataId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $this->markMissingSourceRecords(
                $formId,
                $sourceDataIds,
                'El registro ya no está disponible en Kizeo. No puede aplicarse como una nueva salida de stock.',
            );
        }

        return [
            'total_source' => $totalSource,
            'pending' => $pendingCount,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'remaining' => $remaining,
        ];
    }

    private function sourceKey(?string $formId, ?string $dataId): string
    {
        return (string) $formId.':'.(string) $dataId;
    }

    /** @return array<string, mixed> */
    private function webhookMetadata(array $webhookData, array $record, string $dataId): array
    {
        return [
            'id' => $dataId,
            'record_number' => $record['record_number'] ?? $webhookData['record_number'] ?? null,
            'create_time' => $record['create_time'] ?? $webhookData['create_time'] ?? $webhookData['answer_time'] ?? null,
            'update_time' => $record['update_time'] ?? $webhookData['update_time'] ?? $webhookData['update_answer_time'] ?? null,
            'answer_time' => $record['answer_time'] ?? $webhookData['answer_time'] ?? null,
            'user_name' => $record['user_name'] ?? $webhookData['user_name'] ?? null,
            'update_user_name' => $record['update_user_name'] ?? $webhookData['update_user_name'] ?? null,
        ];
    }

    /**
     * @param array{attributes: array<string, mixed>, items: array<int, array<string, mixed>>} $normalized
     */
    private function persistNormalizedRecord(string $formId, string $dataId, array $normalized): EntregaBodega
    {
        $isNew = false;
        $delivery = DB::transaction(function () use ($formId, $dataId, $normalized, &$isNew) {
            $isNew = ! EntregaBodega::query()
                ->where('kizeo_form_id', $formId)
                ->where('kizeo_data_id', $dataId)
                ->exists();

            $delivery = EntregaBodega::updateOrCreate(
                ['kizeo_form_id' => $formId, 'kizeo_data_id' => $dataId],
                $normalized['attributes'],
            );

            $delivery->items()->delete();
            if ($normalized['items']) {
                $delivery->items()->createMany($normalized['items']);
            }

            return $delivery->refresh();
        });

        $delivery = $delivery->load('items', 'inventarioAplicacion.lineas');
        $this->inventoryStock()->tryAutoApplyNewKizeoDelivery($delivery, $isNew);
        $this->inventoryStock()->tryAutoReconcileUpdatedKizeoDelivery($delivery);

        return $delivery->fresh(['items']) ?? $delivery;
    }

    private function inventoryStock(): InventarioStockService
    {
        return $this->inventoryStock ?? app(InventarioStockService::class);
    }

    private function needsSync(array $metadata, ?EntregaBodega $stored): bool
    {
        if (! $stored) {
            return true;
        }

        // Desde agosto de 2026 los formularios pueden entregar el UUID del
        // ítem de la lista avanzada en vez de su etiqueta. Mientras exista un
        // UUID sin resolver, la respuesta debe reintentarse aunque Kizeo no
        // haya cambiado su fecha: así se normalizan también entregas que se
        // sincronizaron antes de desplegar esta compatibilidad.
        if ($stored->relationLoaded('items') && $stored->items->contains(
            fn ($item) => $this->isAdvancedCatalogItemId($item->articulo),
        )) {
            return true;
        }

        // Las alertas se vuelven a consultar para reconocer una respuesta restaurada
        // en Kizeo o una reversa local que ya resolvió la diferencia de stock.
        if (in_array($stored->estado_fuente, ['ELIMINADA_EN_KIZEO', 'INCOMPLETA', 'REQUIERE_REVISION'], true)) {
            return true;
        }

        return $this->sourceHasChanged($metadata, $stored);
    }

    private function sourceHasChanged(array $metadata, EntregaBodega $stored): bool
    {
        $sourceUpdated = $this->dateTimeValue($metadata['update_time'] ?? $metadata['create_time'] ?? null);

        return $sourceUpdated && (! $stored->kizeo_updated_at || ! $stored->kizeo_updated_at->equalTo($sourceUpdated));
    }

    private function normalizeRecord(array $record, array $metadata, string $formId, array $mapping, ?EntregaBodega $stored): array
    {
        $fields = $record['fields'] ?? [];
        $tipoOperacion = $this->limit($this->fieldValue($fields, $mapping['tipo']), 120);
        $items = $this->items(
            $this->subformRows($fields, $mapping['subform']),
            $mapping['articulo'],
            $mapping['talla'],
        );
        $source = $this->sourceState($fields, $items, $mapping, $metadata, $stored);

        return [
            'attributes' => [
                'kizeo_form_id' => $formId,
                'origen_formulario' => $mapping['name'],
                'tipo_operacion' => $tipoOperacion,
                'flujo_inventario' => $this->inventoryFlow($tipoOperacion, $mapping),
                'estado_fuente' => $source['state'],
                'alerta_fuente' => $source['alert'],
                'fuente_ausente_desde' => null,
                'kizeo_record_number' => $this->integerValue($record['record_number'] ?? $metadata['record_number'] ?? null),
                'kizeo_created_at' => $this->dateTimeValue($record['create_time'] ?? $metadata['create_time'] ?? null),
                'kizeo_updated_at' => $this->dateTimeValue($record['update_time'] ?? $metadata['update_time'] ?? null),
                'registrado_por' => $this->limit($this->recordUserName($record, $metadata), 200),
                'centro' => $this->limit($this->fieldValue($fields, $mapping['centro']), 180),
                'rut' => $this->limit($this->fieldValue($fields, $mapping['rut'] ?? ''), 30),
                'nombre' => $this->limit($this->fieldValue($fields, $mapping['nombre']), 200),
                'fecha_pedido' => $this->dateValue($this->fieldValue($fields, $mapping['fecha']))
                    ?? $this->dateValue($record['answer_time'] ?? $metadata['answer_time'] ?? null),
                'lineas_count' => count($items),
                'unidades_total' => array_sum(array_column($items, 'cantidad')),
                'raw_payload' => $record,
                'synced_at' => now(),
            ],
            'items' => $items,
        ];
    }

    /**
     * @param array<int, array{linea: int, articulo: string, talla: ?string, cantidad: int}> $items
     * @return array{state: string, alert: ?string}
     */
    private function sourceState(array $fields, array $items, array $mapping, array $metadata, ?EntregaBodega $stored): array
    {
        $issue = $this->sourceIssue($fields, $items, $mapping);
        $hasAppliedStock = $stored?->inventarioAplicacion?->estado === 'APLICADA';
        $changed = $stored && $this->sourceHasChanged($metadata, $stored);

        if ($issue !== null) {
            return [
                'state' => $hasAppliedStock ? 'REQUIERE_REVISION' : 'INCOMPLETA',
                'alert' => $issue,
            ];
        }

        if ($hasAppliedStock && ($changed || $stored?->fuente_ausente_desde || $stored?->estado_fuente === 'REQUIERE_REVISION')) {
            return [
                'state' => 'REQUIERE_REVISION',
                'alert' => $stored?->fuente_ausente_desde
                    ? 'El comprobante volvió a estar disponible en Kizeo después de una ausencia. Revisa la salida previamente aplicada.'
                    : 'El comprobante fue actualizado en Kizeo después de afectar el stock. Revisa la salida y revérsala si corresponde.',
            ];
        }

        return ['state' => 'ACTIVA', 'alert' => null];
    }

    /**
     * Detecta una respuesta incompleta o un cambio de claves del formulario antes
     * de que pueda ofrecerse para afectar inventario.
     *
     * @param array<int, array{linea: int, articulo: string, talla: ?string, cantidad: int}> $items
     */
    private function sourceIssue(array $fields, array $items, array $mapping): ?string
    {
        if (! array_key_exists($mapping['centro'], $fields)) {
            return 'El campo Centro de costo ya no está disponible en la respuesta de Kizeo. La entrega quedó bloqueada para proteger el inventario.';
        }

        if (! array_key_exists($mapping['subform'], $fields)) {
            return 'La sección de artículos del formulario Kizeo no está disponible. La entrega quedó bloqueada hasta revisar el formulario.';
        }

        $rows = $this->subformRows($fields, $mapping['subform']);
        if ($rows !== [] && ! collect($rows)->contains(fn (array $row) => array_key_exists($mapping['articulo'], $row))) {
            return 'El campo de artículo del formulario Kizeo cambió o no está disponible. La entrega quedó bloqueada para proteger el inventario.';
        }

        if ($items === []) {
            return 'El comprobante no tiene artículos con cantidad válida. Corrige la respuesta en Kizeo antes de aplicarla al inventario.';
        }

        if (collect($items)->contains(fn (array $item) => $this->isAdvancedCatalogItemId($item['articulo'] ?? null))) {
            return 'Uno o más artículos llegaron como código de la lista avanzada de Kizeo y no se pudieron resolver. La entrega quedó bloqueada para evitar descontar un artículo equivocado.';
        }

        return null;
    }

    /**
     * Una ausencia en Kizeo se registra localmente para impedir descuentos nuevos.
     * Las salidas ya aplicadas requieren una reversa explícita, nunca automática.
     *
     * @param array<int, string>|null $sourceDataIds Null when the complete Kizeo form is unavailable.
     */
    private function markMissingSourceRecords(string $formId, ?array $sourceDataIds, string $alert): void
    {
        $query = EntregaBodega::query()
            ->with('inventarioAplicacion:id,entrega_bodega_id,estado')
            ->where('kizeo_form_id', $formId)
            ->whereNull('fuente_ausente_desde');

        if ($sourceDataIds !== null && $sourceDataIds !== []) {
            $query->whereNotIn('kizeo_data_id', $sourceDataIds);
        }

        foreach ($query->get() as $delivery) {
            $this->markDeliverySourceMissing($delivery, $alert);
        }
    }

    private function markDeliverySourceMissing(EntregaBodega $delivery, string $alert): EntregaBodega
    {
        $hasAppliedStock = $delivery->inventarioAplicacion?->estado === 'APLICADA';
        $delivery->update([
            'estado_fuente' => $hasAppliedStock ? 'REQUIERE_REVISION' : 'ELIMINADA_EN_KIZEO',
            'alerta_fuente' => $hasAppliedStock
                ? $alert.' Esta salida ya afectó stock: revérsala manualmente si se confirma que no corresponde.'
                : $alert,
            'fuente_ausente_desde' => now(),
        ]);

        return $delivery->refresh();
    }

    private function items(array $rows, string $articleKey, ?string $sizeKey): array
    {
        $items = [];

        foreach ($rows as $index => $row) {
            $articulo = $this->resolveAdvancedCatalogItem($this->fieldValue($row, $articleKey));
            [$articulo, $tallaEnArticulo] = $this->splitArticleAndSize($articulo);
            $talla = $this->fieldValue($row, $sizeKey ?? '') ?: $tallaEnArticulo;
            $articulo = $this->limit($articulo, 200);
            $talla = $this->limit($talla, 80);
            $cantidad = max(0, $this->integerValue($this->fieldValue($row, 'cantidad')) ?? 0);

            // En la entrega masiva la cantidad es opcional. Una línea sin cantidad
            // se conserva en el comprobante original, pero no debe quedar disponible
            // para conciliar ni modificar stock.
            if ($cantidad === 0) {
                continue;
            }

            $items[] = [
                'linea' => $index + 1,
                'articulo' => $articulo ?: 'Sin artículo',
                'talla' => $talla,
                'cantidad' => $cantidad,
            ];
        }

        return $items;
    }

    /**
     * Los formularios históricos guardaban el texto del artículo. Los nuevos
     * selectores de la lista avanzada guardan el UUID del ítem. Primero se
     * usa la relación que SAEP conserva al publicar su catálogo; si la lista
     * aún no fue publicada, se consulta su etiqueta actual en Kizeo.
     */
    private function resolveAdvancedCatalogItem(?string $value): ?string
    {
        if (! $this->isAdvancedCatalogItemId($value)) {
            return $value;
        }

        $listId = trim((string) config('services.kizeo.inventory_catalog_list_id'));
        if ($listId === '') {
            return $value;
        }

        $mapping = InventarioKizeoCatalogItem::query()
            ->with('variante.producto')
            ->where('kizeo_list_id', $listId)
            ->where('kizeo_item_id', $value)
            ->first();

        if ($mapping?->variante?->producto) {
            return trim(preg_replace('/\s+/', ' ', $mapping->variante->producto->nombre)
                .' T-'.($this->kizeoSizeForCatalog($mapping->variante->talla)));
        }

        return $this->advancedCatalogItems()[$value]['label'] ?? $value;
    }

    /** @return array<string, array<string, mixed>> */
    private function advancedCatalogItems(): array
    {
        if ($this->advancedCatalogItemsById !== null) {
            return $this->advancedCatalogItemsById;
        }

        $listId = trim((string) config('services.kizeo.inventory_catalog_list_id'));
        if ($listId === '') {
            return $this->advancedCatalogItemsById = [];
        }

        try {
            return $this->advancedCatalogItemsById = collect($this->kizeo->getListItems($listId))
                ->filter(fn (array $item) => filled($item['id'] ?? null) && filled($item['label'] ?? null))
                ->keyBy(fn (array $item) => (string) $item['id'])
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('No se pudo resolver un artículo de la lista avanzada Kizeo.', [
                'list_id' => $listId,
                'error' => $exception->getMessage(),
            ]);

            return $this->advancedCatalogItemsById = [];
        }
    }

    private function isAdvancedCatalogItemId(?string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            trim((string) $value),
        );
    }

    private function kizeoSizeForCatalog(?string $size): string
    {
        $normalized = $this->comparisonKey($size);

        return in_array($normalized, ['', 'na', 'estandar', 'sintalla', 'unica', 'unitalla'], true)
            ? 'NA'
            : Str::upper(trim((string) $size));
    }

    /**
     * Los formularios nuevos entregan la variante como "Artículo … T-XL".
     * Se conserva el nombre de producto limpio y la talla en su campo propio.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitArticleAndSize(?string $value): array
    {
        if (! $value || ! preg_match('/\s+T-([^\s]+)$/ui', $value, $matches, PREG_OFFSET_CAPTURE)) {
            return [$value, null];
        }

        return [trim(substr($value, 0, $matches[0][1])), trim($matches[1][0])];
    }

    private function inventoryFlow(?string $tipoOperacion, array $mapping): string
    {
        $entryTypes = array_map(fn (string $type) => $this->comparisonKey($type), $mapping['entradas'] ?? []);

        return in_array($this->comparisonKey($tipoOperacion), $entryTypes, true)
            ? 'ENTRADA'
            : $mapping['flujo'];
    }

    private function comparisonKey(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii((string) $value))) ?: '';
    }

    private function subformRows(array $fields, string $key): array
    {
        $value = $fields[$key]['value'] ?? $fields[$key]['result'] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private function fieldValue(array $fields, string $key): ?string
    {
        $field = $fields[$key] ?? null;
        $value = is_array($field) ? ($field['value'] ?? $field['result'] ?? null) : $field;

        return $this->stringValue($value);
    }

    private function recordUserName(array $record, array $metadata): ?string
    {
        foreach (['user_name', 'update_user_name', 'recipient_name'] as $field) {
            $value = $this->stringValue($record[$field] ?? $metadata[$field] ?? null);
            if ($value) {
                return $value;
            }
        }

        $fullName = trim(implode(' ', array_filter([
            $this->stringValue($record['first_name'] ?? $metadata['first_name'] ?? null),
            $this->stringValue($record['last_name'] ?? $metadata['last_name'] ?? null),
        ])));

        return $fullName !== ''
            ? $fullName
            : $this->userName($record['user'] ?? $metadata['user'] ?? null);
    }

    private function userName(mixed $user): ?string
    {
        if (! is_array($user)) {
            return $this->stringValue($user);
        }

        $fullName = trim(implode(' ', array_filter([
            $this->stringValue($user['first_name'] ?? null),
            $this->stringValue($user['last_name'] ?? null),
        ])));

        return $fullName !== ''
            ? $fullName
            : $this->stringValue($user['name'] ?? $user['email'] ?? $user['username'] ?? null);
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            foreach (['label', 'name', 'caption', 'value', 'code', 'date'] as $key) {
                if (array_key_exists($key, $value)) {
                    return $this->stringValue($value[$key]);
                }
            }

            if (array_is_list($value)) {
                $values = array_filter(array_map(fn ($item) => $this->stringValue($item), $value));

                return $values ? implode(', ', $values) : null;
            }

            return null;
        }

        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : preg_replace('/\s+/u', ' ', $value);
    }

    private function dateValue(?string $value): ?string
    {
        try {
            return $value ? Carbon::parse($value)->toDateString() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateTimeValue(mixed $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse((string) $value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function integerValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function limit(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $length);
    }
}
