<?php

namespace App\Console\Commands;

use App\Mail\CharlaTrackingReporteMail;
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
    protected $signature = 'kizeo:charla-weekly-report
                            {--email= : Enviar a email específico (si no, envía a superadmins)}
                            {--sync : Ejecutar sincronización antes del reporte}';

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

        // Período: últimas 4 semanas
        $hasta = now();
        $desde = now()->subWeeks(4)->startOfWeek();
        $periodo = $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');

        // Stats generales del período
        $total       = KizeoCharlaTracking::enPeriodo($desde, $hasta)->count();
        $completadas = KizeoCharlaTracking::enPeriodo($desde, $hasta)->completados()->count();
        $transferidos = KizeoCharlaTracking::enPeriodo($desde, $hasta)->transferidos()->count();
        $sinGestion  = $total - $completadas - $transferidos;
        $tasa        = $total > 0 ? round(($completadas / $total) * 100, 1) : 0;

        $promDias = KizeoCharlaTracking::pendientes()
            ->enPeriodo($desde, $hasta)
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

        // Charlas sin completar agrupadas por responsable actual
        $pendientesPorUsuario = KizeoCharlaTracking::pendientes()
            ->enPeriodo($desde, $hasta)
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

        // Resumen por semana (últimas 4 semanas)
        $resumenSemanal = [];
        for ($i = 3; $i >= 0; $i--) {
            $semStart = now()->subWeeks($i)->startOfWeek();
            $semEnd   = now()->subWeeks($i)->endOfWeek();
            $semTotal = KizeoCharlaTracking::enPeriodo($semStart, $semEnd)->count();
            $semComp  = KizeoCharlaTracking::enPeriodo($semStart, $semEnd)->completados()->count();
            $semTransf = KizeoCharlaTracking::enPeriodo($semStart, $semEnd)->transferidos()->count();

            $resumenSemanal[] = [
                'semana'       => (int) $semStart->isoWeek(),
                'anio'         => (int) $semStart->isoWeekYear(),
                'fecha'        => $semStart->format('d/m'),
                'total'        => $semTotal,
                'completadas'  => $semComp,
                'transferidos' => $semTransf,
                'tasa'         => $semTotal > 0 ? round(($semComp / $semTotal) * 100, 1) : 0,
            ];
        }

        // Cumplimiento por destinatario (quién tiene formularios por completar)
        $topDestinatarios = KizeoCharlaTracking::enPeriodo($desde, $hasta)
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

        // Verificar si el reporte está activo
        if (!$this->option('email') && Configuracion::get('charla_report_activo') !== '1') {
            $this->info('Reporte semanal de charlas desactivado en configuración.');
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
            return self::SUCCESS;
        }

        // Enviar email (crear mailable nuevo por cada destinatario para evitar acumulación de recipients)
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
            } catch (\Exception $e) {
                $this->error("Error enviando a {$dest}: {$e->getMessage()}");
                Log::error('charla-weekly-report: error enviando email', [
                    'email' => $dest,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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
        $hasta = now();
        $desde = now()->subWeeks(4)->startOfWeek();
        $periodo = $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');

        $total       = KizeoCharlaTracking::enPeriodo($desde, $hasta)->count();
        $completadas = KizeoCharlaTracking::enPeriodo($desde, $hasta)->completados()->count();
        $transferidos = KizeoCharlaTracking::enPeriodo($desde, $hasta)->transferidos()->count();
        $sinGestion  = $total - $completadas - $transferidos;
        $tasa        = $total > 0 ? round(($completadas / $total) * 100, 1) : 0;

        $promDias = KizeoCharlaTracking::pendientes()
            ->enPeriodo($desde, $hasta)
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

        $pendientesPorUsuario = KizeoCharlaTracking::pendientes()
            ->enPeriodo($desde, $hasta)
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

        $resumenSemanal = [];
        for ($i = 3; $i >= 0; $i--) {
            $semStart = now()->subWeeks($i)->startOfWeek();
            $semEnd   = now()->subWeeks($i)->endOfWeek();
            $semTotal = KizeoCharlaTracking::enPeriodo($semStart, $semEnd)->count();
            $semComp  = KizeoCharlaTracking::enPeriodo($semStart, $semEnd)->completados()->count();
            $semTransf = KizeoCharlaTracking::enPeriodo($semStart, $semEnd)->transferidos()->count();

            $resumenSemanal[] = [
                'semana'       => (int) $semStart->isoWeek(),
                'anio'         => (int) $semStart->isoWeekYear(),
                'fecha'        => $semStart->format('d/m'),
                'total'        => $semTotal,
                'completadas'  => $semComp,
                'transferidos' => $semTransf,
                'tasa'         => $semTotal > 0 ? round(($semComp / $semTotal) * 100, 1) : 0,
            ];
        }

        $topDestinatarios = KizeoCharlaTracking::enPeriodo($desde, $hasta)
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
            'topDestinatarios', 'periodo'
        );
    }
}
