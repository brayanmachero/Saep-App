<?php

namespace App\Console\Commands;

use App\Models\KizeoCharlaTracking;
use App\Services\KizeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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

            if (empty($records)) {
                $this->warn('No se encontraron registros en Kizeo.');
                return self::SUCCESS;
            }

            $this->info('Registros obtenidos de Kizeo: ' . count($records));

            // 2. Procesar y upsert cada registro
            $created   = 0;
            $updated   = 0;
            $completed = 0;
            $pending   = 0;

            foreach ($records as $record) {
                $dataId       = (string) ($record['_id'] ?? '');
                $userId       = (string) ($record['_user_id'] ?? '');
                $userName     = $record['_user_name'] ?? "Usuario-{$userId}";
                $createTime   = $record['_create_time'] ?? null;
                $answerTime   = $record['_answer_time'] ?? '';
                $updateTime   = $record['_update_time'] ?? null;
                $direction    = $record['_direction'] ?? null;
                $recipientId  = $record['_recipient_id'] ?? null;
                $recipientNm  = $record['_recipient_name'] ?? null;
                $originAnswer = $record['_origin_answer'] ?? null;
                $history      = $record['_history'] ?? '';
                $pullTime     = $record['_pull_time'] ?? '';

                if (!$dataId) continue;

                // === Determinar estado ===
                // Completado: tiene _answer_time con valor real
                // Pendiente: _answer_time vacío (fue transferido/asignado pero no completado)
                $hasAnswer = !empty(trim($answerTime));
                $estado = $hasAnswer ? 'completado' : 'pendiente';

                if ($estado === 'completado') {
                    $completed++;
                } else {
                    $pending++;
                }

                // Calcular semana/año
                $fechaRef = $createTime ?? $updateTime;
                $carbon   = $fechaRef ? Carbon::parse($fechaRef) : now();
                $semana   = (int) $carbon->isoWeek();
                $anio     = (int) $carbon->isoWeekYear();

                // Determinar asignado_a (destinatario del transfer/push)
                $asignadoA   = $recipientNm ?: null;
                $asignadoAId = $recipientId ? (string) $recipientId : null;

                // Título de la charla
                $titulo = $record['titulo_actividad'] ?? '';
                $lugar  = $record['lugar_de_la_capacitacion'] ?? '';

                $existing = KizeoCharlaTracking::where('kizeo_data_id', $dataId)->first();

                $data = [
                    'kizeo_form_id'   => $formId,
                    'asignado_por'    => $userName,
                    'asignado_por_id' => $userId,
                    'asignado_a'      => $asignadoA,
                    'asignado_a_id'   => $asignadoAId,
                    'estado'          => $estado,
                    'fecha_creacion'  => $createTime,
                    'fecha_respuesta' => $hasAnswer ? $answerTime : null,
                    'semana'          => $semana,
                    'anio'            => $anio,
                    'metadata'        => [
                        'direction'     => $direction,
                        'origin_answer' => $originAnswer,
                        'history'       => $history,
                        'pull_time'     => $pullTime,
                        'titulo'        => $titulo,
                        'lugar'         => $lugar,
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

            $this->info("Sincronización completada:");
            $this->info("  Nuevos:      {$created}");
            $this->info("  Actualizados: {$updated}");
            $this->info("  Completados:  {$completed}");
            $this->info("  Pendientes:   {$pending}");

            Log::info('kizeo:sync-charla-tracking completado', [
                'form_id'   => $formId,
                'total'     => count($records),
                'created'   => $created,
                'updated'   => $updated,
                'completed' => $completed,
                'pending'   => $pending,
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

    /**
     * Obtiene TODOS los registros usando data/advanced con paginación.
     * Incluye campos _answer_time, _direction, _recipient_id que data/all no tiene.
     */
    private function fetchAllAdvanced(KizeoService $kizeo, string $formId, string $desde): array
    {
        $allRecords = [];
        $limit = 500;
        $offset = 0;

        do {
            $response = $kizeo->rawPost("forms/{$formId}/data/advanced", [
                'filters' => [
                    ['type' => 'OR', 'col' => '_create_time', 'op' => 'ge', 'val' => $desde],
                ],
                'order' => [['col' => '_create_time', 'type' => 'desc']],
                'limit' => $limit,
                'offset' => $offset,
            ]);

            $data = $response['data'] ?? [];
            $count = count($data);
            $allRecords = array_merge($allRecords, $data);
            $offset += $limit;

            $this->line("  Batch: +{$count} registros (offset {$offset})...");
        } while ($count >= $limit);

        return $allRecords;
    }
}
