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
                            {--months=6 : Meses de historial a sincronizar}
                            {--force : Forzar refresh de caché Kizeo}';

    protected $description = 'Sincroniza registros de Charlas de Seguridad desde Kizeo API para seguimiento de cumplimiento';

    public function handle(KizeoService $kizeo): int
    {
        $formId = config('services.kizeo.charla_form_id');

        if (!$formId) {
            $this->error('KIZEO_CHARLA_FORM_ID no configurado en .env');
            return self::FAILURE;
        }

        $months = (int) $this->option('months');
        $force  = (bool) $this->option('force');

        $this->info("Sincronizando charlas (form {$formId}) — últimos {$months} meses...");

        try {
            // 1. Obtener registros desde Kizeo
            $records = $kizeo->getFormData($formId, $force);
            $userDic = $kizeo->getUserDictionary();

            if (empty($records)) {
                $this->warn('No se encontraron registros en Kizeo.');
                return self::SUCCESS;
            }

            $this->info('Registros obtenidos de Kizeo: ' . count($records));

            // 2. Filtrar por rango de fechas
            $desde = now()->subMonths($months)->startOfDay()->toDateTimeString();
            $filtered = array_filter($records, function ($r) use ($desde) {
                $date = $r['update_time'] ?? $r['create_time'] ?? '';
                return $date >= $desde;
            });

            $this->info('Registros en período: ' . count($filtered));

            // 3. Procesar y upsert cada registro
            $created   = 0;
            $updated   = 0;
            $completed = 0;
            $pending   = 0;

            foreach ($filtered as $record) {
                $dataId      = (string) ($record['id'] ?? '');
                $userId      = (string) ($record['user_id'] ?? '');
                $userName    = $userDic[$userId] ?? ($record['user_name'] ?? null) ?? "Usuario-{$userId}";
                $createTime  = $record['create_time'] ?? null;
                $answerTime  = $record['answer_time'] ?? null;
                $updateTime  = $record['update_time'] ?? null;

                if (!$dataId) continue;

                // Determinar estado basado en answer_time
                $estado = (!empty($answerTime) && $answerTime !== 'null')
                    ? 'completado'
                    : 'pendiente';

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

                // Determinar asignado_a (destinatario)
                // Kizeo puede incluir update_user_id cuando hay transferencia
                $updateUserId   = (string) ($record['update_user_id'] ?? '');
                $updateUserName = $updateUserId ? ($userDic[$updateUserId] ?? null) : null;

                // Si update_user_id es distinto de user_id, puede ser el destinatario
                $asignadoA   = null;
                $asignadoAId = null;
                if ($updateUserId && $updateUserId !== $userId) {
                    $asignadoA   = $updateUserName;
                    $asignadoAId = $updateUserId;
                }

                $existing = KizeoCharlaTracking::where('kizeo_data_id', $dataId)->first();

                $data = [
                    'kizeo_form_id'   => $formId,
                    'asignado_por'    => $userName,
                    'asignado_por_id' => $userId,
                    'asignado_a'      => $asignadoA,
                    'asignado_a_id'   => $asignadoAId,
                    'estado'          => $estado,
                    'fecha_creacion'  => $createTime,
                    'fecha_respuesta' => ($estado === 'completado') ? ($answerTime ?? $updateTime) : null,
                    'semana'          => $semana,
                    'anio'            => $anio,
                    'metadata'        => [
                        'update_time'    => $updateTime,
                        'update_user_id' => $updateUserId ?: null,
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
                'total'     => count($filtered),
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
}
