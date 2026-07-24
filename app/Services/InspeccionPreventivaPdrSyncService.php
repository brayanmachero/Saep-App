<?php

namespace App\Services;

use App\Models\InspeccionPreventivaPdr;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InspeccionPreventivaPdrSyncService
{
    private const FORM_ID = '973787';

    public function __construct(private readonly KizeoService $kizeo)
    {
    }

    public function sync(int $limit = 250, bool $force = false): array
    {
        $formId = trim((string) config('services.kizeo.inspeccion_form_id')) ?: self::FORM_ID;
        $metadata = array_values(array_filter(
            $this->kizeo->getFormData($formId, $force),
            fn (array $record) => !empty($record['id']),
        ));

        usort($metadata, fn (array $a, array $b) => strcmp(
            (string) ($b['update_time'] ?? $b['create_time'] ?? ''),
            (string) ($a['update_time'] ?? $a['create_time'] ?? ''),
        ));

        $knownIds = InspeccionPreventivaPdr::query()
            ->whereIn('kizeo_data_id', array_map(fn (array $record) => (string) $record['id'], $metadata))
            ->pluck('kizeo_data_id')
            ->flip()
            ->all();

        $pending = $force
            ? $metadata
            : array_values(array_filter($metadata, fn (array $record) => !isset($knownIds[(string) $record['id']])));
        $pending = array_slice($pending, 0, max(1, $limit));

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

                $exists = isset($knownIds[$dataId]);
                InspeccionPreventivaPdr::updateOrCreate(
                    ['kizeo_data_id' => $dataId],
                    $this->normalizeRecord($record, $metadataRecord),
                );
                $exists ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('No se pudo sincronizar una inspección preventiva PDR desde Kizeo.', [
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

    private function normalizeRecord(array $record, array $metadata): array
    {
        $fields = $record['fields'] ?? [];
        $conditions = $this->subformRows($fields, 'inspeccion1');
        $measures = $this->subformRows($fields, 'medidas_correctivas_y_o_p1');
        $conditionTexts = array_values(array_filter(array_map(
            fn (array $row) => $this->fieldValue($row, 'descripcion_de_la_accion_o_co'),
            $conditions,
        )));
        $measureTexts = array_values(array_filter(array_map(
            fn (array $row) => $this->fieldValue($row, 'medidas_correctivas_preventiv'),
            $measures,
        )));
        $frequencies = $this->measureValues($measures, 'frecuencia');
        $verifications = $this->measureValues($measures, 'verificacion');
        $photoCount = array_sum(array_map(
            fn (array $row) => $this->mediaCount($this->fieldValue($row, 'registro_fotografico_en_cas')),
            $conditions,
        ));

        return [
            'kizeo_record_number' => $this->integerValue($record['record_number'] ?? $metadata['record_number'] ?? null),
            'kizeo_created_at' => $this->dateTimeValue($record['create_time'] ?? $metadata['create_time'] ?? null),
            'kizeo_updated_at' => $this->dateTimeValue($record['update_time'] ?? $metadata['update_time'] ?? null),
            'fecha_inspeccion' => $this->dateValue($this->fieldValue($fields, 'fecha_'))
                ?? $this->dateValue($record['answer_time'] ?? $metadata['answer_time'] ?? null),
            'hora_inspeccion' => $this->limit($this->fieldValue($fields, 'hora_'), 10),
            'centro' => $this->limit($this->fieldValue($fields, 'centro_de_distribucion'), 160),
            'responsable_area' => $this->limit($this->fieldValue($fields, 'responsable_area_'), 200),
            'inspector_nombre' => $this->limit($this->fieldValue($fields, 'inspeccion_efectuada_por_'), 200),
            'inspector_cargo' => $this->limit($this->fieldValue($fields, 'cargo_'), 180),
            'inspector_secundario_nombre' => $this->limit($this->fieldValue($fields, 'inspeccion_efectuada_por1'), 200),
            'inspector_secundario_cargo' => $this->limit($this->fieldValue($fields, 'cargo_de_quien_inspecciona1'), 180),
            'area_inspeccionada' => $this->limit($this->fieldValue($fields, 'areas_inspeccionadas_'), 255),
            'objetivo' => $this->limit($this->fieldValue($fields, 'objetivo_1'), 100),
            'condiciones_count' => count($conditionTexts),
            'evidencias_count' => $photoCount,
            'condiciones_resumen' => $this->summary($conditionTexts),
            'medidas_count' => count($measureTexts),
            'medidas_resumen' => $this->summary($measureTexts),
            'frecuencias_text' => $this->tokens($frequencies),
            'verificaciones_text' => $this->tokens($verifications),
            'responsable_medida' => $this->limit($this->fieldValue($measures[0] ?? [], 'responsable_de_ejecucion'), 200),
            'synced_at' => now(),
        ];
    }

    private function subformRows(array $fields, string $key): array
    {
        $value = $fields[$key]['value'] ?? $fields[$key]['result'] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private function measureValues(array $rows, string $key): array
    {
        return array_values(array_filter(array_map(
            fn (array $row) => $this->fieldValue($row, $key),
            $rows,
        )));
    }

    private function fieldValue(array $fields, string $key): ?string
    {
        $field = $fields[$key] ?? null;
        $value = is_array($field) ? ($field['value'] ?? $field['result'] ?? null) : $field;

        return $this->stringValue($value);
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

    private function mediaCount(?string $value): int
    {
        return $value ? count(array_filter(array_map('trim', explode(',', $value)))) : 0;
    }

    private function summary(array $values): ?string
    {
        return $values ? mb_substr(implode("\n", $values), 0, 4000) : null;
    }

    private function tokens(array $values): ?string
    {
        return $values ? '|' . implode('|', $values) . '|' : null;
    }

    private function limit(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $length);
    }
}
