<?php

namespace App\Console\Commands;

use App\Mail\CharlaTrackingReporteMail;
use App\Models\Configuracion;
use App\Models\KizeoCharlaTracking;
use App\Models\User;
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
        $total      = KizeoCharlaTracking::enPeriodo($desde, $hasta)->count();
        $completadas = KizeoCharlaTracking::enPeriodo($desde, $hasta)->completados()->count();
        $pendientes  = KizeoCharlaTracking::enPeriodo($desde, $hasta)->pendientes()->count();
        $tasa        = $total > 0 ? round(($completadas / $total) * 100, 1) : 0;

        $stats = [
            'total'             => $total,
            'completadas'       => $completadas,
            'pendientes'        => $pendientes,
            'tasa_cumplimiento' => $tasa,
        ];

        // Pendientes agrupados por usuario (asignado_por)
        $pendientesPorUsuario = KizeoCharlaTracking::pendientes()
            ->enPeriodo($desde, $hasta)
            ->selectRaw('asignado_por as nombre, COUNT(*) as cantidad, MIN(fecha_creacion) as fecha_min')
            ->groupBy('asignado_por')
            ->orderByDesc('cantidad')
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

            $resumenSemanal[] = [
                'semana'      => (int) $semStart->isoWeek(),
                'anio'        => (int) $semStart->isoWeekYear(),
                'total'       => $semTotal,
                'completadas' => $semComp,
                'tasa'        => $semTotal > 0 ? round(($semComp / $semTotal) * 100, 1) : 0,
            ];
        }

        // Destinatarios
        $email = $this->option('email');
        if ($email) {
            $destinatarios = [$email];
        } else {
            $destinatarios = User::whereHas('rol', fn ($q) => $q->where('codigo', 'SUPER_ADMIN'))
                ->where('activo', true)
                ->pluck('email')
                ->filter()
                ->toArray();

            // Agregar email de notificación de Kizeo si está configurado
            $kizeoNotifyEmail = config('services.kizeo.notify_email');
            if ($kizeoNotifyEmail && !in_array($kizeoNotifyEmail, $destinatarios)) {
                $destinatarios[] = $kizeoNotifyEmail;
            }
        }

        if (empty($destinatarios)) {
            $this->warn('No hay destinatarios configurados para el reporte.');
            return self::SUCCESS;
        }

        // Enviar email
        $mailable = new CharlaTrackingReporteMail($stats, $pendientesPorUsuario, $resumenSemanal, $periodo);

        foreach ($destinatarios as $dest) {
            try {
                Mail::to($dest)->send($mailable);
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
}
