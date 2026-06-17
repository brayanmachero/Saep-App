<?php

namespace App\Services;

use App\Models\KizeoAutomationRule;
use App\Models\KizeoAutomationRun;
use App\Models\WebhookLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KizeoAutomationService
{
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

            $run = KizeoAutomationRun::create([
                'kizeo_automation_rule_id' => $rule->id,
                'form_id' => $formId,
                'data_id' => $dataId,
                'status' => 'processing',
                'context' => $this->compactContext($context),
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
                    'resumen' => "Automatización {$rule->name}",
                    'archivo' => $upload['filename'],
                    'sharepoint_path' => $upload['path'],
                    'email_enviado' => false,
                    'destinatarios' => [],
                    'metadata' => [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name,
                        'form_name' => $context['form_name'] ?? null,
                    ],
                    'ip' => $ip,
                ]);

                $result['succeeded']++;
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
                    'resumen' => "Error automatización {$rule->name}",
                    'error_message' => $e->getMessage(),
                    'metadata' => [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name,
                        'form_name' => $context['form_name'] ?? null,
                    ],
                    'ip' => $ip,
                ]);

                Log::error('Kizeo Automation: error procesando regla', [
                    'rule_id' => $rule->id,
                    'formId' => $formId,
                    'dataId' => $dataId,
                    'error' => $e->getMessage(),
                ]);

                $result['failed']++;
                $result['errors'][] = $e->getMessage();
            }
        }

        return $result;
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
        $remotePath = $this->cleanSharePointPath(trim($baseFolder . '/' . $folder . '/' . $filename, '/'));

        $uploaded = $rule->sharepoint_site
            ? $this->oneDrive->uploadFileToSite($rule->sharepoint_site, $pdfContent, $remotePath, 'application/pdf')
            : $this->oneDrive->uploadFile($pdfContent, $remotePath, 'application/pdf', true);

        if (!$uploaded) {
            $lastError = $this->oneDrive->getLastError();
            throw new \RuntimeException($lastError['message'] ?? 'SharePoint no confirmó la subida del archivo');
        }

        return ['filename' => basename($remotePath), 'path' => $remotePath];
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
        $context = $this->flattenFields($fields);

        $formName = $record['form_name']
            ?? $payload['data']['form_name']
            ?? $payload['form_name']
            ?? "Formulario {$formId}";

        $context = array_merge($context, [
            'form_id' => $formId,
            'data_id' => $dataId,
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

    private function flattenFields(array $fields): array
    {
        $flat = [];

        foreach ($fields as $key => $field) {
            $value = $this->extractValue($field);
            $flat[(string) $key] = $value;

            if (is_array($field) && !empty($field['label'])) {
                $flat[Str::slug((string) $field['label'], '_')] = $value;
            }
        }

        return $flat;
    }

    private function extractValue(mixed $value): string
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

        if (array_key_exists('result', $value)) {
            return $this->extractValue($value['result']);
        }

        if (array_key_exists('value', $value)) {
            return $this->extractValue($value['value']);
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
        $filename = $this->cleanPathSegment($filename ?: 'documento.pdf', 180);

        return Str::endsWith(Str::lower($filename), '.pdf') ? $filename : $filename . '.pdf';
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
            ->only(['form_id', 'data_id', 'form_name', 'fecha', 'anio', 'mes', 'mes_nombre'])
            ->all();
    }
}
