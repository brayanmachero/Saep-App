<?php

namespace App\Services;

use App\Models\ReservaVehiculo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReservaVehiculoCalendarService
{
    public function estaConfigurado(): bool
    {
        return (bool) $this->config('enabled')
            && filled($this->config('mailbox'))
            && filled(config('services.microsoft_graph.tenant_id'))
            && filled(config('services.microsoft_graph.client_id'))
            && filled(config('services.microsoft_graph.client_secret'));
    }

    /**
     * Crea o actualiza el evento del calendario compartido sin bloquear la
     * confirmacion operacional de la reserva si Microsoft Graph falla.
     *
     * @return array{estado: 'omitido'|'sincronizado'|'error', detalle?: string}
     */
    public function sincronizar(ReservaVehiculo $reserva): array
    {
        if (! $this->estaConfigurado()) {
            return ['estado' => 'omitido'];
        }

        $reserva->loadMissing('vehiculo');

        try {
            $token = $this->accessToken();
            if (! $token) {
                throw new RuntimeException('Microsoft Graph no entrego un token de calendario.');
            }

            $payload = $this->eventPayload($reserva);
            $eventId = $reserva->calendar_event_id;

            if ($eventId) {
                $response = Http::withToken($token)->patch($this->eventUrl($eventId), $payload);

                if ($response->status() === 404) {
                    $eventId = null;
                } elseif ($response->failed()) {
                    throw new RuntimeException('Microsoft Graph respondio HTTP '.$response->status().'.');
                }
            }

            if (! $eventId) {
                $response = Http::withToken($token)->post($this->eventsUrl(), $payload);

                if ($response->failed() || ! $response->json('id')) {
                    throw new RuntimeException('Microsoft Graph respondio HTTP '.$response->status().' al crear el evento.');
                }

                $eventId = (string) $response->json('id');
            }

            $reserva->forceFill([
                'calendar_event_id' => $eventId,
                'calendar_synced_at' => now(),
                'calendar_last_error' => null,
            ])->save();

            return ['estado' => 'sincronizado'];
        } catch (\Throwable $exception) {
            Log::warning('Reserva vehiculo: no fue posible sincronizar calendario Microsoft 365.', [
                'reserva_id' => $reserva->id,
                'codigo' => $reserva->codigo,
                'error' => $exception->getMessage(),
            ]);

            $reserva->forceFill([
                'calendar_last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            return ['estado' => 'error', 'detalle' => $exception->getMessage()];
        }
    }

    private function eventPayload(ReservaVehiculo $reserva): array
    {
        $vehiculo = $reserva->vehiculo;
        $estado = ReservaVehiculo::ESTADOS[$reserva->estado] ?? $reserva->estado;
        $cancelada = $reserva->estado === 'CANCELADA';
        $titulo = ($cancelada ? 'CANCELADA · ' : '').$reserva->codigo.' · '.$vehiculo?->patente;

        return [
            'subject' => $titulo,
            'body' => [
                'contentType' => 'HTML',
                'content' => $this->bodyHtml($reserva, $estado),
            ],
            'start' => [
                'dateTime' => $reserva->inicio->format('Y-m-d\\TH:i:s'),
                'timeZone' => $this->config('timezone', 'Pacific SA Standard Time'),
            ],
            'end' => [
                'dateTime' => $reserva->termino->format('Y-m-d\\TH:i:s'),
                'timeZone' => $this->config('timezone', 'Pacific SA Standard Time'),
            ],
            'location' => [
                'displayName' => $reserva->destino ?: 'Destino por confirmar',
            ],
            'showAs' => $cancelada ? 'free' : 'busy',
            'categories' => ['SAEP', 'Reserva vehiculo', $estado],
        ];
    }

    private function bodyHtml(ReservaVehiculo $reserva, string $estado): string
    {
        $escape = static fn (?string $value): string => e($value ?: 'No informado');

        return '<h2>Reserva de vehiculo SAEP</h2>'
            .'<p><strong>Codigo:</strong> '.$escape($reserva->codigo).'<br>'
            .'<strong>Estado:</strong> '.$escape($estado).'<br>'
            .'<strong>Vehiculo:</strong> '.$escape($reserva->vehiculo?->patente).' · '.$escape($reserva->vehiculo?->nombre_operativo).'<br>'
            .'<strong>Solicitante:</strong> '.$escape($reserva->solicitante_nombre).' ('.$escape($reserva->solicitante_email).')<br>'
            .'<strong>Telefono:</strong> '.$escape($reserva->solicitante_telefono).'<br>'
            .'<strong>Pasajeros:</strong> '.($reserva->pasajeros ?: 'No informado').'<br>'
            .'<strong>Destino:</strong> '.$escape($reserva->destino).'<br>'
            .'<strong>Motivo:</strong> '.$escape($reserva->motivo).'</p>';
    }

    private function accessToken(): ?string
    {
        return Cache::remember('msgraph_reservas_vehiculos_calendar_token', 3000, function () {
            $response = Http::asForm()->post(
                'https://login.microsoftonline.com/'.config('services.microsoft_graph.tenant_id').'/oauth2/v2.0/token',
                [
                    'client_id' => config('services.microsoft_graph.client_id'),
                    'client_secret' => config('services.microsoft_graph.client_secret'),
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            );

            if ($response->failed()) {
                Log::warning('Reserva vehiculo: no fue posible obtener token Graph para calendario.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    private function eventsUrl(): string
    {
        $mailbox = rawurlencode((string) $this->config('mailbox'));
        $calendarId = $this->config('calendar_id');

        return $calendarId
            ? 'https://graph.microsoft.com/v1.0/users/'.$mailbox.'/calendars/'.rawurlencode((string) $calendarId).'/events'
            : 'https://graph.microsoft.com/v1.0/users/'.$mailbox.'/calendar/events';
    }

    private function eventUrl(string $eventId): string
    {
        return $this->eventsUrl().'/'.rawurlencode($eventId);
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config('services.reservas_vehiculos_calendar.'.$key, $default);
    }
}
