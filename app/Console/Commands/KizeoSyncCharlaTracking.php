<?php

namespace App\Console\Commands;

use App\Models\KizeoCharlaTracking;
use App\Models\Configuracion;
use App\Services\KizeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class KizeoSyncCharlaTracking extends Command
{
    protected $signature = 'kizeo:sync-charla-tracking
                            {--months=6 : Meses de historial a sincronizar}';

    protected $description = 'Sincroniza registros de Charlas de Seguridad desde Kizeo API para seguimiento de cumplimiento';

    public function handle(KizeoService $kizeo): int
    {
        $formId = config('services.kizeo.charla_form_id');

        if (!$formId) {
            $this->error('KIZEO_CHARLA_FORM_ID no configurado en .env');
            return self::FAILURE;
        }

        $months = (int) $this->option('months');

        $this->info("Sincronizando charlas (form {$formId}) — últimos {$months} meses...");

        try {
            // 1. Obtener registros desde Kizeo usando data/advanced
            //    que incluye _answer_time, _direction, _recipient_id, _recipient_name
            $desde = now()->subMonths($months)->startOfDay()->format('Y-m-d H:i:s');
            $records = $this->fetchAllAdvanced($kizeo, $formId, $desde);
            $records = $this->mergeCanonicalDates($kizeo, $formId, $desde, $records);

            if (empty($records)) {
                $this->warn('No se encontraron registros en Kizeo.');
                return self::SUCCESS;
            }

            $this->info('Registros obtenidos de Kizeo: ' . count($records));

            // 2. Procesar y upsert cada registro
            $created     = 0;
            $updated     = 0;
            $completed   = 0;
            $pending     = 0;
            $transferred = 0;

            foreach ($records as $record) {
                $dataId       = (string) ($record['_id'] ?? '');
                $userId       = (string) ($record['_user_id'] ?? '');
                $userName     = $record['_user_name'] ?? "Usuario-{$userId}";
                $createTime   = $record['_create_time'] ?? null;
                $answerTime   = $record['_answer_time'] ?? '';
                $registrationTime = $record['_registration_time'] ?? null;
                $updateTime   = $record['_update_time'] ?? null;
                $direction    = $record['_direction'] ?? null;
                $recipientId  = $record['_recipient_id'] ?? null;
                $recipientNm  = $record['_recipient_name'] ?? null;
                $originAnswer = $record['_origin_answer'] ?? null;
                $history      = $record['_history'] ?? '';
                $pullTime     = $record['_pull_time'] ?? '';

                if (!$dataId) continue;

                // === Determinar estado y estatus Kizeo ===
                $hasAnswer    = !empty(trim($answerTime));
                $isTransfer   = str_contains($history, 'Transferido por');
                $hasRecipient = !empty($recipientId);

                // Estatus Kizeo (refleja lo que muestra Kizeo Forms UI)
                if ($hasAnswer && !$isTransfer) {
                    $estatusKizeo = 'registrado';   // ✓ Completado directamente
                    $estado = 'completado';
                } elseif ($hasAnswer && $isTransfer) {
                    $estatusKizeo = 'terminado';     // ✓ Fue transferido y completado
                    $estado = 'completado';
                } elseif (!$hasAnswer && $isTransfer && !empty(trim($pullTime))) {
                    $estatusKizeo = 'recuperado';    // Transferido y recuperado al dispositivo
                    $estado = 'transferido';
                } elseif (!$hasAnswer && ($isTransfer || $hasRecipient)) {
                    $estatusKizeo = 'transferido';   // 🔄 Transferido, pendiente
                    $estado = 'transferido';
                } else {
                    $estatusKizeo = $hasAnswer ? 'registrado' : 'pendiente';
                    $estado = $hasAnswer ? 'completado' : 'pendiente';
                }

                if ($estado === 'completado') {
                    $completed++;
                } elseif ($estado === 'transferido') {
                    $transferred++;
                } else {
                    $pending++;
                }

                // Calcular semana/año
                $fechaRef = $createTime ?? $updateTime;
                $carbon   = $fechaRef ? Carbon::parse($fechaRef) : now();
                $semana   = (int) $carbon->isoWeek();
                $anio     = (int) $carbon->isoWeekYear();

                // Determinar asignado_a (destinatario del transfer)
                $asignadoA   = $recipientNm ?: null;
                $asignadoAId = $recipientId ? (string) $recipientId : null;

                // El destinatario actual debe coincidir con la columna de Kizeo.
                // La atribución histórica de una respuesta no reemplaza un destinatario vacío.
                $destinatarioHistorico = null;
                if ($estado === 'completado' && $isTransfer && !$asignadoA) {
                    $destinatarioHistorico = $userName;
                    // Extraer remitente original: "Transferido por [Nombre] a [Destino]..."
                    if (preg_match('/Transferido por (.+?) a /u', $history, $hm)) {
                        $userName = trim($hm[1]);
                    }
                }

                // Fecha de asignación (cuando se transfirió)
                $fechaAsignacion = null;
                if ($isTransfer && preg_match('/el (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $history, $m)) {
                    $fechaAsignacion = $m[1];
                }

                // Título y actividad (campos reales del formulario Kizeo)
                $descripcion = $record['descripcion_'] ?? '';
                $actividad = $record['actividad_de_'] ?? '';
                $summaryTitle = $record['_summary_title'] ?? '';
                // Usar summary_title (corto) si existe, sino recortar descripcion
                $titulo = $summaryTitle ?: ($descripcion ? mb_substr($descripcion, 0, 120) : '');
                if ($actividad && $titulo) {
                    $titulo = "{$actividad}: {$titulo}";
                } elseif ($actividad) {
                    $titulo = $actividad;
                }
                $lugar  = $record['antecedentes'] ?? '';

                $existing = KizeoCharlaTracking::where('kizeo_data_id', $dataId)->first();

                $data = [
                    'kizeo_form_id'    => $formId,
                    'asignado_por'     => $userName,
                    'asignado_por_id'  => $userId,
                    'asignado_a'       => $asignadoA,
                    'asignado_a_id'    => $asignadoAId,
                    'titulo_actividad' => $titulo ? mb_substr($titulo, 0, 295) : null,
                    'lugar'            => $lugar ? mb_substr($lugar, 0, 195) : null,
                    'estado'           => $estado,
                    'estatus_kizeo'    => $estatusKizeo,
                    'fecha_creacion'   => $createTime,
                    'fecha_registro_kizeo' => $registrationTime,
                    'fecha_asignacion' => $fechaAsignacion,
                    'fecha_respuesta'  => $hasAnswer ? $answerTime : null,
                    'origin_answer'    => $originAnswer,
                    'direction'        => $direction,
                    'semana'           => $semana,
                    'anio'             => $anio,
                    'metadata'         => [
                        'history'   => $history,
                        'pull_time' => $pullTime,
                        'destinatario_historico' => $destinatarioHistorico,
                    ],
                ];

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    KizeoCharlaTracking::create(array_merge($data, [
                        'kizeo_data_id' => $dataId,
                    ]));
                    $created++;
                }
            }

            // === Eliminar registros huérfanos (existen en BD pero ya no en Kizeo) ===
            // Kizeo puede eliminar o reasignar registros; sin este paso los datos quedan
            // "fantasma" en SAEP aunque el usuario ya no los vea en Kizeo.
            $kizeoIds = array_map(fn($r) => (string) ($r['_id'] ?? ''), $records);
            $kizeoIds = array_filter($kizeoIds); // quitar vacíos

            $deleted = KizeoCharlaTracking::where('kizeo_form_id', $formId)
                ->where('fecha_creacion', '>=', $desde)
                ->whereNotIn('kizeo_data_id', $kizeoIds)
                ->delete();

            if ($deleted > 0) {
                $this->warn("  Eliminados (ya no existen en Kizeo): {$deleted}");
                Log::info('kizeo:sync-charla-tracking — registros huérfanos eliminados', [
                    'form_id' => $formId,
                    'deleted' => $deleted,
                ]);
            }

            $this->info("Sincronización completada:");
            $this->info("  Nuevos:       {$created}");
            $this->info("  Actualizados: {$updated}");
            $this->info("  Eliminados:   {$deleted}");
            $this->info("  Completados:  {$completed}");
            $this->info("  Transferidos: {$transferred}");
            $this->info("  Pendientes:   {$pending}");

            // Guardar marca de tiempo real de la sincronización
            Cache::put('charla_tracking_last_sync', now()->toDateTimeString(), now()->addDays(30));
            Configuracion::updateOrCreate(['clave' => 'charla_tracking_last_sync'], [
                'valor' => now()->toDateTimeString(), 'tipo' => 'TEXT',
                'categoria' => 'sistema', 'editable' => false,
                'descripcion' => 'Última sincronización completa de charlas Kizeo',
            ]);

            Log::info('kizeo:sync-charla-tracking completado', [
                'form_id'     => $formId,
                'total'       => count($records),
                'created'     => $created,
                'updated'     => $updated,
                'completed'   => $completed,
                'transferred' => $transferred,
                'pending'     => $pending,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('kizeo:sync-charla-tracking falló', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /** Obtiene los detalles por mes; Kizeo agota el tiempo con el rango completo. */
    private function fetchAllAdvanced(KizeoService $kizeo, string $formId, string $desde): array
    {
        $allRecords = [];
        $start = Carbon::parse($desde);
        $end = now()->endOfDay();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $monthEnd = $cursor->copy()->endOfMonth()->min($end);
            $response = $kizeo->rawPost("forms/{$formId}/data/advanced", [
                'filters' => [
                    [
                        'type'     => 'simple',
                        'field'    => '_create_time',
                        'operator' => '>=',
                        'val'      => $cursor->format('Y-m-d H:i:s'),
                    ],
                    [
                        'type'     => 'simple',
                        'field'    => '_create_time',
                        'operator' => '<=',
                        'val'      => $monthEnd->format('Y-m-d H:i:s'),
                    ],
                ],
                'order' => [['col' => '_create_time', 'type' => 'desc']],
                'limit' => 500,
                'offset' => 0,
            ], 60);

            $data = $response['data'] ?? null;
            if (!is_array($data)) {
                throw new \RuntimeException('Kizeo devolvió un bloque de charlas inválido.');
            }
            $expected = (int) ($response['recordsFiltered'] ?? count($data));
            if ($expected !== count($data)) {
                throw new \RuntimeException("Kizeo devolvió un bloque incompleto: {$expected} esperados, " . count($data) . ' recibidos.');
            }
            foreach ($data as $record) {
                if (isset($record['_id'])) {
                    $allRecords[(string) $record['_id']] = $record;
                }
            }
            $this->line("  {$cursor->format('Y-m')}: " . count($data) . ' registros');
            $cursor = $monthEnd->copy()->addSecond();
        }

        return array_values($allRecords);
    }

    /** data/all contiene la fecha de registro del histórico, incluso para transferidos pendientes. */
    private function mergeCanonicalDates(KizeoService $kizeo, string $formId, string $desde, array $records): array
    {
        $response = $kizeo->rawGet("forms/{$formId}/data/all", 90);
        $summaries = $response['data'] ?? null;
        if (!is_array($summaries)) {
            throw new \RuntimeException('Kizeo devolvió un histórico de charlas inválido.');
        }

        $recent = [];
        foreach ($summaries as $summary) {
            $id = (string) ($summary['id'] ?? '');
            if ($id !== '' && ($summary['create_time'] ?? '') >= $desde) {
                $recent[$id] = $summary;
            }
        }

        $advancedIds = [];
        foreach ($records as $record) {
            $advancedIds[(string) ($record['_id'] ?? '')] = true;
        }
        if (array_diff_key($recent, $advancedIds) || array_diff_key($advancedIds, $recent)) {
            throw new \RuntimeException('Kizeo devolvió conjuntos distintos en el histórico y los detalles de charlas.');
        }

        foreach ($records as &$record) {
            $summary = $recent[(string) $record['_id']];
            $record['_create_time'] = $summary['create_time'];
            $record['_registration_time'] = $summary['answer_time'] ?? null;
            $record['_direction'] = $summary['direction'] ?? ($record['_direction'] ?? null);
        }
        unset($record);

        return $records;
    }
}
