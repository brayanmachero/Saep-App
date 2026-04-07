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
                            {--anio= : Filtrar por año (YYYY)}
                            {--empresa= : Filtrar por empresa observador}
                            {--frecuencia=semanal : semanal o mensual}';

    protected $description = 'Genera y envía el reporte semanal/mensual de Tarjeta STOP';

    public function handle(): int
    {
        $frecuencia = strtolower($this->option('frecuencia') ?? 'semanal');
        $esMensual  = $frecuencia === 'mensual';

        $this->info("Generando reporte Tarjeta STOP ({$frecuencia})...");

        $drive = new GoogleDriveService();

        if (!$drive->isConfigured()) {
            $this->error('Google Drive no está configurado.');
            return self::FAILURE;
        }

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

        // Aplicar filtro de empresa
        if ($empresa) {
            $filters['empresa_observador'] = $empresa;
            $this->info("Filtrando por empresa: {$empresa}");
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

        // --- Verificar si el reporte está activo ---
        $configActivo = $esMensual
            ? 'stop_report_mensual_activo'
            : 'stop_report_activo';

        if (!$this->option('email') && Configuracion::get($configActivo) !== '1') {
            $this->info("Reporte STOP ({$frecuencia}) desactivado en configuración.");
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
            $this->warn("No hay destinatarios configurados para el reporte STOP ({$frecuencia}).");
            return self::SUCCESS;
        }

        $frecLabel = $esMensual ? 'Mensual' : 'Semanal';

        $mailable = new StopReporteMail(
            analytics: $analytics,
            periodo: $periodo,
            mesLabel: $mesLabel,
            frecuencia: $frecLabel,
        );

        foreach ($destinatarios as $dest) {
            try {
                Mail::to($dest)->send($mailable);
                $this->info("Reporte enviado a: {$dest}");
            } catch (\Exception $e) {
                $this->error("Error enviando a {$dest}: {$e->getMessage()}");
                Log::error("stop:weekly-report ({$frecuencia}): error enviando email", [
                    'email' => $dest,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Reporte STOP ({$frecLabel}) — Total: {$analytics['totalRows']} | Pos: {$positivas} | Neg: {$negativas}");

        Log::info("stop:weekly-report ({$frecuencia}) enviado", [
            'total'         => $analytics['totalRows'],
            'destinatarios' => count($destinatarios),
            'periodo'       => $periodo,
            'empresa'       => $empresa ?: '(todas)',
        ]);

        return self::SUCCESS;
    }

    /**
     * Build report data for preview routes.
     */
    public static function buildReportData(?string $mes = null, ?string $anio = null, ?string $empresa = null): array
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

        // Usar empresa pasada o la de configuración
        $emp = $empresa ?: Configuracion::get('stop_report_empresa', '');
        if ($emp) {
            $filters['empresa_observador'] = $emp;
        }

        $analytics = $drive->getFilteredAnalytics($filters);

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            return ['analytics' => ['totalRows' => 0], 'periodo' => $mesLabel, 'mesLabel' => $mesLabel];
        }

        return [
            'analytics' => $analytics,
            'periodo'   => $mesLabel ?? now()->format('d/m/Y'),
            'mesLabel'  => $mesLabel,
        ];
    }
}
