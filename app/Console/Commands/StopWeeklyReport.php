<?php

namespace App\Console\Commands;

use App\Mail\StopReporteMail;
use App\Models\Configuracion;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StopWeeklyReport extends Command
{
    protected $signature = 'stop:weekly-report
                            {--email= : Enviar a email específico}
                            {--mes= : Filtrar por mes (YYYY-MM)}
                            {--anio= : Filtrar por año (YYYY)}';

    protected $description = 'Genera y envía el reporte semanal/mensual de Tarjeta STOP';

    public function handle(): int
    {
        $this->info('Generando reporte Tarjeta STOP...');

        $drive = new GoogleDriveService();

        if (!$drive->isConfigured()) {
            $this->error('Google Drive no está configurado.');
            return self::FAILURE;
        }

        // Determinar filtros del período
        $filters = [];
        $mesLabel = null;

        if ($mes = $this->option('mes')) {
            $filters['mes'] = $mes;
            $mesLabel = Carbon::createFromFormat('Y-m', $mes)->translatedFormat('F Y');
        } elseif ($anio = $this->option('anio')) {
            $filters['anio'] = $anio;
            $mesLabel = "Año {$anio}";
        } else {
            // Por defecto: mes actual
            $filters['mes'] = now()->format('Y-m');
            $mesLabel = now()->translatedFormat('F Y');
        }

        $analytics = $drive->getFilteredAnalytics($filters);

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            $this->warn('No se encontraron datos para el período seleccionado.');
            return self::SUCCESS;
        }

        $periodo = $mesLabel ?? now()->format('d/m/Y');

        $clasificacion = $analytics['clasificacion'] ?? [];
        $positivas = $clasificacion['Positiva'] ?? $clasificacion['positiva'] ?? 0;
        $negativas = $clasificacion['Negativa'] ?? $clasificacion['negativa'] ?? 0;

        $stats = [
            'total'        => $analytics['totalRows'],
            'positivas'    => $positivas,
            'negativas'    => $negativas,
            'centros'      => count($analytics['centros'] ?? []),
            'observadores' => count($analytics['topObservadores'] ?? []),
        ];

        // Verificar si el reporte está activo
        if (!$this->option('email') && Configuracion::get('stop_report_activo') !== '1') {
            $this->info('Reporte STOP desactivado en configuración.');
            return self::SUCCESS;
        }

        // Destinatarios
        $email = $this->option('email');
        if ($email) {
            $destinatarios = [$email];
        } else {
            $configEmails = Configuracion::get('stop_report_destinatarios', '');
            $destinatarios = collect(explode(',', $configEmails))
                ->map(fn ($e) => trim($e))
                ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values()
                ->toArray();
        }

        if (empty($destinatarios)) {
            $this->warn('No hay destinatarios configurados para el reporte STOP.');
            return self::SUCCESS;
        }

        $mailable = new StopReporteMail(
            stats: $stats,
            topNegTrabajadores: $analytics['topNegTrabajadores'] ?? [],
            topPosTrabajadores: $analytics['topPosTrabajadores'] ?? [],
            negPorTipo: $analytics['negPorTipo'] ?? [],
            posPorTipo: $analytics['posPorTipo'] ?? [],
            centros: $analytics['centros'] ?? [],
            areas: $analytics['areas'] ?? [],
            topObservadores: $analytics['topObservadores'] ?? [],
            antiguedades: $analytics['antiguedades'] ?? [],
            periodo: $periodo,
            mesLabel: $mesLabel,
        );

        foreach ($destinatarios as $dest) {
            try {
                Mail::to($dest)->send($mailable);
                $this->info("Reporte enviado a: {$dest}");
            } catch (\Exception $e) {
                $this->error("Error enviando a {$dest}: {$e->getMessage()}");
                Log::error('stop:weekly-report: error enviando email', [
                    'email' => $dest,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Reporte STOP — Total: {$stats['total']} | Pos: {$positivas} | Neg: {$negativas}");

        Log::info('stop:weekly-report enviado', [
            'stats'         => $stats,
            'destinatarios' => count($destinatarios),
            'periodo'       => $periodo,
        ]);

        return self::SUCCESS;
    }

    /**
     * Build report data for preview routes.
     */
    public static function buildReportData(?string $mes = null, ?string $anio = null): array
    {
        $drive = new GoogleDriveService();

        $filters = [];
        $mesLabel = null;

        if ($mes) {
            $filters['mes'] = $mes;
            $mesLabel = Carbon::createFromFormat('Y-m', $mes)->translatedFormat('F Y');
        } elseif ($anio) {
            $filters['anio'] = $anio;
            $mesLabel = "Año {$anio}";
        } else {
            $filters['mes'] = now()->format('Y-m');
            $mesLabel = now()->translatedFormat('F Y');
        }

        $analytics = $drive->getFilteredAnalytics($filters);

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            return ['stats' => ['total' => 0, 'positivas' => 0, 'negativas' => 0, 'centros' => 0, 'observadores' => 0]];
        }

        $clasificacion = $analytics['clasificacion'] ?? [];

        return [
            'stats' => [
                'total'        => $analytics['totalRows'],
                'positivas'    => $clasificacion['Positiva'] ?? $clasificacion['positiva'] ?? 0,
                'negativas'    => $clasificacion['Negativa'] ?? $clasificacion['negativa'] ?? 0,
                'centros'      => count($analytics['centros'] ?? []),
                'observadores' => count($analytics['topObservadores'] ?? []),
            ],
            'topNegTrabajadores' => $analytics['topNegTrabajadores'] ?? [],
            'topPosTrabajadores' => $analytics['topPosTrabajadores'] ?? [],
            'negPorTipo'         => $analytics['negPorTipo'] ?? [],
            'posPorTipo'         => $analytics['posPorTipo'] ?? [],
            'centros'            => $analytics['centros'] ?? [],
            'areas'              => $analytics['areas'] ?? [],
            'topObservadores'    => $analytics['topObservadores'] ?? [],
            'antiguedades'       => $analytics['antiguedades'] ?? [],
            'periodo'            => $mesLabel ?? now()->format('d/m/Y'),
            'mesLabel'           => $mesLabel,
        ];
    }
}
