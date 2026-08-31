<?php

namespace App\Console\Commands;

use App\Services\PlannerService;
use Illuminate\Console\Command;
use RuntimeException;

class InitializeSaepPlannerProject extends Command
{
    protected $signature = 'planner:initialize-saep-project
        {--dry-run : Muestra los cambios sin escribir en Planner}';

    protected $description = 'Crea la estructura y los pendientes iniciales del plan SAEP en Microsoft Planner.';

    /** @var array<int, string> */
    private const BUCKETS = [
        'Pendientes',
        'En curso',
        'Validación',
        'Completado',
        'Incidentes',
    ];

    /** @var array<int, array{bucket: string, title: string, description: string, completed?: bool}> */
    private const INITIAL_TASKS = [
        [
            'bucket' => 'Pendientes',
            'title' => 'Reforzar reintentos de Kizeo ante demoras externas',
            'description' => 'Incorporar reintentos y espera progresiva para que las demoras temporales de Kizeo no afecten la sincronización operativa.',
        ],
        [
            'bucket' => 'Validación',
            'title' => 'Validar informes diarios de asistencia durante la primera semana',
            'description' => 'Confirmar recepción, destinatarios, adjuntos y formato de los reportes de LTS Quilicura y LTS Peñón.',
        ],
        [
            'bucket' => 'Validación',
            'title' => 'Monitorear descuentos automáticos y sincronización de entregas Kizeo',
            'description' => 'Revisar que las entregas nuevas sigan descontando stock de forma trazable desde la línea base operativa.',
        ],
        [
            'bucket' => 'Completado',
            'title' => 'Dejar operativo el inventario y la trazabilidad de bodega',
            'description' => 'Stock base, ingresos, salidas, catálogo, trazabilidad y revisión de entregas Kizeo disponibles en producción.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => 'Ordenar repostulaciones y documentos en SharePoint',
            'description' => 'La carpeta principal conserva la ficha más reciente y las postulaciones previas quedan ordenadas en historial.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => 'Configurar reportes diarios Talana con remitente oficial',
            'description' => 'Reportes de LTS Quilicura y LTS Peñón configurados con destinatarios oficiales y envío desde notificaciones@saep.cl.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => 'Eliminar ejecución duplicada del programador SAEP',
            'description' => 'Se retiró la entrada duplicada del scheduler en el servidor y se verificó una ejecución por minuto.',
            'completed' => true,
        ],
    ];

    public function handle(PlannerService $planner): int
    {
        if (! $planner->isConfigured()) {
            $this->error('Planner no está configurado. Define MSGRAPH_PLANNER_PLAN_ID antes de ejecutar este comando.');

            return self::FAILURE;
        }

        try {
            $plan = $planner->plan();
            $this->info('Plan conectado: '.($plan['title'] ?? $planner->planId()));

            $buckets = $planner->buckets();
            $tasks = $planner->tasks();
            $bucketMap = $this->bucketMap($buckets);
            $existingTaskTitles = $this->taskTitles($tasks);
            $renamedDefaultBucket = false;

            if (! isset($bucketMap[$this->normalize('Pendientes')]) && count($buckets) === 1 && count($tasks) === 0) {
                $defaultBucket = $buckets[0];
                $this->line("Columna inicial: “{$defaultBucket['name']}” → “Pendientes”.");

                if (! $this->option('dry-run')) {
                    $planner->renameBucket($defaultBucket, 'Pendientes');
                    $bucketMap[$this->normalize('Pendientes')] = array_merge($defaultBucket, ['name' => 'Pendientes']);
                }

                $renamedDefaultBucket = true;
            }

            foreach (self::BUCKETS as $bucketName) {
                $key = $this->normalize($bucketName);
                if (isset($bucketMap[$key]) || ($renamedDefaultBucket && $bucketName === 'Pendientes')) {
                    continue;
                }

                $this->line("Crear columna: {$bucketName}");
                if (! $this->option('dry-run')) {
                    $bucketMap[$key] = $planner->createBucket($bucketName);
                }
            }

            foreach (self::INITIAL_TASKS as $task) {
                $titleKey = $this->normalize($task['title']);
                if (isset($existingTaskTitles[$titleKey])) {
                    $this->line("Conservada: {$task['title']}");

                    continue;
                }

                $this->line("Crear tarea [{$task['bucket']}]: {$task['title']}");
                if (! $this->option('dry-run')) {
                    $bucket = $bucketMap[$this->normalize($task['bucket'])] ?? null;
                    if (! $bucket || empty($bucket['id'])) {
                        throw new RuntimeException("No fue posible resolver la columna {$task['bucket']} en Planner.");
                    }

                    $planner->createTask(
                        bucketId: (string) $bucket['id'],
                        title: $task['title'],
                        description: $task['description'],
                        completed: (bool) ($task['completed'] ?? false),
                    );
                }
            }

            $this->info($this->option('dry-run')
                ? 'Validación terminada. No se realizaron cambios en Planner.'
                : 'Plan SAEP inicializado correctamente en Microsoft Planner.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @param array<int, array<string, mixed>> $buckets
     * @return array<string, array<string, mixed>>
     */
    private function bucketMap(array $buckets): array
    {
        $map = [];
        foreach ($buckets as $bucket) {
            $name = (string) ($bucket['name'] ?? '');
            if ($name !== '') {
                $map[$this->normalize($name)] = $bucket;
            }
        }

        return $map;
    }

    /** @param array<int, array<string, mixed>> $tasks
     * @return array<string, true>
     */
    private function taskTitles(array $tasks): array
    {
        $titles = [];
        foreach ($tasks as $task) {
            $title = (string) ($task['title'] ?? '');
            if ($title !== '') {
                $titles[$this->normalize($title)] = true;
            }
        }

        return $titles;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
