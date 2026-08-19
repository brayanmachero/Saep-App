<?php

namespace App\Services;

use App\Models\ObservacionConductaCcu;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ObservacionConductaCcuSyncService
{
    private const FORM_ID = '1156826';
    private const CENTER_LIST_ID = '483239';

    public function __construct(private readonly KizeoService $kizeo)
    {
    }

    /**
     * Sincroniza solo respuestas nuevas por defecto. --force vuelve a leer todas
     * las respuestas para incorporar modificaciones hechas en Kizeo.
     */
    public function sync(int $limit = 250, bool $force = false): array
    {
        $formId = (string) config('services.kizeo.observacion_ccu_form_id', self::FORM_ID);
        $metadata = $this->kizeo->getFormData($formId, $force);
        $metadata = array_values(array_filter($metadata, fn ($record) => !empty($record['id'])));

        usort($metadata, fn ($a, $b) => strcmp(
            (string) ($b['update_time'] ?? $b['create_time'] ?? ''),
            (string) ($a['update_time'] ?? $a['create_time'] ?? '')
        ));

        $knownIds = ObservacionConductaCcu::query()
            ->whereIn('kizeo_data_id', array_map(fn ($record) => (string) $record['id'], $metadata))
            ->pluck('kizeo_data_id')
            ->flip()
            ->all();

        $pending = $force
            ? $metadata
            : array_values(array_filter($metadata, fn ($record) => !isset($knownIds[(string) $record['id']])));

        $pending = array_slice($pending, 0, max(1, $limit));
        $centers = $this->centerLabels();
        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($pending as $metadataRecord) {
            $dataId = (string) $metadataRecord['id'];

            try {
                if ($force) {
                    Cache::forget("kizeo_record_{$formId}_{$dataId}");
                }

                $record = $this->kizeo->getRecord($formId, $dataId);
                if (!$record) {
                    $failed++;
                    continue;
                }

                $attributes = $this->normalizeRecord($record, $metadataRecord, $centers);
                $exists = isset($knownIds[$dataId]);

                ObservacionConductaCcu::updateOrCreate(
                    ['kizeo_data_id' => $dataId],
                    $attributes
                );

                $exists ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('No se pudo sincronizar una observacion CCU desde Kizeo.', [
                    'form_id' => $formId,
                    'data_id' => $dataId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total_source' => count($metadata),
            'pending' => count($pending),
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'remaining' => max(0, count($metadata) - count($pending) - ($force ? 0 : count($knownIds))),
        ];
    }

    private function centerLabels(): array
    {
        try {
            return collect($this->kizeo->getListItems(self::CENTER_LIST_ID))
                ->mapWithKeys(fn (array $item) => [
                    (string) ($item['id'] ?? '') => (string) ($item['label'] ?? $item['id'] ?? ''),
                ])
                ->filter()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('No se pudo cargar la lista de centros CCU desde Kizeo.', [
                'list_id' => self::CENTER_LIST_ID,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function normalizeRecord(array $record, array $metadata, array $centers): array
    {
        $fields = $record['fields'] ?? [];
        $typeValues = $this->fieldValues($fields, 'negativa_1');
        $type = $typeValues ? implode(', ', $typeValues) : $this->fieldValue($fields, 'negativa_1');
        $centerRaw = $this->fieldValue($fields, 'centro_de_distribucion');

        return [
            'kizeo_record_number' => $this->integerValue($record['record_number'] ?? $metadata['record_number'] ?? null),
            'kizeo_created_at' => $this->dateTimeValue($record['create_time'] ?? $metadata['create_time'] ?? null),
            'kizeo_updated_at' => $this->dateTimeValue($record['update_time'] ?? $metadata['update_time'] ?? null),
            'fecha_observacion' => $this->dateValue($this->fieldValue($fields, 'fecha'))
                ?? $this->dateValue($record['answer_time'] ?? $metadata['answer_time'] ?? null),
            'centro' => $this->limit($centers[$centerRaw] ?? $centerRaw, 160),
            'turno' => $this->limit($this->fieldValue($fields, 'turno'), 50),
            'observador_nombre' => $this->limit($this->fieldValue($fields, 'nombre_del_observador'), 200),
            'observador_cargo' => $this->limit($this->fieldValue($fields, 'cargo'), 180),
            'trabajador_rut' => $this->limit($this->fieldValue($fields, 'nombre_del_trabajador'), 30),
            'trabajador_nombre' => $this->limit($this->fieldValue($fields, 'nombre_trabajador_observado'), 200),
            'trabajador_cargo' => $this->limit($this->fieldValue($fields, 'cargo1'), 180),
            'antiguedad_cargo' => $this->limit($this->fieldValue($fields, 'antiguedad_en_el_cargo'), 80),
            'tipo_observacion' => $this->limit($type, 600),
            'clasificacion' => $this->classificationFor($typeValues ?: array_filter([$type])),
            'conducta_observada' => $this->fieldValue($fields, 'conducta_observada'),
            'medida_control' => $this->limit($this->fieldValue($fields, 'medida_de_control'), 250),
            'retroalimentacion' => $this->fieldValue($fields, 'retroalimentacion'),
            'synced_at' => now(),
        ];
    }

    private function fieldValue(array $fields, string $key): ?string
    {
        $field = $fields[$key] ?? null;
        $value = is_array($field) ? ($field['value'] ?? $field['result'] ?? null) : $field;

        return $this->stringValue($value);
    }

    private function fieldValues(array $fields, string $key): array
    {
        $field = $fields[$key] ?? null;
        $values = is_array($field) ? ($field['valuesAsArray'] ?? null) : null;

        if (!is_array($values)) {
            return array_filter([$this->fieldValue($fields, $key)]);
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($value) => $this->stringValue($value),
            $values,
        ))));
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

    private function classificationFor(array $types): string
    {
        $types = array_values(array_filter(
            $types,
            fn ($type) => trim((string) $type) !== '',
        ));

        // El formulario CCU registra solo hallazgos. Las opciones SIEMPRE, NUNCA
        // y "No Cumple PTS" identifican la conducta observada, no un resultado positivo.
        return $types === [] ? 'Por revisar' : 'Negativa';
    }

    private function dateValue(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateTimeValue(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
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
