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
        [
            'bucket' => 'Completado',
            'title' => '[Plataforma] Base operativa, administración y permisos de SAEP',
            'description' => 'Consolidación de la plataforma base: usuarios, roles, permisos por módulo, configuración, panel principal y navegación operativa.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Formularios] Gestión, categorías y exportación de respuestas',
            'description' => 'Administración de formularios y categorías, acceso de usuarios y normalización de exportaciones de respuestas.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Kanban] Tareas, adjuntos, alertas y recurrencias',
            'description' => 'Tablero interno con vista de adjuntos, alertas de vencimiento diarias (08:15) y creación de tareas recurrentes diarias (07:00).',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[SST / Kizeo] Automatizaciones documentales hacia SharePoint',
            'description' => 'Webhooks y automatizaciones para charlas, observaciones, reglas de oro, retroalimentaciones, inspecciones, visitas, accidentes, declaraciones y reuniones CPHS. Incluye creación de carpetas, historial de ejecuciones y reintentos.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[SST] Seguimiento de charlas Kizeo y reporte semanal',
            'description' => 'Sincronización de seguimiento de charlas cada seis horas, limpieza de registros huérfanos y reporte semanal programado los lunes a las 08:00.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[SST / STOP CCU] Tablero y reportes desde Google Sheets',
            'description' => 'Sincronización horaria desde Google Sheets, filtros por trabajador y período, reportes semanales y mensuales, y correcciones de rendimiento y exportación.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[SST / Observaciones CCU] Dashboard Kizeo, filtros y analítica',
            'description' => 'Dashboard de observaciones de conducta CCU con actualización desde Kizeo cada 30 minutos, filtros por trabajador y turno, gráficos de distribución y clasificación operativa.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[SST / Inspecciones PDR] Dashboard preventivo conectado a Kizeo',
            'description' => 'Panel de inspecciones preventivas PDR con sincronización automática desde Kizeo cada 30 minutos y reporte analítico para seguimiento SST.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[SST / Carta Gantt] Programas, equipos y seguimiento operativo',
            'description' => 'Asignación de equipos por programa, auditoría de acciones, acciones rápidas para asignados, filtros, vista Mis tareas, recordatorios y resumen ejecutivo.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[SST / Ley Karin] Formulario ampliado y dashboard de gestión',
            'description' => 'Flujo para denunciante, denunciado y terceros; panel de indicadores con gráficos, filtros y ajustes de navegación.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Talana] Sincronización de personal, contratos, turnos y ausencias',
            'description' => 'Sincronización diaria de Talana, refrescos de contratos dos veces al día, ausencias aprobadas, asignación persona-turno y base para distinguir descansos de ausencias reales.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Talana] Alertas de contratos y análisis de asistencia',
            'description' => 'Alertas de contratos próximos a vencer y vencidos activos, detección de marcaciones incompletas, ausencia de marcas y alertas con evidencia verificada.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Talana ↔ Kizeo] Publicación de personal vigente para CDD',
            'description' => 'Publicación programada del padrón vigente hacia la lista avanzada Kizeo, con conciliación por RUT, mapeo de centros, reintentos ante límites y tres cortes diarios.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Contratación] Portal público, carga documental y ficha consolidada',
            'description' => 'Formulario público con autenticación, validación de documentos, protección de cargas móviles, soporte HEIC y generación de ficha PDF consolidada.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Contratación] Cierre diario RRHH y enlaces a SharePoint',
            'description' => 'Correo de cierre diario a RRHH con control anti-duplicados, enlaces a documentos de SharePoint y registro de envíos.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Reclutamiento] Campañas WhatsApp, consentimiento y webhook oficial',
            'description' => 'Gestión de campañas y bandeja de reclutamiento, webhook oficial, plantillas, consentimiento verificable y despacho programado de campañas aprobadas.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Inventario / Kizeo] Sincronización de entregas, catálogo y aplicación controlada',
            'description' => 'Sincronización de entregas EPP y catálogo cada 30 minutos, aplicación masiva, revisión de diferencias, reversos, asociación de tallas y descuento automático solo para entregas nuevas.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Inventario] Catálogo, variantes, maestros y costos de referencia',
            'description' => 'Administración de productos y tallas activas/inactivas, maestros de ubicaciones y proveedores, alertas de stock, stock inicial, importaciones y trazabilidad de costos.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Inventario] Resumen, trazabilidad e historiales consultables',
            'description' => 'Resumen filtrable por período, flujo diario, detalle por producto o talla, historial de ingresos y movimientos con búsqueda y paginación.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Reservas de vehículos] Portal, disponibilidad, agenda y actas Kizeo',
            'description' => 'Reservas operativas con disponibilidad, agenda Outlook publicada, confirmación, cancelación, márgenes, avisos a bodega y preparación de actas Kizeo con patente y conductor precargados.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Privacidad y cumplimiento] ARCO, retención, términos y marca SAEP',
            'description' => 'Rutas públicas ARCO, gestión de solicitudes, registro de tratamientos, matriz de retención, términos de servicio, avisos de privacidad y ajustes de identidad visual.',
            'completed' => true,
        ],
        [
            'bucket' => 'Completado',
            'title' => '[Planner] Conector Microsoft Graph para seguimiento del proyecto',
            'description' => 'Integración directa y acotada al plan SAEP – Operación y Mejoras, con estructura idempotente y carga inicial de avances y pendientes.',
            'completed' => true,
        ],
        [
            'bucket' => 'Validación',
            'title' => '[Comercial / Cotizaciones] Ejecutar validación integral del módulo',
            'description' => 'Pruebas de clientes y centros de costo, creación y edición de cotizaciones, tarifas, estados simplificados, plantillas de importación, mantenedor y reportes. Pendiente de pruebas formales durante la semana.',
        ],
        [
            'bucket' => 'Validación',
            'title' => '[Operaciones / Contenedores] Ejecutar validación integral del flujo',
            'description' => 'Pruebas de carga rápida, tarifas, dotación Talana, FACT, equipos, evidencia fotográfica, bandeja de revisión, permisos, edición lateral y acciones masivas. Pendiente de pruebas formales durante la semana.',
        ],
        [
            'bucket' => 'Validación',
            'title' => '[Reservas de vehículos] Validar ciclo operativo completo',
            'description' => 'Comprobar reserva, disponibilidad, agenda, confirmación, aviso a bodega, cancelación, calendario Outlook y vínculo de actas Kizeo en una operación real.',
        ],
        [
            'bucket' => 'Validación',
            'title' => '[SST / Kizeo] Confirmar continuidad de sincronizaciones y archivos',
            'description' => 'Monitorear que las sincronizaciones de charlas, observaciones, inspecciones y cargas documentales hacia SharePoint se mantengan correctas tras sus próximas ejecuciones programadas.',
        ],
        [
            'bucket' => 'Validación',
            'title' => '[Reclutamiento] Validar campaña WhatsApp y ciclo público de postulación',
            'description' => 'Realizar una prueba controlada de campaña aprobada, consentimiento, webhook y postulación con carga documental para confirmar el flujo completo de reclutamiento.',
        ],
        [
            'bucket' => 'En curso',
            'title' => '[Operación] Consolidar el registro histórico de avances en Planner',
            'description' => 'Mantener este plan como fuente de seguimiento: cada mejora relevante, automatización, validación o incidente debe registrarse y moverse según su estado.',
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
