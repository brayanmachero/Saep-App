<?php

namespace App\Console\Commands;

use App\Models\ReclutamientoWhatsappPlantilla;
use App\Services\MetaWhatsappCloudService;
use Illuminate\Console\Command;
use RuntimeException;

class SyncReclutamientoWhatsappTemplates extends Command
{
    protected $signature = 'whatsapp:sync-plantillas';
    protected $description = 'Sincroniza las plantillas oficiales de Meta WhatsApp Cloud API.';

    public function handle(MetaWhatsappCloudService $whatsapp): int
    {
        try {
            $plantillas = $whatsapp->listTemplates();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        foreach ($plantillas as $plantilla) {
            $nombre = trim((string) ($plantilla['name'] ?? ''));
            if ($nombre === '') {
                continue;
            }

            ReclutamientoWhatsappPlantilla::updateOrCreate(
                ['nombre_meta' => $nombre],
                [
                    'idioma' => (string) ($plantilla['language'] ?? 'es'),
                    'categoria' => strtolower((string) ($plantilla['category'] ?? 'utility')),
                    'estado' => strtolower((string) ($plantilla['status'] ?? 'pendiente')),
                    'componentes' => $plantilla['components'] ?? [],
                    'sincronizada_at' => now(),
                ]
            );
        }

        $this->info("Plantillas sincronizadas: " . count($plantillas));

        return self::SUCCESS;
    }
}
