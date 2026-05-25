<?php

namespace App\Console\Commands;

use App\Mail\TalanaContratoVencimientoMail;
use App\Services\TalanaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TalanaAlertasContratosCommand extends Command
{
    protected $signature = 'talana:alertas-contratos
                            {--dias=30 : Umbral de días para alertar (contratos que vencen dentro de N días)}
                            {--email= : Correo destinatario (sobreescribe TALANA_ALERTA_EMAIL)}
                            {--dry-run : Muestra los contratos encontrados sin enviar correo}';

    protected $description = 'Detecta contratos próximos a vencer en Talana y envía alerta consolidada por correo (solo lectura)';

    public function handle(TalanaService $talana): int
    {
        $dias  = (int) $this->option('dias');
        $email = $this->option('email') ?: config('services.talana.alerta_email');
        $dryRun = (bool) $this->option('dry-run');

        if (! $email) {
            $this->error('No se definió destinatario. Configure TALANA_ALERTA_EMAIL en .env o use --email=');
            return self::FAILURE;
        }

        $this->info("Consultando contratos próximos a vencer (umbral: {$dias} días) ...");

        try {
            $contratos = $talana->contratosPorVencer($dias);
        } catch (\Throwable $e) {
            $this->error("Error al consultar Talana: {$e->getMessage()}");
            return self::FAILURE;
        }

        if (empty($contratos)) {
            $this->info('Sin contratos próximos a vencer en el período.');
            return self::SUCCESS;
        }

        $this->info("Encontrados: " . count($contratos) . " contrato(s) por vencer.");

        if ($this->getOutput()->isVerbose()) {
            $rows = array_map(fn($c) => [
                "{$c['empleadoDetails']['nombre']} {$c['empleadoDetails']['apellidoPaterno']}",
                $c['empleadoDetails']['rut'] ?? '—',
                $c['cargo'] ?? '—',
                $c['hasta'],
                $c['diasRestantes'] . ' días',
                $c['sucursal']['nombre'] ?? '—',
            ], $contratos);

            $this->table(
                ['Trabajador', 'RUT', 'Cargo', 'Fecha Término', 'Días Rest.', 'Sucursal'],
                $rows
            );
        }

        if ($dryRun) {
            $this->warn('[dry-run] Correo NO enviado.');
            return self::SUCCESS;
        }

        Mail::to($email)->send(new TalanaContratoVencimientoMail($contratos, $dias));

        $this->info("Alerta enviada a: {$email}");
        return self::SUCCESS;
    }
}
