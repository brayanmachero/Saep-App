<?php

namespace App\Services;

use App\Models\KizeoAutomationRule;
use App\Models\KizeoAutomationRun;
use App\Models\WebhookLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KizeoAutomationService
{
    private array $formFieldDefinitions = [];
    private array $advancedListLabels = [];

    private const MESES = [
        '01' => 'Enero',
        '02' => 'Febrero',
        '03' => 'Marzo',
        '04' => 'Abril',
        '05' => 'Mayo',
        '06' => 'Junio',
        '07' => 'Julio',
        '08' => 'Agosto',
        '09' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre',
    ];

    public function __construct(
        private KizeoService $kizeo,
        private OneDriveService $oneDrive
    ) {
    }

    public function process(string $formId, string $dataId, array $payload, ?string $ip = null): array
    {
        try {
            $rules = KizeoAutomationRule::active()
                ->forForm($formId)
                ->orderBy('priority')
                ->orderBy('id')
                ->get();
        } catch (QueryException $e) {
            Log::warning('Kizeo Automation: tablas no disponibles, se usa flujo legacy', [
                'formId' => $formId,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyResult(false);
        }

        if ($rules->isEmpty()) {
            return $this->emptyResult(false);
        }

        $record = $this->resolveRecord($formId, $dataId, $payload);
        $context = $this->buildContext($formId, $dataId, $payload, $record);
        $result = [
            'has_rules' => true,
            'matched' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'errors' => [],
            'continue_legacy' => true,
        ];

        foreach ($rules as $rule) {
            if (!$this->matchesConditions($rule->conditions ?? [], $context)) {
                continue;
            }

            $result['matched']++;

            if (!$rule->continue_legacy) {
                $result['continue_legacy'] = false;
            }

            $runResult = $this->runRule($rule, $formId, $dataId, $context, $ip);

            if ($runResult['success']) {
                $result['succeeded']++;
            } else {
                $result['failed']++;
                $result['errors'][] = $runResult['error'];
            }
        }

        return $result;
    }

    public function retryRun(KizeoAutomationRun $sourceRun, ?string $ip = null): array
    {
        $rule = $sourceRun->rule;

        if (!$rule) {
            throw new \RuntimeException('La regla original ya no está disponible para reintentar.');
        }

        $formId = (string) $sourceRun->form_id;
        $dataId = (string) $sourceRun->data_id;

        if ($formId === '' || $dataId === '') {
            throw new \RuntimeException('La ejecución no tiene Form ID o Data ID válido.');
        }

        $record = $this->resolveRecord($formId, $dataId, []);
        $context = $this->buildContext($formId, $dataId, [], $record);
        $result = $this->runRule($rule, $formId, $dataId, $context, $ip, $sourceRun);

        return [
            'success' => $result['success'],
            'run_id' => $result['run']->id,
            'filename' => $result['run']->filename,
            'sharepoint_path' => $result['run']->sharepoint_path,
            'error' => $result['error'] ?? null,
        ];
    }

    private function emptyResult(bool $hasRules): array
    {
        return [
            'has_rules' => $hasRules,
            'matched' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'errors' => [],
            'continue_legacy' => true,
        ];
    }

    private function executeRule(KizeoAutomationRule $rule, string $formId, string $dataId, array $context): array
    {
        $pdfContent = $rule->export_id
            ? $this->kizeo->downloadExportPdf($formId, $dataId, $rule->export_id)
            : $this->kizeo->downloadPdf($formId, $dataId);

        if (!$pdfContent || strlen($pdfContent) < 100) {
            throw new \RuntimeException('PDF descargado desde Kizeo está vacío o inválido');
        }

        $folder = $this->renderTemplate($rule->folder_template ?: '', $context);
        $baseFolder = $this->renderTemplate($rule->sharepoint_folder ?: '', $context);
        $filename = $this->ensurePdfFilename($this->renderTemplate($rule->filename_template ?: '', $context));
        $folderPath = $this->cleanSharePointPath(trim($baseFolder . '/' . $folder, '/'));
        $remotePath = trim($folderPath . '/' . $filename, '/');

        $uploaded = $rule->sharepoint_site
            ? $this->oneDrive->uploadFileToSite($rule->sharepoint_site, $pdfContent, $remotePath, 'application/pdf')
            : $this->oneDrive->uploadFile($pdfContent, $remotePath, 'application/pdf', true);

        if (!$uploaded) {
            $lastError = $this->oneDrive->getLastError();
            throw new \RuntimeException($lastError['message'] ?? 'SharePoint no confirmó la subida del archivo');
        }

        return ['filename' => basename($remotePath), 'path' => $remotePath];
    }

    private function runRule(
        KizeoAutomationRule $rule,
        string $formId,
        string $dataId,
        array $context,
        ?string $ip = null,
        ?KizeoAutomationRun $retryOf = null
    ): array {
        $runContext = $this->compactContext($context);

        if ($retryOf) {
            $runContext['manual_retry'] = true;
            $runContext['retry_of_run_id'] = $retryOf->id;
        }

        $run = KizeoAutomationRun::create([
            'kizeo_automation_rule_id' => $rule->id,
            'form_id' => $formId,
            'data_id' => $dataId,
            'status' => 'processing',
            'context' => $runContext,
        ]);

        try {
            $upload = $this->executeRule($rule, $formId, $dataId, $context);

            $run->update([
                'status' => 'success',
                'filename' => $upload['filename'],
                'sharepoint_path' => $upload['path'],
                'processed_at' => now(),
            ]);

            $rule->forceFill(['last_run_at' => now()])->save();

            WebhookLog::logSuccess([
                'origen' => 'kizeo',
                'form_id' => $formId,
                'data_id' => $dataId,
                'tipo' => 'automation_' . $rule->id,
                'resumen' => ($retryOf ? 'Reintento ' : '') . "Automatización {$rule->name}",
                'archivo' => $upload['filename'],
                'sharepoint_path' => $upload['path'],
                'email_enviado' => false,
                'destinatarios' => [],
                'metadata' => [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'form_name' => $context['form_name'] ?? null,
                    'manual_retry' => (bool) $retryOf,
                    'retry_of_run_id' => $retryOf?->id,
                ],
                'ip' => $ip,
            ]);

            return ['success' => true, 'run' => $run->fresh()];
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            WebhookLog::logError([
                'origen' => 'kizeo',
                'form_id' => $formId,
                'data_id' => $dataId,
                'tipo' => 'automation_' . $rule->id,
                'resumen' => ($retryOf ? 'Error reintento ' : 'Error ') . "automatización {$rule->name}",
                'error_message' => $e->getMessage(),
                'metadata' => [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'form_name' => $context['form_name'] ?? null,
                    'manual_retry' => (bool) $retryOf,
                    'retry_of_run_id' => $retryOf?->id,
                ],
                'ip' => $ip,
            ]);

            Log::error('Kizeo Automation: error procesando regla', [
                'rule_id' => $rule->id,
                'formId' => $formId,
                'dataId' => $dataId,
                'retry_of_run_id' => $retryOf?->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'run' => $run->fresh(), 'error' => $e->getMessage()];
        }
    }

    private function resolveRecord(string $formId, string $dataId, array $payload): array
    {
        $payloadRecord = $payload['data'] ?? [];

        if (!empty($payloadRecord['fields'])) {
            return $payloadRecord;
        }

        try {
            return $this->kizeo->getRecord($formId, $dataId) ?: $payloadRecord;
        } catch (\Throwable $e) {
            Log::warning('Kizeo Automation: no se pudo obtener registro completo, se usa payload', [
                'formId' => $formId,
                'dataId' => $dataId,
                'error' => $e->getMessage(),
            ]);

            return $payloadRecord;
        }
    }

    private function buildContext(string $formId, string $dataId, array $payload, array $record): array
    {
        $fields = $record['fields'] ?? $payload['data']['fields'] ?? [];
        $formFields = $this->formFieldDefinitions($formId);
        $context = $this->flattenFields($fields, $formFields);

        $formName = $record['form_name']
            ?? $payload['data']['form_name']
            ?? $payload['form_name']
            ?? "Formulario {$formId}";

        $context = array_merge($context, [
            'form_id' => $formId,
            'data_id' => $dataId,
            'record_number' => (string) ($record['record_number'] ?? $payload['data']['record_number'] ?? $dataId),
            'form_unique_id' => (string) ($record['form_unique_id'] ?? $payload['data']['form_unique_id'] ?? $dataId),
            'form_name' => $formName,
            'record_id' => $dataId,
        ]);

        $dateValue = $context['fecha']
            ?? $context['fecha_y_hora']
            ?? $context['fecha_hora']
            ?? $record['create_time']
            ?? $record['update_time']
            ?? $payload['data']['create_time']
            ?? $payload['created_at']
            ?? now()->toDateTimeString();

        $timestamp = strtotime((string) $dateValue) ?: time();
        $month = date('m', $timestamp);

        return array_merge($context, [
            'fecha' => date('Y-m-d', $timestamp),
            'fecha_hora' => date('Y-m-d H-i', $timestamp),
            'anio' => date('Y', $timestamp),
            'mes' => $month,
            'mes_nombre' => self::MESES[$month] ?? $month,
            'dia' => date('d', $timestamp),
        ]);
    }

    private function flattenFields(array $fields, array $formFields = []): array
    {
        $flat = [];

        foreach ($fields as $key => $field) {
            $definition = is_array($formFields[$key] ?? null) ? $formFields[$key] : [];
            $value = $this->extractValue($field, $definition);
            $rawValue = $this->extractRawValue($field);
            $flat[(string) $key] = $value;

            if ($rawValue !== '' && $rawValue !== $value) {
                $flat[(string) $key . '_id'] = $rawValue;
                $flat[(string) $key . '_raw'] = $rawValue;
            }

            foreach ($this->fieldLabelAliases(is_array($field) ? $field : [], $definition) as $alias) {
                $flat[$alias] = $value;

                if ($rawValue !== '' && $rawValue !== $value) {
                    $flat[$alias . '_id'] = $rawValue;
                    $flat[$alias . '_raw'] = $rawValue;
                }
            }
        }

        return $flat;
    }

    private function fieldLabelAliases(array $field, array $definition): array
    {
        $aliases = [];

        foreach (['label', 'caption', 'name', 'title'] as $source) {
            foreach ([$field[$source] ?? null, $definition[$source] ?? null] as $candidate) {
                if (!is_scalar($candidate)) {
                    continue;
                }

                $slug = Str::slug(trim((string) $candidate), '_');

                if ($slug !== '') {
                    $aliases[$slug] = $slug;
                }
            }
        }

        return array_values($aliases);
    }

    private function extractValue(mixed $value, array $definition = []): string
    {
        if (is_bool($value)) {
            return $value ? 'Si' : 'No';
        }

        if (is_scalar($value) || $value === null) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return '';
        }

        $selectLabel = $this->extractAdvancedSelectLabel($value, $definition);
        if ($selectLabel !== null) {
            return $selectLabel;
        }

        if (array_key_exists('result', $value)) {
            return $this->extractValue($value['result']);
        }

        if (array_key_exists('value', $value)) {
            $extractedValue = $this->extractValue($value['value']);

            if ($extractedValue !== '' || !array_key_exists('text', $value)) {
                return $extractedValue;
            }
        }

        if (array_key_exists('text', $value)) {
            return $this->extractValue($value['text']);
        }

        if (isset($value['date'], $value['hour'])) {
            return trim($value['date'] . ' ' . $value['hour']);
        }

        if (isset($value['label'])) {
            return $this->extractValue($value['label']);
        }

        if (isset($value['code'])) {
            return $this->extractValue($value['code']);
        }

        if (isset($value['file'])) {
            return $this->extractValue($value['file']);
        }

        if (array_is_list($value)) {
            return collect($value)
                ->map(fn ($item) => $this->extractValue($item))
                ->filter(fn ($item) => $item !== '')
                ->implode(', ');
        }

        return trim(json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function extractRawValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return '';
        }

        if (array_key_exists('value', $value)) {
            return $this->extractRawValue($value['value']);
        }

        if (!empty($value['valuesAsArray']) && is_array($value['valuesAsArray'])) {
            return collect($value['valuesAsArray'])
                ->map(fn ($item) => $this->extractRawValue($item))
                ->filter(fn ($item) => $item !== '')
                ->implode(', ');
        }

        if (array_key_exists('result', $value)) {
            return $this->extractRawValue($value['result']);
        }

        return '';
    }

    private function extractAdvancedSelectLabel(array $field, array $definition): ?string
    {
        $fieldType = (string) ($field['type'] ?? $definition['type'] ?? '');
        $isAdvancedList = filter_var($definition['list_is_advanced'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $listId = (string) ($definition['list_id'] ?? $field['list_id'] ?? '');

        if ($fieldType !== 'select' || !$isAdvancedList || $listId === '') {
            return null;
        }

        $values = collect($field['valuesAsArray'] ?? [$field['value'] ?? null])
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '')
            ->values()
            ->all();

        if ($values === []) {
            return null;
        }

        $labels = $this->advancedListLabels($listId);
        $resolved = $this->resolveAdvancedListValues($values, $labels);

        if (count($resolved) < count($values) && $this->hasTechnicalIds($values)) {
            $labels = $this->advancedListLabels($listId, true);
            $resolved = $this->resolveAdvancedListValues($values, $labels);
        }

        return $resolved ? implode(', ', $resolved) : null;
    }

    private function resolveAdvancedListValues(array $values, array $labels): array
    {
        return collect($values)
            ->map(fn ($id) => $labels[(string) $id] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function hasTechnicalIds(array $values): bool
    {
        return collect($values)->contains(
            fn ($value) => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $value) === 1
        );
    }

    private function formFieldDefinitions(string $formId): array
    {
        if (array_key_exists($formId, $this->formFieldDefinitions)) {
            return $this->formFieldDefinitions[$formId];
        }

        try {
            $this->formFieldDefinitions[$formId] = Cache::remember(
                "kizeo_form_field_definitions_{$formId}",
                3600,
                fn () => $this->kizeo->rawGet("forms/{$formId}", 20)['form']['fields'] ?? []
            );
        } catch (\Throwable $e) {
            Log::warning('Kizeo Automation: no se pudo obtener definición del formulario', [
                'formId' => $formId,
                'error' => $e->getMessage(),
            ]);

            $this->formFieldDefinitions[$formId] = [];
        }

        return $this->formFieldDefinitions[$formId];
    }

    private function advancedListLabels(string $listId, bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            unset($this->advancedListLabels[$listId]);
        }

        if (array_key_exists($listId, $this->advancedListLabels)) {
            return $this->advancedListLabels[$listId];
        }

        try {
            $items = $this->kizeo->getListItems($listId, $forceRefresh);
            $this->advancedListLabels[$listId] = collect($items)
                ->mapWithKeys(fn ($item) => [(string) ($item['id'] ?? '') => (string) ($item['label'] ?? '')])
                ->filter(fn ($label, $id) => $id !== '' && $label !== '')
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Kizeo Automation: no se pudo resolver lista avanzada', [
                'listId' => $listId,
                'error' => $e->getMessage(),
            ]);

            $this->advancedListLabels[$listId] = [];
        }

        return $this->advancedListLabels[$listId];
    }

    private function matchesConditions(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? 'equals';
            $expected = (string) ($condition['value'] ?? '');

            if (!$field) {
                continue;
            }

            $actual = (string) ($context[$field] ?? $context[Str::slug($field, '_')] ?? '');

            if (!$this->matches($actual, $operator, $expected)) {
                return false;
            }
        }

        return true;
    }

    private function matches(string $actual, string $operator, string $expected): bool
    {
        $actualLower = Str::lower(trim($actual));
        $expectedLower = Str::lower(trim($expected));

        return match ($operator) {
            'not_equals' => $actualLower !== $expectedLower,
            'contains' => str_contains($actualLower, $expectedLower),
            'not_contains' => !str_contains($actualLower, $expectedLower),
            'starts_with' => str_starts_with($actualLower, $expectedLower),
            'ends_with' => str_ends_with($actualLower, $expectedLower),
            'empty' => $actualLower === '',
            'not_empty' => $actualLower !== '',
            'in' => in_array($actualLower, array_map(fn ($item) => Str::lower(trim($item)), explode(',', $expected)), true),
            default => $actualLower === $expectedLower,
        };
    }

    private function renderTemplate(string $template, array $context): string
    {
        return preg_replace_callback('/\{([A-Za-z0-9_\-.]+)\}/', function ($matches) use ($context) {
            $key = $matches[1];
            return $this->cleanPathSegment((string) ($context[$key] ?? $context[Str::slug($key, '_')] ?? 'Sin especificar'));
        }, $template) ?? $template;
    }

    private function ensurePdfFilename(string $filename): string
    {
        $filename = preg_replace('/\.pdf$/i', '', $filename ?: 'documento') ?? 'documento';
        $filename = $this->cleanPathSegment($filename, 176);

        return $filename . '.pdf';
    }

    private function cleanSharePointPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $segments = array_map(
            fn (string $segment) => $this->cleanPathSegment($segment),
            explode('/', $path)
        );

        return trim(implode('/', $segments), '/');
    }

    private function cleanPathSegment(?string $value, int $maxLength = 120): string
    {
        $value = (string) ($value ?? '');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|', '#', '%', '{', '}', '~', '&'], '_', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B.");

        return substr($value !== '' ? $value : 'Sin especificar', 0, $maxLength);
    }

    private function compactContext(array $context): array
    {
        return collect($context)
            ->only(['form_id', 'data_id', 'record_number', 'form_unique_id', 'form_name', 'fecha', 'anio', 'mes', 'mes_nombre'])
            ->all();
    }
}
