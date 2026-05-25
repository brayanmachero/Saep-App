<?php

namespace App\Console\Commands;

use App\Mail\TalanaContratosVencidosActivosMail;
use App\Services\TalanaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TalanaAlertasVencidosActivosCommand extends Command
{
    protected $signature = 'talana:alertas-vencidos-activos
                            {--email= : Correo destinatario (sobreescribe TALANA_ALERTA_EMAIL)}
                            {--dry-run : Muestra los registros encontrados sin enviar correo}';

    protected $description = 'Detecta contratos vencidos cuyos trabajadores siguen activos (no finiquitados) en Talana y envía alerta (solo lectura)';

    public function handle(TalanaService $talana): int
    {
        $email  = $this->option('email') ?: config('services.talana.alerta_email');
        $dryRun = (bool) $this->option('dry-run');

        if (! $email) {
            $this->error('No se definió destinatario. Configure TALANA_ALERTA_EMAIL en .env o use --email=');
            return self::FAILURE;
        }

        $this->info('Consultando contratos vencidos con trabajadores aún activos ...');

        try {
            $contratos = $talana->contratosVencidosActivos();
        } catch (\Throwable $e) {
            $this->error("Error al consultar Talana: {$e->getMessage()}");
            return self::FAILURE;
        }

        if (empty($contratos)) {
            $this->info('Sin contratos vencidos con trabajadores activos.');
            return self::SUCCESS;
        }

        $this->info("Encontrados: " . count($contratos) . " contrato(s) vencido(s) con trabajador activo.");

        if ($this->getOutput()->isVerbose()) {
            $rows = array_map(fn($c) => [
                "{$c['empleadoDetails']['nombre']} {$c['empleadoDetails']['apellidoPaterno']}",
                $c['empleadoDetails']['rut'] ?? '—',
                $c['cargo'] ?? '—',
                $c['hasta'],
                $c['diasVencido'] . ' días',
                $c['sucursal']['nombre'] ?? '—',
            ], $contratos);

            $this->table(
                ['Trabajador', 'RUT', 'Cargo', 'Venció el', 'Días vencido', 'Sucursal'],
                $rows
            );
        }

        if ($dryRun) {
            $this->warn('[dry-run] Correo NO enviado.');
            return self::SUCCESS;
        }

        Mail::to($email)->send(new TalanaContratosVencidosActivosMail($contratos));

        $this->info("Alerta enviada a: {$email}");
        return self::SUCCESS;
    }
}
