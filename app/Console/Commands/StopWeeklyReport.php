<?php

namespace App\Console\Commands;

use App\Mail\StopReporteMail;
use App\Models\Configuracion;
use App\Models\StopActionLog;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StopWeeklyReport extends Command
{
    public const REPORT_FILTER_KEYS = [
        'empresa_observador',
        'empresa_observado',
        'tipo_observacion',
        'centro',
        'clasificacion',
        'fecha_desde',
        'fecha_hasta',
        'mes',
        'anio',
        'trabajador',
    ];

    protected $signature = 'stop:weekly-report
                            {--email= : Enviar a email específico}
                            {--mes= : Filtrar por mes (YYYY-MM)}
                            {--anio= : Filtrar por año (YYYY)}
                            {--empresa= : Filtrar por empresa observada}
                            {--frecuencia=semanal : semanal o mensual}';

    protected $description = 'Genera y envía el reporte semanal/mensual de Tarjeta STOP CCU';

    public function handle(): int
    {
        $frecuencia = strtolower($this->option('frecuencia') ?? 'semanal');
        $esMensual  = $frecuencia === 'mensual';

        $this->info("Generando reporte Tarjeta STOP CCU ({$frecuencia})...");

        // --- Empresa filter (option > config > none) ---
        $empresa = $this->option('empresa')
            ?: Configuracion::get('stop_report_empresa', '');

        // --- Determinar filtros del período ---
        $filters = [];
        $mesLabel = null;

        if ($mes = $this->option('mes')) {
            $filters['mes'] = $mes;
            $mesLabel = Carbon::createFromFormat('Y-m', $mes)->translatedFormat('F Y');
        } elseif ($anio = $this->option('anio')) {
            $filters['anio'] = $anio;
            $mesLabel = "Año {$anio}";
        } elseif ($esMensual) {
            // Mensual automático: mes anterior completo
            $prev = now()->subMonth();
            $filters['mes'] = $prev->format('Y-m');
            $mesLabel = $prev->translatedFormat('F Y');
        } else {
            // Semanal automático: mes en curso
            $filters['mes'] = now()->format('Y-m');
            $mesLabel = now()->translatedFormat('F Y');
        }

        // Aplicar filtro de empresa (empresa_observado)
        if ($empresa) {
            $filters['empresa_observado'] = $empresa;
            $this->info("Filtrando por empresa observado: {$empresa}");
        }

        $data = self::buildReportDataFromFilters($filters);
        $analytics = $data['analytics'] ?? ['totalRows' => 0];

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            $this->warn('No se encontraron datos para el período seleccionado.');
            $this->recordStopAction('report_scheduled_send', 'skipped', 'Reporte STOP omitido por falta de datos.', $data['filters'] ?? $filters, [
                'frecuencia' => $frecuencia,
                'periodo' => $data['periodo'] ?? null,
                'total_rows' => 0,
            ]);

            return self::SUCCESS;
        }

        $periodo = $data['periodo'];
        $mesLabel = $data['mesLabel'];
        $comparison = $data['comparison'] ?? [];
        $evalDetail = $data['evalDetail'] ?? [];

        $clasificacion = $analytics['clasificacion'] ?? [];
        $positivas = $clasificacion['Positiva'] ?? $clasificacion['positiva'] ?? 0;
        $negativas = $clasificacion['Negativa'] ?? $clasificacion['negativa'] ?? 0;

        // --- Verificar si el reporte está activo ---
        $configActivo = $esMensual
            ? 'stop_report_mensual_activo'
            : 'stop_report_activo';

        if (!$this->option('email') && Configuracion::get($configActivo) !== '1') {
            $this->info("Reporte STOP CCU ({$frecuencia}) desactivado en configuración.");
            $this->recordStopAction('report_scheduled_send', 'skipped', 'Reporte STOP omitido porque la automatización está desactivada.', $data['filters'] ?? $filters, [
                'frecuencia' => $frecuencia,
                'periodo' => $periodo,
                'config_key' => $configActivo,
                'total_rows' => $analytics['totalRows'] ?? 0,
            ]);

            return self::SUCCESS;
        }

        // --- Destinatarios ---
        $email = $this->option('email');
        if ($email) {
            $destinatarios = [$email];
        } else {
            $configKey = $esMensual
                ? 'stop_report_mensual_destinatarios'
                : 'stop_report_destinatarios';

            $configEmails = Configuracion::get($configKey, '');
            $destinatarios = collect(explode(',', $configEmails))
                ->map(fn ($e) => trim($e))
                ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values()
                ->toArray();
        }

        if (empty($destinatarios)) {
            $this->warn("No hay destinatarios configurados para el reporte STOP CCU ({$frecuencia}).");
            $this->recordStopAction('report_scheduled_send', 'skipped', 'Reporte STOP omitido por falta de destinatarios.', $data['filters'] ?? $filters, [
                'frecuencia' => $frecuencia,
                'periodo' => $periodo,
                'total_rows' => $analytics['totalRows'] ?? 0,
            ]);

            return self::SUCCESS;
        }

        $frecLabel = $esMensual ? 'Mensual' : 'Semanal';
        $sent = [];
        $failed = [];

        foreach ($destinatarios as $dest) {
            try {
                $mailable = new StopReporteMail(
                    analytics: $analytics,
                    periodo: $periodo,
                    mesLabel: $mesLabel,
                    frecuencia: $frecLabel,
                    comparison: $comparison,
                    evalDetail: $evalDetail,
                );
                Mail::to($dest)->send($mailable);
                User::where('email', $dest)->first()?->notify(new AppNotification(
                    'Reporte STOP disponible',
                    "Reporte {$frecLabel} generado",
                    'info',
                    route('stop-dashboard')
                ));
                $this->info("Reporte enviado a: {$dest}");
                $sent[] = $dest;
            } catch (\Exception $e) {
                $this->error("Error enviando a {$dest}: {$e->getMessage()}");
                $failed[] = [
                    'email' => $dest,
                    'error' => $e->getMessage(),
                ];

                Log::error("stop:weekly-report ({$frecuencia}): error enviando email", [
                    'email' => $dest,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $auditStatus = empty($failed) ? 'success' : (!empty($sent) ? 'partial' : 'failed');
        $this->recordStopAction('report_scheduled_send', $auditStatus, 'Ejecución de reporte STOP desde comando.', $data['filters'] ?? $filters, [
            'frecuencia' => $frecLabel,
            'periodo' => $periodo,
            'recipients' => $destinatarios,
            'sent' => $sent,
            'failed' => $failed,
            'sent_count' => count($sent),
            'failed_count' => count($failed),
            'total_rows' => $analytics['totalRows'] ?? 0,
        ]);

        $this->info("Reporte STOP CCU ({$frecLabel}) — Total: {$analytics['totalRows']} | Pos: {$positivas} | Neg: {$negativas}");

        Log::info("stop:weekly-report ({$frecuencia}) enviado", [
            'total'         => $analytics['totalRows'],
            'destinatarios' => count($destinatarios),
            'periodo'       => $periodo,
            'empresa'       => $empresa ?: '(todas)',
        ]);

        return self::SUCCESS;
    }

    private function recordStopAction(
        string $action,
        string $status,
        ?string $summary = null,
        array $filters = [],
        array $metadata = []
    ): void {
        StopActionLog::record([
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

    /**
     * Build report data for preview routes.
     */
    public static function buildReportData(?string $mes = null, ?string $anio = null, ?string $empresa = null): array
    {
        $filters = [];

        if ($mes) {
            $filters['mes'] = $mes;
        } elseif ($anio) {
            $filters['anio'] = $anio;
        }

        if ($empresa) {
            $filters['empresa_observado'] = $empresa;
        }

        return self::buildReportDataFromFilters($filters);
    }

    public static function buildReportDataFromFilters(array $filters = []): array
    {
        $drive = new GoogleDriveService();

        $filters = self::normalizeReportFilters($filters);
        $periodo = self::periodLabelForFilters($filters);
        $mesLabel = self::monthLabelForFilters($filters);

        // Intentar usar SQL si hay datos sincronizados
        $sql = new \App\Services\StopAnalyticsService();
        $useSql = $sql->hasSyncedData();

        if ($useSql) {
            $analytics = $sql->getFilteredAnalytics($filters);
        } else {
            $analytics = $drive->getFilteredAnalytics($filters);
        }

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            return [
                'analytics' => ['totalRows' => 0],
                'periodo' => $periodo,
                'mesLabel' => $mesLabel,
                'comparison' => [],
                'evalDetail' => [],
                'filters' => $filters,
            ];
        }

        // --- Comparativa: YTD año actual + año anterior ---
        $comparison = $useSql
            ? $sql->buildComparison($filters)
            : self::buildComparison($drive, $filters);

        // --- Detalle de evaluación negativas ---
        $evalDetail = $useSql
            ? ($sql->getEvaluationDetail($filters) ?? [])
            : ($drive->getEvaluationDetail($filters) ?? []);

        return [
            'analytics'  => $analytics,
            'periodo'    => $periodo,
            'mesLabel'   => $mesLabel,
            'comparison' => $comparison,
            'evalDetail' => $evalDetail,
            'filters'    => $filters,
        ];
    }

    public static function reportFilterKeys(): array
    {
        return self::REPORT_FILTER_KEYS;
    }

    public static function normalizeReportFilters(array $filters): array
    {
        $normalized = [];
        $forceAllPeriod = !empty($filters['all']);

        foreach (self::REPORT_FILTER_KEYS as $key) {
            $value = $filters[$key] ?? null;
            if ($value === null) {
                continue;
            }

            $value = is_string($value) ? trim($value) : $value;
            if ($value === '') {
                continue;
            }

            $normalized[$key] = $value;
        }

        $hasPeriod = !empty($normalized['fecha_desde'])
            || !empty($normalized['fecha_hasta'])
            || !empty($normalized['mes'])
            || !empty($normalized['anio']);

        if (!$hasPeriod && !$forceAllPeriod) {
            $normalized['mes'] = now()->format('Y-m');
        }

        if (empty($normalized['empresa_observado'])) {
            $empresa = Configuracion::get('stop_report_empresa', '');
            if ($empresa) {
                $normalized['empresa_observado'] = $empresa;
            }
        }

        return $normalized;
    }

    public static function periodLabelForFilters(array $filters): string
    {
        if (!empty($filters['fecha_desde']) && !empty($filters['fecha_hasta'])) {
            return Carbon::parse($filters['fecha_desde'])->format('d/m/Y')
                . ' — '
                . Carbon::parse($filters['fecha_hasta'])->format('d/m/Y');
        }

        if (!empty($filters['mes'])) {
            return Carbon::createFromFormat('Y-m', $filters['mes'])->translatedFormat('F Y');
        }

        if (!empty($filters['anio'])) {
            return "Año {$filters['anio']}";
        }

        return now()->format('d/m/Y');
    }

    public static function monthLabelForFilters(array $filters): ?string
    {
        if (!empty($filters['mes'])) {
            return Carbon::createFromFormat('Y-m', $filters['mes'])->translatedFormat('F Y');
        }

        if (!empty($filters['anio'])) {
            return "Año {$filters['anio']}";
        }

        return null;
    }

    /**
     * Compute year-over-year and YTD comparison data.
     */
    public static function buildComparison(GoogleDriveService $drive, array $baseFilters): array
    {
        $period = self::resolveComparisonPeriod($baseFilters);

        // Carry over non-date filters (empresa_observado, empresa_observador, centro, etc.)
        $carryFilters = array_filter([
            'empresa_observador' => $baseFilters['empresa_observador'] ?? null,
            'empresa_observado'  => $baseFilters['empresa_observado'] ?? null,
            'centro'             => $baseFilters['centro'] ?? null,
            'tipo_observacion'   => $baseFilters['tipo_observacion'] ?? null,
            'clasificacion'      => $baseFilters['clasificacion'] ?? null,
        ]);

        try {
            $ytdData = $drive->getFilteredAnalytics(array_merge($period['current_ytd'], $carryFilters)) ?? [];
            $prevData = $drive->getFilteredAnalytics(array_merge($period['previous_period'], $carryFilters)) ?? [];
            $prevYtdData = $drive->getFilteredAnalytics(array_merge($period['previous_ytd'], $carryFilters)) ?? [];
        } catch (\Throwable $e) {
            Log::warning('stop:weekly-report: error obteniendo datos comparativos', ['error' => $e->getMessage()]);
            return [];
        }

        $ytdClasif  = $ytdData['clasificacion'] ?? [];
        $prevPeriodClasif = $prevData['clasificacion'] ?? [];
        $prevYtdClasif = $prevYtdData['clasificacion'] ?? [];

        $samePeriodTotal = $prevData['totalRows'] ?? 0;
        $samePeriodNeg = $prevPeriodClasif['Negativa'] ?? $prevPeriodClasif['negativa'] ?? 0;
        $samePeriodPos = $prevPeriodClasif['Positiva'] ?? $prevPeriodClasif['positiva'] ?? 0;

        return [
            'currentYear' => $period['current_year'],
            'ytd' => [
                'total'      => $ytdData['totalRows'] ?? 0,
                'pos'        => $ytdClasif['Positiva'] ?? $ytdClasif['positiva'] ?? 0,
                'neg'        => $ytdClasif['Negativa'] ?? $ytdClasif['negativa'] ?? 0,
                'topNeg'     => array_slice($ytdData['topNegTrabajadores'] ?? [], 0, 10, true),
                'topPos'     => array_slice($ytdData['topPosTrabajadores'] ?? [], 0, 10, true),
                'negPorTipo' => array_slice($ytdData['negPorTipo'] ?? [], 0, 10, true),
                'posPorTipo' => array_slice($ytdData['posPorTipo'] ?? [], 0, 10, true),
                'byMonth'    => $ytdData['byMonth'] ?? [],
                'byMonthNeg' => $ytdData['byMonthNeg'] ?? [],
                'byMonthPos' => $ytdData['byMonthPos'] ?? [],
            ],
            'prevYear' => [
                'year'           => $period['previous_year'],
                'total'          => $prevData['totalRows'] ?? 0,
                'pos'            => $samePeriodPos,
                'neg'            => $samePeriodNeg,
                'sameMonthTotal' => $samePeriodTotal,
                'sameMonthPos'   => $samePeriodPos,
                'sameMonthNeg'   => $samePeriodNeg,
                'ytdTotal'       => $prevYtdData['totalRows'] ?? 0,
                'ytdPos'         => $prevYtdClasif['Positiva'] ?? $prevYtdClasif['positiva'] ?? 0,
                'ytdNeg'         => $prevYtdClasif['Negativa'] ?? $prevYtdClasif['negativa'] ?? 0,
                'byMonth'        => $prevYtdData['byMonth'] ?? [],
                'byMonthNeg'     => $prevYtdData['byMonthNeg'] ?? [],
                'byMonthPos'     => $prevYtdData['byMonthPos'] ?? [],
            ],
        ];
    }

    private static function resolveComparisonPeriod(array $baseFilters): array
    {
        if (!empty($baseFilters['fecha_desde']) && !empty($baseFilters['fecha_hasta'])) {
            $startDate = Carbon::parse($baseFilters['fecha_desde']);
            $endDate = Carbon::parse($baseFilters['fecha_hasta']);

            if ($endDate->lt($startDate)) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            $previousStart = $startDate->copy()->subYear();
            $previousEnd = $endDate->copy()->subYear();

            return [
                'current_year' => (int) $endDate->format('Y'),
                'previous_year' => (int) $previousEnd->format('Y'),
                'current_ytd' => [
                    'fecha_desde' => $endDate->copy()->startOfYear()->format('Y-m-d'),
                    'fecha_hasta' => $endDate->format('Y-m-d'),
                ],
                'previous_period' => [
                    'fecha_desde' => $previousStart->format('Y-m-d'),
                    'fecha_hasta' => $previousEnd->format('Y-m-d'),
                ],
                'previous_ytd' => [
                    'fecha_desde' => $previousEnd->copy()->startOfYear()->format('Y-m-d'),
                    'fecha_hasta' => $previousEnd->format('Y-m-d'),
                ],
            ];
        }

        if (!empty($baseFilters['mes'])) {
            $month = Carbon::createFromFormat('Y-m', $baseFilters['mes'])->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();
            $previousMonth = $month->copy()->subYear();

            return [
                'current_year' => (int) $month->format('Y'),
                'previous_year' => (int) $previousMonth->format('Y'),
                'current_ytd' => [
                    'fecha_desde' => $month->copy()->startOfYear()->format('Y-m-d'),
                    'fecha_hasta' => $monthEnd->format('Y-m-d'),
                ],
                'previous_period' => [
                    'mes' => $previousMonth->format('Y-m'),
                ],
                'previous_ytd' => [
                    'fecha_desde' => $previousMonth->copy()->startOfYear()->format('Y-m-d'),
                    'fecha_hasta' => $previousMonth->copy()->endOfMonth()->format('Y-m-d'),
                ],
            ];
        }

        if (!empty($baseFilters['anio'])) {
            $currentYear = (int) $baseFilters['anio'];
            $previousYear = $currentYear - 1;

            return [
                'current_year' => $currentYear,
                'previous_year' => $previousYear,
                'current_ytd' => ['anio' => (string) $currentYear],
                'previous_period' => ['anio' => (string) $previousYear],
                'previous_ytd' => ['anio' => (string) $previousYear],
            ];
        }

        $today = now();
        $previousDate = $today->copy()->subYear();

        return [
            'current_year' => (int) $today->format('Y'),
            'previous_year' => (int) $previousDate->format('Y'),
            'current_ytd' => [
                'fecha_desde' => $today->copy()->startOfYear()->format('Y-m-d'),
                'fecha_hasta' => $today->format('Y-m-d'),
            ],
            'previous_period' => [
                'mes' => $previousDate->format('Y-m'),
            ],
            'previous_ytd' => [
                'fecha_desde' => $previousDate->copy()->startOfYear()->format('Y-m-d'),
                'fecha_hasta' => $previousDate->format('Y-m-d'),
            ],
        ];
    }
}
