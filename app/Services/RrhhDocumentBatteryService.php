<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RrhhDocumentBatteryService
{
    public function selections(array $tokens, int $userId): array
    {
        $documents = [];
        foreach ($tokens as $token) {
            try {
                $selection = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
                if (($selection['user'] ?? null) !== $userId || ! is_string($selection['name'] ?? null) || ! is_int($selection['id'] ?? null) || $selection['id'] < 1) {
                    throw new \RuntimeException;
                }
                $documents[$selection['name']] = ['talana_id' => $selection['id']];
            } catch (\Throwable) {
                throw ValidationException::withMessages(['documentos' => 'Busca y selecciona nuevamente el documento en Talana.']);
            }
        }

        return $documents;
    }

    public function event(int $batteryId, int $actorId, string $action, array $detail = []): void
    {
        DB::table('rrhh_document_battery_events')->insert([
            'battery_id' => $batteryId, 'actor_id' => $actorId, 'action' => $action,
            'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE), 'created_at' => now(),
        ]);
    }

    public function editable(object $battery, int $revision): void
    {
        if ($battery->revision !== $revision) {
            throw ValidationException::withMessages(['bateria' => 'Otra persona modificó esta batería. Recarga para revisar los cambios.']);
        }
        if ($battery->estado !== 'BORRADOR' || DB::table('rrhh_document_batches')->where('battery_id', $battery->id)->exists()) {
            throw ValidationException::withMessages(['bateria' => 'Crea una nueva versión para modificar esta batería.']);
        }
    }
}
