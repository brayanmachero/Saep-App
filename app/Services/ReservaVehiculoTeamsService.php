<?php

namespace App\Services;

use App\Models\ReservaVehiculo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReservaVehiculoTeamsService
{
    public function estaConfigurado(): bool
    {
        return filled($this->webhookUrl());
    }

    /**
     * Envía una única alerta al crear una reserva. Una falla de Teams nunca
     * invalida la reserva ya confirmada en SAEP.
     */
    public function notificarNuevaReserva(ReservaVehiculo $reserva): bool
    {
        if (! $this->estaConfigurado()) {
            return false;
        }

        $reserva->loadMissing('vehiculo');

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->post($this->webhookUrl(), $this->payloadNuevaReserva($reserva));

            if ($response->failed()) {
                throw new RuntimeException('Teams respondio HTTP '.$response->status().'.');
            }

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Reserva vehiculo: no fue posible notificar la nueva reserva a Teams.', [
                'reserva_id' => $reserva->id,
                'codigo' => $reserva->codigo,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /** @return array<string, mixed> */
    public function payloadNuevaReserva(ReservaVehiculo $reserva): array
    {
        $vehiculo = $reserva->vehiculo;
        $margen = max(0, min(240, (int) config('services.reservas_vehiculos.buffer_minutes', 60)));
        $liberacion = $reserva->termino->copy()->addMinutes($margen);
        $vehiculoNombre = trim(implode(' · ', array_filter([
            $vehiculo?->nombre_operativo,
            $vehiculo?->patente,
        ])));

        return [
            'type' => 'message',
            'attachments' => [[
                'contentType' => 'application/vnd.microsoft.card.adaptive',
                'contentUrl' => null,
                'content' => [
                    '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                    'type' => 'AdaptiveCard',
                    'version' => '1.4',
                    'body' => [
                        [
                            'type' => 'TextBlock',
                            'text' => 'Nueva reserva de vehiculo',
                            'weight' => 'Bolder',
                            'size' => 'Medium',
                            'color' => 'Accent',
                        ],
                        [
                            'type' => 'TextBlock',
                            'text' => 'Solicitud confirmada desde el portal SAEP para gestion de Bodega.',
                            'wrap' => true,
                            'spacing' => 'None',
                        ],
                        [
                            'type' => 'FactSet',
                            'facts' => [
                                ['title' => 'Codigo', 'value' => (string) $reserva->codigo],
                                ['title' => 'Vehiculo', 'value' => $vehiculoNombre ?: 'Por informar'],
                                ['title' => 'Solicitante', 'value' => (string) $reserva->solicitante_nombre],
                                ['title' => 'Inicio', 'value' => $reserva->inicio->format('d/m/Y H:i')],
                                ['title' => 'Hasta', 'value' => $reserva->termino->format('d/m/Y H:i')],
                                ['title' => 'Duracion solicitada', 'value' => $this->duracion($reserva)],
                                ['title' => 'Liberacion para otra reserva', 'value' => $liberacion->format('d/m/Y H:i').($margen > 0 ? ' (incluye margen de '.$margen.' min)' : '')],
                                ['title' => 'Destino', 'value' => $this->texto($reserva->destino, 'Por informar')],
                                ['title' => 'Motivo', 'value' => $this->texto($reserva->motivo, 'Por informar', 240)],
                                ['title' => 'Pasajeros', 'value' => $reserva->pasajeros ? (string) $reserva->pasajeros : 'No informado'],
                                ['title' => 'Creada', 'value' => optional($reserva->created_at)->format('d/m/Y H:i') ?: now()->format('d/m/Y H:i')],
                            ],
                        ],
                    ],
                    'actions' => [[
                        'type' => 'Action.OpenUrl',
                        'title' => 'Abrir reserva en SAEP',
                        'url' => route('gestion-vehiculos.index', ['buscar' => $reserva->codigo]),
                    ]],
                ],
            ]],
        ];
    }

    private function webhookUrl(): string
    {
        return trim((string) config('services.reservas_vehiculos.teams_webhook_url'));
    }

    private function duracion(ReservaVehiculo $reserva): string
    {
        $minutos = (int) $reserva->inicio->diffInMinutes($reserva->termino);
        $dias = intdiv($minutos, 1440);
        $horas = intdiv($minutos % 1440, 60);
        $resto = $minutos % 60;
        $partes = [];

        if ($dias > 0) {
            $partes[] = $dias.' dia'.($dias === 1 ? '' : 's');
        }
        if ($horas > 0) {
            $partes[] = $horas.' hora'.($horas === 1 ? '' : 's');
        }
        if ($resto > 0 || $partes === []) {
            $partes[] = $resto.' min';
        }

        return implode(' y ', $partes);
    }

    private function texto(?string $valor, string $alternativa, int $maximo = 160): string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return $alternativa;
        }

        return mb_strimwidth($valor, 0, $maximo, '...');
    }
}
