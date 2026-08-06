<?php

namespace Tests\Unit;

use App\Models\ReservaVehiculo;
use App\Models\Vehiculo;
use App\Services\ReservaVehiculoTeamsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReservaVehiculoTeamsServiceTest extends TestCase
{
    public function test_it_sends_a_card_with_requested_end_duration_and_release_time(): void
    {
        config([
            'services.reservas_vehiculos.teams_webhook_url' => 'https://teams.example.test/webhook/reservas',
            'services.reservas_vehiculos.buffer_minutes' => 60,
        ]);
        Http::fake([
            'https://teams.example.test/webhook/*' => Http::response(null, 202),
        ]);

        $reserva = new ReservaVehiculo;
        $reserva->forceFill([
            'id' => 99,
            'codigo' => 'RV-2026-000099',
            'solicitante_nombre' => 'Solicitante QA',
            'inicio' => Carbon::parse('2026-08-08 09:00:00'),
            'termino' => Carbon::parse('2026-08-08 12:00:00'),
            'destino' => 'CD Renca',
            'motivo' => 'Traslado operativo para entrega de equipos',
            'pasajeros' => 2,
            'created_at' => Carbon::parse('2026-08-06 15:42:00'),
        ]);
        $vehiculo = new Vehiculo;
        $vehiculo->forceFill([
            'patente' => 'PSHD-38',
            'marca' => 'Chevrolet',
            'modelo' => 'N400',
        ]);
        $reserva->setRelation('vehiculo', $vehiculo);

        $service = app(ReservaVehiculoTeamsService::class);

        $this->assertTrue($service->notificarNuevaReserva($reserva));

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $facts = collect($data['attachments'][0]['content']['body'][2]['facts'] ?? [])
                ->mapWithKeys(fn (array $fact) => [$fact['title'] => $fact['value']]);

            return $request->method() === 'POST'
                && $request->url() === 'https://teams.example.test/webhook/reservas'
                && $facts->get('Hasta') === '08/08/2026 12:00'
                && $facts->get('Duracion solicitada') === '3 horas'
                && $facts->get('Liberacion para otra reserva') === '08/08/2026 13:00 (incluye margen de 60 min)'
                && $facts->get('Solicitante') === 'Solicitante QA'
                && ($data['attachments'][0]['content']['actions'][0]['title'] ?? null) === 'Abrir reserva en SAEP';
        });
    }

    public function test_it_skips_the_notification_when_the_webhook_is_not_configured(): void
    {
        config(['services.reservas_vehiculos.teams_webhook_url' => null]);
        Http::fake();

        $reserva = new ReservaVehiculo;
        $reserva->forceFill([
            'codigo' => 'RV-2026-000100',
            'inicio' => Carbon::parse('2026-08-08 09:00:00'),
            'termino' => Carbon::parse('2026-08-08 10:00:00'),
        ]);

        $this->assertFalse(app(ReservaVehiculoTeamsService::class)->notificarNuevaReserva($reserva));
        Http::assertNothingSent();
    }
}
