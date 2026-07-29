<?php

namespace App\Services;

use App\Models\EntregaBodega;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EntregaBodegaSyncService
{
    private const FORM_ID = '947762';

    public function __construct(private readonly KizeoService $kizeo)
    {
    }

    public function sync(int $limit = 250, bool $force = false): array
    {
        $metadata = array_values(array_filter(
            $this->kizeo->getFormData(self::FORM_ID, $force),
            fn (array $record) => ! empty($record['id']),
        ));

        usort($metadata, fn (array $left, array $right) => strcmp(
            (string) ($right['update_time'] ?? $right['create_time'] ?? ''),
            (string) ($left['update_time'] ?? $left['create_time'] ?? ''),
        ));

        $known = EntregaBodega::query()
            ->whereIn('kizeo_data_id', array_map(fn (array $record) => (string) $record['id'], $metadata))
            ->get(['kizeo_data_id', 'kizeo_updated_at'])
            ->keyBy('kizeo_data_id');

        $allPending = $force
            ? $metadata
            : array_values(array_filter($metadata, fn (array $record) => $this->needsSync($record, $known->get((string) $record['id']))));
        $pending = array_slice($allPending, 0, max(1, $limit));

        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($pending as $metadataRecord) {
            $dataId = (string) $metadataRecord['id'];

            try {
                if ($force || $this->needsSync($metadataRecord, $known->get($dataId))) {
                    Cache::forget("kizeo_record_" . self::FORM_ID . "_{$dataId}");
                }

                $record = $this->kizeo->getRecord(self::FORM_ID, $dataId);
                if (! $record) {
                    $failed++;
                    continue;
                }

                $normalized = $this->normalizeRecord($record, $metadataRecord);
                $exists = $known->has($dataId);

                DB::transaction(function () use ($dataId, $normalized) {
                    $entrega = EntregaBodega::updateOrCreate(
                        ['kizeo_data_id' => $dataId],
                        $normalized['attributes'],
                    );

                    $entrega->items()->delete();
                    if ($normalized['items']) {
                        $entrega->items()->createMany($normalized['items']);
                    }
                });

                $exists ? $updated++ : $created++;
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('No se pudo sincronizar una entrega de bodega desde Kizeo.', [
                    'form_id' => self::FORM_ID,
                    'data_id' => $dataId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'total_source' => count($metadata),
            'pending' => count($pending),
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'remaining' => max(0, count($allPending) - count($pending)),
        ];
    }

    private function needsSync(array $metadata, ?EntregaBodega $stored): bool
    {
        if (! $stored) {
            return true;
        }

        $sourceUpdated = $this->dateTimeValue($metadata['update_time'] ?? $metadata['create_time'] ?? null);

        return $sourceUpdated && (! $stored->kizeo_updated_at || ! $stored->kizeo_updated_at->equalTo($sourceUpdated));
    }

    private function normalizeRecord(array $record, array $metadata): array
    {
        $fields = $record['fields'] ?? [];
        $items = $this->items($this->subformRows($fields, 'epi'));

        return [
            'attributes' => [
                'kizeo_record_number' => $this->integerValue($record['record_number'] ?? $metadata['record_number'] ?? null),
                'kizeo_created_at' => $this->dateTimeValue($record['create_time'] ?? $metadata['create_time'] ?? null),
                'kizeo_updated_at' => $this->dateTimeValue($record['update_time'] ?? $metadata['update_time'] ?? null),
                'registrado_por' => $this->limit($this->recordUserName($record, $metadata), 200),
                'centro' => $this->limit($this->fieldValue($fields, 'centro_de_costo1'), 180),
                'rut' => $this->limit($this->fieldValue($fields, 'rut'), 30),
                'nombre' => $this->limit($this->fieldValue($fields, 'nombre'), 200),
                'fecha_pedido' => $this->dateValue($this->fieldValue($fields, 'fecha_del_pedido'))
                    ?? $this->dateValue($record['answer_time'] ?? $metadata['answer_time'] ?? null),
                'lineas_count' => count($items),
                'unidades_total' => array_sum(array_column($items, 'cantidad')),
                'raw_payload' => $record,
                'synced_at' => now(),
            ],
            'items' => $items,
        ];
    }

    private function items(array $rows): array
    {
        $items = [];

        foreach ($rows as $index => $row) {
            $articulo = $this->limit($this->fieldValue($row, 'concepto'), 200);
            $talla = $this->limit($this->fieldValue($row, 'talla'), 80);
            $cantidad = max(0, $this->integerValue($this->fieldValue($row, 'cantidad')) ?? 0);

            if (! $articulo && ! $talla && $cantidad === 0) {
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
