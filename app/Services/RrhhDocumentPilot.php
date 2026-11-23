<?php

namespace App\Services;

use RuntimeException;

class RrhhDocumentPilot
{
    public function assertRecipient(array $snapshot): void
    {
        if (!config('services.talana_documents.pilot')) return;
        $until = config('services.talana_documents.pilot_until');
        if (!$until || now()->greaterThanOrEqualTo(\Carbon\Carbon::parse($until))) {
            throw new RuntimeException('La ventana del piloto documental está cerrada.');
        }
        $key = ($snapshot['empresa_id'] ?? '').':'.($snapshot['persona_talana_id'] ?? '');
        if (!in_array($key, config('services.talana_documents.pilot_recipients', []), true)) {
            throw new RuntimeException('El destinatario no está autorizado para este piloto documental.');
        }
    }
}
