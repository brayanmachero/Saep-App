<?php

namespace App\Console\Commands;

use App\Mail\CharlaTrackingReporteMail;
use App\Models\CharlaTrackingActionLog;
use App\Models\Configuracion;
use App\Models\KizeoCharlaTracking;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class KizeoCharlaWeeklyReport extends Command
{
    public const REPORT_FILTER_KEYS = [
        'desde',
        'hasta',
        'estado',
        'buscar',
    ];

    protected $signature = 'kizeo:charla-weekly-report
                            {--email= : Enviar a email específico (si no, envía a superadmins)}
                            {--sync : Ejecutar sincronización antes del reporte}
                            {--desde= : Fecha de inicio del reporte (YYYY-MM-DD)}
                            {--hasta= : Fecha de término del reporte (YYYY-MM-DD)}
                            {--estado=todos : Estado a incluir: todos, completado, pendiente o transferido}
                            {--buscar= : Texto de búsqueda aplicado al reporte}';

    protected $description = 'Genera y envía el reporte semanal de cumplimiento de Charlas de Seguridad';

    public function handle(): int
    {
        // Opcionalmente sincronizar primero
        if ($this->option('sync')) {
            $this->info('Ejecutando sincronización previa...');
            Artisan::call('kizeo:sync-charla-tracking', ['--months' => 3]);
            $this->info(Artisan::output());
        }

        $this->info('Generando reporte semanal de charlas...');

        $data = self::buildReportDataFromFilters([
            'desde' => $this->option('desde'),
            'hasta' => $this->option('hasta'),
            'estado' => $this->option('estado'),
            'buscar' => $this->option('buscar'),
        ]);

        $stats = $data['stats'];
        $pendientesPorUsuario = $data['pendientesPorUsuario'];
        $resumenSemanal = $data['resumenSemanal'];
        $topDestinatarios = $data['topDestinatarios'];
        $periodo = $data['periodo'];
        $filters = $data['filters'];
        $total = $stats['total'] ?? 0;
        $completadas = $stats['completadas'] ?? 0;
        $tasa = $stats['tasa_cumplimiento'] ?? 0;

        // Verificar si el reporte está activo
        if (!$this->option('email') && Configuracion::get('charla_report_activo') !== '1') {
            $this->info('Reporte semanal de charlas desactivado en configuración.');
            $this->recordAction('report_scheduled_send', 'skipped', 'Reporte de charlas omitido porque la automatización está desactivada.', $filters, [
                'periodo' => $periodo,
                'total_rows' => $total,
                'config_key' => 'charla_report_activo',
            ]);

            return self::SUCCESS;
        }

        // Destinatarios del email
        $email = $this->option('email');
        if ($email) {
            $destinatarios = [$email];
        } else {
            // Leer desde configuración de la plataforma
            $configEmails = Configuracion::get('charla_report_destinatarios', '');
            $destinatarios = collect(explode(',', $configEmails))
                ->map(fn ($e) => trim($e))
                ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values()
                ->toArray();
        }

        if (empty($destinatarios)) {
            $this->warn('No hay destinatarios configurados para el reporte.');
            $this->recordAction('report_scheduled_send', 'skipped', 'Reporte de charlas omitido por falta de destinatarios.', $filters, [
                'periodo' => $periodo,
                'total_rows' => $total,
            ]);

            return self::SUCCESS;
        }

        // Enviar email (crear mailable nuevo por cada destinatario para evitar acumulación de recipients)
        $sent = [];
        $failed = [];

        foreach ($destinatarios as $dest) {
            try {
                $mailable = new CharlaTrackingReporteMail(
                    $stats, $pendientesPorUsuario, $resumenSemanal,
                    $topDestinatarios, $periodo
                );
                Mail::to($dest)->send($mailable);
                User::where('email', $dest)->first()?->notify(new AppNotification(
                    'Reporte semanal charlas',
                    "Cumplimiento {$tasa}%",
                    'info'
                ));
                $this->info("Reporte enviado a: {$dest}");
                $sent[] = $dest;
            } catch (\Exception $e) {
                $this->error("Error enviando a {$dest}: {$e->getMessage()}");
                $failed[] = [
                    'email' => $dest,
                    'error' => $e->getMessage(),
                ];

                Log::error('charla-weekly-report: error enviando email', [
                    'email' => $dest,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $auditStatus = empty($failed) ? 'success' : (!empty($sent) ? 'partial' : 'failed');
        $this->recordAction('report_scheduled_send', $auditStatus, 'Ejecución de reporte de charlas desde comando.', $filters, [
            'periodo' => $periodo,
            'total_rows' => $total,
            'recipients' => $destinatarios,
            'sent' => $sent,
            'failed' => $failed,
            'sent_count' => count($sent),
            'failed_count' => count($failed),
        ]);

        $this->info("Reporte semanal generado — Tasa: {$tasa}% ({$completadas}/{$total})");

        Log::info('kizeo:charla-weekly-report enviado', [
            'stats'         => $stats,
            'destinatarios' => count($destinatarios),
            'periodo'       => $periodo,
        ]);

        return self::SUCCESS;
    }

    /**
     * Build the report data (used by preview route and email).
     */
    public static function buildReportData(): array
    {
        return self::buildReportDataFromFilters();
    }

    public static function buildReportDataFromFilters(array $filters = []): array
    {
        $filters = self::normalizeReportFilters($filters);
        $desde = Carbon::parse($filters['desde'])->startOfDay();
        $hasta = Carbon::parse($filters['hasta'])->endOfDay();
        $periodo = $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');

        $baseQuery = self::filteredQuery($filters);

        $total = (clone $baseQuery)->count();
        $completadas = (clone $baseQuery)->completados()->count();
        $transferidos = (clone $baseQuery)->transferidos()->count();
        $sinGestion = max(0, $total - $completadas - $transferidos);
        $tasa = $total > 0 ? round(($completadas / $total) * 100, 1) : 0;

        $promDias = (clone $baseQuery)
            ->pendientes()
            ->selectRaw('AVG(DATEDIFF(NOW(), COALESCE(fecha_asignacion, fecha_creacion))) as prom')
            ->value('prom');

        $stats = [
            'total'             => $total,
            'completadas'       => $completadas,
            'transferidos'      => $transferidos,
            'sin_gestion'       => $sinGestion,
            'tasa_cumplimiento' => $tasa,
            'prom_dias'         => $promDias ? round($promDias, 1) : 0,
        ];

        $pendientesPorUsuario = (clone $baseQuery)
            ->pendientes()
            ->selectRaw('COALESCE(asignado_a, asignado_por) as nombre, COUNT(*) as cantidad,
                         MIN(COALESCE(fecha_asignacion, fecha_creacion)) as fecha_min')
            ->groupBy('nombre')
            ->orderByDesc('cantidad')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $fechaMin = $row->fecha_min ? Carbon::parse($row->fecha_min) : null;
                return [
                    'nombre'             => $row->nombre ?? 'Desconocido',
                    'cantidad'           => $row->cantidad,
                    'fecha_mas_antigua'  => $fechaMin?->format('d/m/Y'),
                    'dias_max'           => $fechaMin ? (int) $fechaMin->diffInDays(now()) : 0,
                ];
            })
            ->toArray();

        $resumenSemanal = (clone $baseQuery)
            ->selectRaw("anio, semana,
                         COUNT(*) as total,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estado='transferido' THEN 1 ELSE 0 END) as transferidos")
            ->groupBy('anio', 'semana')
            ->orderBy('anio')
            ->orderBy('semana')
            ->get()
            ->map(function ($row) {
                $semStart = Carbon::now()->setISODate((int) $row->anio, (int) $row->semana)->startOfWeek();

                return [
                    'semana'       => (int) $row->semana,
                    'anio'         => (int) $row->anio,
                    'fecha'        => $semStart->format('d/m'),
                    'total'        => (int) $row->total,
                    'completadas'  => (int) $row->completadas,
                    'transferidos' => (int) $row->transferidos,
                    'tasa'         => $row->total > 0 ? round(($row->completadas / $row->total) * 100, 1) : 0,
                ];
            })
            ->toArray();

        $topDestinatarios = (clone $baseQuery)
            ->whereNotNull('asignado_a')
            ->selectRaw("asignado_a as nombre, COUNT(*) as total,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estado!='completado' THEN 1 ELSE 0 END) as pendientes,
                         SUM(CASE WHEN estatus_kizeo='recuperado' THEN 1 ELSE 0 END) as recuperadas,
                         SUM(CASE WHEN estatus_kizeo='transferido' THEN 1 ELSE 0 END) as sin_descargar")
            ->groupBy('asignado_a')
            ->orderByRaw("SUM(CASE WHEN estado!='completado' THEN 1 ELSE 0 END) DESC")
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'nombre'        => $r->nombre ?? 'Desconocido',
                'total'         => $r->total,
                'completadas'   => $r->completadas,
                'pendientes'    => $r->pendientes,
                'recuperadas'   => $r->recuperadas,
                'sin_descargar' => $r->sin_descargar,
                'tasa'          => $r->total > 0 ? round(($r->completadas / $r->total) * 100) : 0,
            ])
            ->toArray();

        return compact(
            'stats', 'pendientesPorUsuario', 'resumenSemanal',
            'topDestinatarios', 'periodo', 'filters'
        );
    }

    public static function reportFilterKeys(): array
    {
        return self::REPORT_FILTER_KEYS;
    }

    public static function normalizeReportFilters(array $filters = []): array
    {
        $normalized = [];

        foreach (self::REPORT_FILTER_KEYS as $key) {
            $value = $filters[$key] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$key] = $value;
        }

        $desde = !empty($normalized['desde'])
            ? Carbon::parse($normalized['desde'])
            : now()->subWeeks(4)->startOfWeek();

        $hasta = !empty($normalized['hasta'])
            ? Carbon::parse($normalized['hasta'])
            : now();

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $estado = $normalized['estado'] ?? 'todos';
        if (!in_array($estado, ['todos', 'completado', 'pendiente', 'transferido'], true)) {
            $estado = 'todos';
        }

        return array_filter([
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
            'estado' => $estado,
            'buscar' => $normalized['buscar'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function filteredQuery(array $filters)
    {
        $desde = Carbon::parse($filters['desde'])->startOfDay()->toDateTimeString();
        $hasta = Carbon::parse($filters['hasta'])->endOfDay()->toDateTimeString();

        $query = KizeoCharlaTracking::query()->enPeriodo($desde, $hasta);

        $estado = $filters['estado'] ?? 'todos';
        if ($estado === 'pendiente') {
            $query->pendientes();
        } elseif ($estado === 'completado') {
            $query->completados();
        } elseif ($estado === 'transferido') {
            $query->transferidos();
        }

        if (!empty($filters['buscar'])) {
            $buscar = $filters['buscar'];
            $query->where(function ($q) use ($buscar) {
                $q->where('asignado_por', 'like', "%{$buscar}%")
                    ->orWhere('asignado_a', 'like', "%{$buscar}%")
                    ->orWhere('titulo_actividad', 'like', "%{$buscar}%")
                    ->orWhere('lugar', 'like', "%{$buscar}%");
            });
        }

        return $query;
    }

    private function recordAction(
        string $action,
        string $status,
        ?string $summary = null,
        array $filters = [],
        array $metadata = []
    ): void {
        CharlaTrackingActionLog::record([
            'user_id' => null,
            'action' => $action,
            'status' => $status,
            'summary' => $summary,
            'filters' => $filters ?: null,
            'metadata' => $metadata ?: null,
            'ip_address' => null,
            'user_agent' => 'console',
        ]);
    }
}
