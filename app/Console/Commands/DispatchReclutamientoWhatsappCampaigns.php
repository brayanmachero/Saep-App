<?php

namespace App\Console\Commands;

use App\Models\ReclutamientoWhatsappCampania;
use App\Services\ReclutamientoWhatsappCampaignService;
use Illuminate\Console\Command;
use Throwable;

class DispatchReclutamientoWhatsappCampaigns extends Command
{
    protected $signature = 'whatsapp:despachar-campanias {--limit=50 : Máximo de destinatarios por campaña en esta ejecución}';
    protected $description = 'Despacha campañas de WhatsApp programadas y aprobadas.';

    public function handle(ReclutamientoWhatsappCampaignService $campaigns): int
    {
        if (!$campaigns->isConfigured()) {
            $this->warn('Despacho omitido: Meta WhatsApp Cloud API no está configurada.');
            return self::SUCCESS;
        }

        $campanias = ReclutamientoWhatsappCampania::query()
            ->where('estado', 'programada')
            ->whereNotNull('programada_para')
            ->where('programada_para', '<=', now())
            ->orderBy('programada_para')
            ->get();

        foreach ($campanias as $campania) {
            try {
                $resultado = $campaigns->despacharCampania($campania, (int) $this->option('limit'));
                $this->line("{$campania->id}: {$resultado['estado']} · enviados {$resultado['enviados']} · fallidos {$resultado['fallidos']} · omitidos {$resultado['omitidos']}");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$campania->id}: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
