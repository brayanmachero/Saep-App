<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Mail\ReservaVehiculoMail;
use App\Models\Rol;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\OneDriveService;
use App\Services\ReservaVehiculoKizeoService;
use App\Services\ReservaVehiculoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ReservaVehiculoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-01 08:00:00'));
        $this->withoutMiddleware([
            VerificarConsentimientoDatos::class,
            ForcePasswordChange::class,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_vehicle_rejects_overlapping_reservations_but_allows_adjacent_periods(): void
    {
        $vehiculo = Vehiculo::create([
            'patente' => 'TEST-01',
            'marca' => 'Toyota',
            'modelo' => 'Yaris',
            'estado' => 'DISPONIBLE',
            'reservas_habilitadas' => true,
        ]);
        $service = app(ReservaVehiculoService::class);
        $identity = ['oid' => 'test-oid', 'email' => 'solicitante@saep.cl', 'name' => 'Solicitante QA'];

        $first = $service->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-02 09:00:00',
            'termino' => '2026-08-02 10:00:00',
            'motivo' => 'Traslado a centro de trabajo',
        ], $identity);

        $adjacent = $service->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-02 10:00:00',
            'termino' => '2026-08-02 11:00:00',
            'motivo' => 'Segundo traslado operativo',
        ], $identity);

        $this->assertSame('RV-2026-'.str_pad((string) $first->id, 6, '0', STR_PAD_LEFT), $first->codigo);
        $this->assertSame('CONFIRMADA', $adjacent->estado);
        $this->assertDatabaseCount('reserva_vehiculo_eventos', 2);

        try {
            $service->crearReserva([
                'vehiculo_id' => $vehiculo->id,
                'inicio' => '2026-08-02 09:30:00',
                'termino' => '2026-08-02 10:30:00',
                'motivo' => 'Intento que no debe cruzarse',
            ], $identity);
            $this->fail('La reserva cruzada debio ser rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vehiculo_id', $exception->errors());
        }

        $this->assertDatabaseCount('reservas_vehiculos', 2);
    }

    public function test_public_portal_requires_an_explicit_range_and_only_offers_free_vehicles(): void
    {
        $ocupado = Vehiculo::create([
            'patente' => 'OCPD-01', 'marca' => 'Fiat', 'modelo' => 'Fiorino', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        $libre = Vehiculo::create([
            'patente' => 'LIBR-01', 'marca' => 'Chevrolet', 'modelo' => 'N400', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $ocupado->id,
            'inicio' => '2026-08-03 08:00:00',
            'termino' => '2026-08-03 12:00:00',
            'motivo' => 'Reserva existente de prueba',
        ], ['oid' => 'existing', 'email' => 'existente@saep.cl', 'name' => 'Usuario Existente']);

        $this->withSession(['reserva_vehiculo_microsoft_identity' => [
            'oid' => 'viewer', 'email' => 'visor@saep.cl', 'name' => 'Visor QA',
        ]])->get(route('reservas-vehiculos.inicio'))
            ->assertOk()
            ->assertSee('Ningun vehiculo aparece sin un rango consultado.')
            ->assertDontSee('LIBR-01');

        $response = $this->withSession(['reserva_vehiculo_microsoft_identity' => [
            'oid' => 'viewer', 'email' => 'visor@saep.cl', 'name' => 'Visor QA',
        ]])->get(route('reservas-vehiculos.inicio', [
            'inicio' => '2026-08-03T09:00',
            'termino' => '2026-08-03T10:00',
        ]));

        $response->assertOk()
            ->assertSee('Agenda de reservas')
            ->assertSee('LIBR-01')
            ->assertSee('OCPD-01')
            ->assertSee('<option value="'.$libre->id.'"', false)
            ->assertDontSee('<option value="'.$ocupado->id.'"', false);

        $this->assertTrue($libre->exists);
    }

    public function test_confirmation_offers_a_direct_link_to_the_safe_reservation_agenda(): void
    {
        $this->withSession([
            'reserva_vehiculo_microsoft_identity' => [
                'oid' => 'confirmed-viewer', 'email' => 'confirmado@saep.cl', 'name' => 'Confirmado QA',
            ],
            'success' => 'Reserva RV-2026-000001 confirmada.',
        ])->get(route('reservas-vehiculos.inicio', [
            'inicio' => '2026-08-03T09:00',
            'termino' => '2026-08-03T10:00',
        ]))
            ->assertOk()
            ->assertSee('Ver agenda de reservas')
            ->assertSee('href="#agenda"', false);
    }

    public function test_internal_operator_change_is_logged_with_the_user(): void
    {
        $role = Rol::where('codigo', 'BODEGA_VEHICULOS')->firstOrFail();
        $operator = User::create([
            'name' => 'Operador',
            'email' => 'operador.vehiculos@saep.cl',
            'rol_id' => $role->id,
            'password' => bcrypt('secret'),
            'activo' => true,
        ]);
        $vehiculo = Vehiculo::create([
            'patente' => 'LOGS-01', 'marca' => 'Chevrolet', 'modelo' => 'Sail', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        $reserva = app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-04 09:00:00',
            'termino' => '2026-08-04 12:00:00',
            'motivo' => 'Reserva para validacion de bitacora',
        ], ['oid' => 'booking', 'email' => 'booking@saep.cl', 'name' => 'Booking QA']);

        $this->actingAs($operator)
            ->patch(route('gestion-vehiculos.reservas.update', $reserva), ['estado' => 'EN_USO'])
            ->assertRedirect();

        $this->assertDatabaseHas('reservas_vehiculos', ['id' => $reserva->id, 'estado' => 'EN_USO']);
        $this->assertDatabaseHas('reserva_vehiculo_eventos', [
            'reserva_vehiculo_id' => $reserva->id,
            'user_id' => $operator->id,
            'accion' => 'ESTADO_ACTUALIZADO',
        ]);
    }

    public function test_bodega_operator_can_permanently_delete_a_test_reservation_and_its_calendar_event(): void
    {
        Mail::fake();
        Http::fake([
            'https://login.microsoftonline.com/*/oauth2/v2.0/token' => Http::response(['access_token' => 'calendar-token'], 200),
            'https://graph.microsoft.com/v1.0/users/*/calendar/events' => Http::response(['id' => 'graph-event-delete'], 201),
            'https://graph.microsoft.com/v1.0/users/*/calendar/events/graph-event-delete' => Http::response(null, 204),
        ]);
        config([
            'services.reservas_vehiculos_calendar.tenant_id' => 'tenant-id',
            'services.reservas_vehiculos_calendar.client_id' => 'client-id',
            'services.reservas_vehiculos_calendar.client_secret' => 'client-secret',
            'services.reservas_vehiculos_calendar.enabled' => true,
            'services.reservas_vehiculos_calendar.mailbox' => 'reservas.vehiculos@saep.cl',
        ]);

        $role = Rol::where('codigo', 'BODEGA_VEHICULOS')->firstOrFail();
        $operator = User::create([
            'name' => 'Coordinador Bodega', 'email' => 'coordinador.delete@saep.cl', 'rol_id' => $role->id,
            'password' => bcrypt('secret'), 'activo' => true,
        ]);
        $vehiculo = Vehiculo::create([
            'patente' => 'DELT-01', 'marca' => 'Chevrolet', 'modelo' => 'Sail', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        $reserva = app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-04 09:00:00',
            'termino' => '2026-08-04 11:00:00',
            'motivo' => 'Reserva de prueba para eliminacion',
        ], ['oid' => 'delete-test', 'email' => 'prueba.delete@saep.cl', 'name' => 'Prueba Delete']);

        $this->actingAs($operator)
            ->get(route('gestion-vehiculos.index'))
            ->assertOk()
            ->assertSee('Eliminar reserva de prueba')
            ->assertSee('Codigo operativo')
            ->assertDontSee("@include('gestion_vehiculos._form'");

        $this->actingAs($operator)
            ->delete(route('gestion-vehiculos.reservas.destroy', $reserva))
            ->assertRedirect();

        $this->assertDatabaseMissing('reservas_vehiculos', ['id' => $reserva->id]);
        $this->assertDatabaseMissing('reserva_vehiculo_eventos', ['reserva_vehiculo_id' => $reserva->id]);
        $correoEliminacion = null;
        Mail::assertSent(ReservaVehiculoMail::class, function (ReservaVehiculoMail $mail) use ($operator, &$correoEliminacion): bool {
            $correoEliminacion = $mail;

            return $mail->tipo === 'eliminacion'
                && $mail->actor?->is($operator)
                && $mail->hasTo('prueba.delete@saep.cl');
        });
        $this->assertStringContainsString('Reserva eliminada', $correoEliminacion->render());
        $this->assertStringContainsString($operator->email, $correoEliminacion->render());
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'https://graph.microsoft.com/v1.0/users/reservas.vehiculos%40saep.cl/calendar/events/graph-event-delete');
    }

    public function test_reservation_is_preserved_when_its_outlook_event_cannot_be_deleted(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*/oauth2/v2.0/token' => Http::response(['access_token' => 'calendar-token'], 200),
            'https://graph.microsoft.com/v1.0/users/*/calendar/events' => Http::response(['id' => 'graph-event-failure'], 201),
            'https://graph.microsoft.com/v1.0/users/*/calendar/events/graph-event-failure' => Http::response(['error' => ['message' => 'Temporary Graph error']], 503),
        ]);
        config([
            'services.reservas_vehiculos_calendar.tenant_id' => 'tenant-id',
            'services.reservas_vehiculos_calendar.client_id' => 'client-id',
            'services.reservas_vehiculos_calendar.client_secret' => 'client-secret',
            'services.reservas_vehiculos_calendar.enabled' => true,
            'services.reservas_vehiculos_calendar.mailbox' => 'reservas.vehiculos@saep.cl',
        ]);

        $role = Rol::where('codigo', 'BODEGA_VEHICULOS')->firstOrFail();
        $operator = User::create([
            'name' => 'Coordinador Bodega', 'email' => 'coordinador.failure@saep.cl', 'rol_id' => $role->id,
            'password' => bcrypt('secret'), 'activo' => true,
        ]);
        $vehiculo = Vehiculo::create([
            'patente' => 'FALL-01', 'marca' => 'Chevrolet', 'modelo' => 'Sail', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        $reserva = app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-04 12:00:00',
            'termino' => '2026-08-04 14:00:00',
            'motivo' => 'Reserva que no debe quedar huerfana',
        ], ['oid' => 'failure-test', 'email' => 'prueba.failure@saep.cl', 'name' => 'Prueba Failure']);

        $this->actingAs($operator)
            ->delete(route('gestion-vehiculos.reservas.destroy', $reserva))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('reservas_vehiculos', ['id' => $reserva->id]);
        $this->assertDatabaseHas('reservas_vehiculos', [
            'id' => $reserva->id,
            'calendar_last_error' => 'Microsoft Graph respondio HTTP 503 al eliminar el evento.',
        ]);
    }

    public function test_corporate_portal_redirects_booking_without_a_microsoft_session(): void
    {
        $this->post(route('reservas-vehiculos.store'), [
            'vehiculo_id' => 1,
            'inicio' => '2026-08-03 09:00:00',
            'termino' => '2026-08-03 10:00:00',
            'motivo' => 'No debe llegar al guardado',
        ])->assertRedirect(route('reservas-vehiculos.inicio'));

        $this->assertDatabaseCount('reservas_vehiculos', 0);
    }

    public function test_microsoft_authorization_redirect_is_bound_to_the_corporate_tenant(): void
    {
        config([
            'services.reservas_vehiculos_microsoft.tenant_id' => 'tenant-saep-id',
            'services.reservas_vehiculos_microsoft.client_id' => 'client-saep-id',
            'services.reservas_vehiculos_microsoft.client_secret' => 'secret-for-test',
            'services.reservas_vehiculos_microsoft.allowed_domain' => 'saep.cl',
        ]);

        $response = $this->get(route('reservas-vehiculos.microsoft.redirect'));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://login.microsoftonline.com/tenant-saep-id/oauth2/v2.0/authorize?', $location);
        $this->assertStringContainsString('client_id=client-saep-id', $location);
        $this->assertStringContainsString('scope=openid%20profile%20email%20User.Read', $location);
    }

    public function test_microsoft_callback_rejects_an_external_account_with_a_clear_saep_domain_message(): void
    {
        config([
            'services.reservas_vehiculos_microsoft.tenant_id' => 'tenant-saep-id',
            'services.reservas_vehiculos_microsoft.client_id' => 'client-saep-id',
            'services.reservas_vehiculos_microsoft.client_secret' => 'secret-for-test',
            'services.reservas_vehiculos_microsoft.allowed_domain' => 'saep.cl',
        ]);
        Http::fake([
            'https://login.microsoftonline.com/tenant-saep-id/oauth2/v2.0/token' => Http::response(['access_token' => 'token-test'], 200),
            'https://graph.microsoft.com/v1.0/me*' => Http::response([
                'id' => 'external-user',
                'displayName' => 'Cuenta Externa',
                'mail' => 'persona@gmail.com',
            ], 200),
        ]);

        $this->withSession(['reserva_vehiculo_microsoft_state' => 'callback-state'])
            ->get(route('reservas-vehiculos.microsoft.callback', [
                'state' => 'callback-state',
                'code' => 'authorization-code',
            ]))
            ->assertRedirect(route('reservas-vehiculos.inicio'))
            ->assertSessionHas('error', 'Este portal de reservas esta disponible solo para cuentas corporativas SAEP con correo @saep.cl.');
    }

    public function test_reservation_processor_sends_reminder_and_marks_expired_reservation(): void
    {
        Mail::fake();
        $vehiculo = Vehiculo::create([
            'patente' => 'MAIL-01', 'marca' => 'Fiat', 'modelo' => 'Fiorino', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        $service = app(ReservaVehiculoService::class);
        $identity = ['oid' => 'mail-user', 'email' => 'mail.user@saep.cl', 'name' => 'Correo QA'];

        $recordatorio = $service->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-01 08:30:00',
            'termino' => '2026-08-01 09:00:00',
            'motivo' => 'Reserva para validar recordatorio',
        ], $identity);

        $resultadoRecordatorio = $service->procesarNotificaciones();
        $this->assertSame(1, $resultadoRecordatorio['recordatorios']);
        $this->assertNotNull($recordatorio->fresh()->recordatorio_enviado_at);
        Mail::assertSent(ReservaVehiculoMail::class, fn (ReservaVehiculoMail $mail) => $mail->tipo === 'recordatorio');

        Carbon::setTestNow(Carbon::parse('2026-08-01 10:00:00'));
        $resultadoVencimiento = $service->procesarNotificaciones();

        $this->assertSame(1, $resultadoVencimiento['vencidas']);
        $this->assertSame('VENCIDA', $recordatorio->fresh()->estado);
        Mail::assertSent(ReservaVehiculoMail::class, fn (ReservaVehiculoMail $mail) => $mail->tipo === 'vencimiento');
    }

    public function test_reservation_email_renders_vehicle_and_schedule_details(): void
    {
        $vehiculo = Vehiculo::where('patente', 'CGVC-41')->firstOrFail();
        $reserva = app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-03 09:00:00',
            'termino' => '2026-08-03 11:00:00',
            'motivo' => 'Traslado de equipo a centro operativo',
            'destino' => 'CD Quilicura',
        ], ['oid' => 'email', 'email' => 'correo@saep.cl', 'name' => 'Correo QA']);

        $html = (new ReservaVehiculoMail($reserva, 'confirmacion'))->render();

        $this->assertStringContainsString('Reserva confirmada', $html);
        $this->assertStringContainsString('CGVC-41', $html);
        $this->assertStringContainsString('CD Quilicura', $html);
        $this->assertStringContainsString($reserva->codigo, $html);
    }

    public function test_reservation_updates_notify_the_requester_and_vehicle_module_recipients(): void
    {
        Mail::fake();

        $role = Rol::where('codigo', 'BODEGA_VEHICULOS')->firstOrFail();
        $operator = User::create([
            'name' => 'Coordinador Bodega',
            'email' => 'coordinador.bodega@saep.cl',
            'rol_id' => $role->id,
            'password' => bcrypt('secret'),
            'activo' => true,
        ]);
        $vehiculo = Vehiculo::create([
            'patente' => 'AVIS-01', 'marca' => 'Fiat', 'modelo' => 'Fiorino', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        $reserva = app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-03 09:00:00',
            'termino' => '2026-08-03 11:00:00',
            'motivo' => 'Traslado para validar comunicacion',
        ], ['oid' => 'mail-notification', 'email' => 'solicitante.aviso@saep.cl', 'name' => 'Solicitante Aviso']);

        app(ReservaVehiculoService::class)->enviarActualizacion($reserva, 'actualizacion');

        Mail::assertSent(ReservaVehiculoMail::class, function (ReservaVehiculoMail $mail) use ($operator, $reserva) {
            return $mail->tipo === 'actualizacion'
                && $mail->hasTo($operator->email)
                && $mail->hasTo($reserva->solicitante_email);
        });
    }

    public function test_reservation_creates_an_event_in_the_shared_microsoft_calendar_when_enabled(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*/oauth2/v2.0/token' => Http::response(['access_token' => 'calendar-token'], 200),
            'https://graph.microsoft.com/v1.0/users/*/calendar/events' => Http::response(['id' => 'graph-event-123'], 201),
        ]);
        config([
            'services.microsoft_graph.tenant_id' => 'legacy-sharepoint-tenant',
            'services.reservas_vehiculos_calendar.tenant_id' => 'tenant-id',
            'services.reservas_vehiculos_calendar.client_id' => 'client-id',
            'services.reservas_vehiculos_calendar.client_secret' => 'client-secret',
            'services.reservas_vehiculos_calendar.enabled' => true,
            'services.reservas_vehiculos_calendar.mailbox' => 'reservas.vehiculos@saep.cl',
            'services.reservas_vehiculos_calendar.calendar_id' => null,
        ]);

        $vehiculo = Vehiculo::create([
            'patente' => 'CALS-01', 'marca' => 'Fiat', 'modelo' => 'Fiorino', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);

        $reserva = app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-03 09:00:00',
            'termino' => '2026-08-03 11:00:00',
            'motivo' => 'Traslado al centro de trabajo',
            'destino' => 'CD Quilicura',
        ], ['oid' => 'calendar-user', 'email' => 'calendar.user@saep.cl', 'name' => 'Calendario QA']);

        $this->assertSame('graph-event-123', $reserva->calendar_event_id);
        $this->assertNotNull($reserva->calendar_synced_at);
        $this->assertDatabaseHas('reserva_vehiculo_eventos', [
            'reserva_vehiculo_id' => $reserva->id,
            'accion' => 'CALENDARIO_SINCRONIZADO',
        ]);
        Http::assertSent(function ($request) use ($reserva) {
            return $request->method() === 'POST'
                && $request->url() === 'https://graph.microsoft.com/v1.0/users/reservas.vehiculos%40saep.cl/calendar/events'
                && $request['subject'] === $reserva->codigo.' · CALS-01';
        });
        Http::assertSent(fn ($request) => $request->url() === 'https://login.microsoftonline.com/tenant-id/oauth2/v2.0/token');
    }

    public function test_bodega_prepares_a_prefilled_kizeo_delivery_sheet_without_changing_the_reservation_status(): void
    {
        Http::fake([
            'https://www.kizeoforms.com/rest/v3/forms/1165545/push' => Http::response([
                'status' => 'ok',
                'data' => ['id' => 'kizeo-push-123'],
            ], 200),
        ]);
        config([
            'services.kizeo.vehicle_form_id' => '1165545',
            'services.kizeo.vehicle_recipient_user_id' => '657579',
            'services.kizeo.vehicle_reservation_code_field' => 'codigo_de_reserva_saep',
        ]);

        $role = Rol::where('codigo', 'BODEGA_VEHICULOS')->firstOrFail();
        $operator = User::create([
            'name' => 'Bodega Kizeo',
            'email' => 'bodega.kizeo@saep.cl',
            'rol_id' => $role->id,
            'password' => bcrypt('secret'),
            'activo' => true,
        ]);
        $vehiculo = Vehiculo::create([
            'patente' => 'KIZE-01', 'marca' => 'Fiat', 'modelo' => 'Fiorino', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        $reserva = app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-03 09:00:00',
            'termino' => '2026-08-03 11:00:00',
            'motivo' => 'Traslado para validar ficha Kizeo',
        ], ['oid' => 'kizeo-test', 'email' => 'kizeo.test@saep.cl', 'name' => 'Kizeo QA']);

        $preparada = app(ReservaVehiculoKizeoService::class)->prepararActa($reserva, $operator);

        $this->assertSame('CONFIRMADA', $preparada->estado);
        $this->assertSame('1165545', $preparada->kizeo_form_id);
        $this->assertSame('kizeo-push-123', $preparada->kizeo_data_id);
        $this->assertNotNull($preparada->kizeo_pushed_at);
        $this->assertDatabaseHas('reserva_vehiculo_eventos', [
            'reserva_vehiculo_id' => $reserva->id,
            'accion' => 'KIZEO_ACTA_PREPARADA',
        ]);
        Http::assertSent(function ($request) use ($reserva) {
            return $request->method() === 'POST'
                && $request->url() === 'https://www.kizeoforms.com/rest/v3/forms/1165545/push'
                && $request['recipient_user_id'] === 657579
                && $request['fields']['codigo_de_reserva_saep']['value'] === $reserva->codigo
                && $request['fields']['gestion']['value'] === 'Entrega a Conductor'
                && $request['fields']['marca_modelo']['value'] === 'Fiat - Fiorino';
        });
    }

    public function test_kizeo_delivery_and_return_update_the_matching_reservation_idempotently(): void
    {
        $vehiculo = Vehiculo::create([
            'patente' => 'BACK-01', 'marca' => 'Chevrolet', 'modelo' => 'Sail', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        $reserva = app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-03 09:00:00',
            'termino' => '2026-08-03 11:00:00',
            'motivo' => 'Traslado para validar retorno de Kizeo',
        ], ['oid' => 'kizeo-return', 'email' => 'return.test@saep.cl', 'name' => 'Retorno QA']);

        $service = app(ReservaVehiculoKizeoService::class);
        $entrega = $service->registrarActaRecibida(
            '1165545',
            'kizeo-record-321',
            $reserva->codigo,
            'Entrega',
            '2026-08-03 09:15',
            'Actas Vehiculos/CGVC-41/Entrega.pdf',
        );

        $this->assertSame('estado_actualizado', $entrega['estado']);
        $this->assertSame('EN_USO', $entrega['reserva']->estado);
        $this->assertSame('kizeo-record-321', $entrega['reserva']->kizeo_data_id);
        $this->assertNotNull($entrega['reserva']->entregada_at);

        $reintento = $service->registrarActaRecibida(
            '1165545',
            'kizeo-record-321',
            $reserva->codigo,
            'Entrega',
            '2026-08-03 09:15',
            'Actas Vehiculos/CGVC-41/Entrega.pdf',
        );

        $this->assertSame('registrada', $reintento['estado']);
        $this->assertDatabaseCount('reserva_vehiculo_eventos', 2);

        $devolucion = $service->registrarActaRecibida(
            '1165545',
            'kizeo-record-321',
            $reserva->codigo,
            'Devolucion',
            '2026-08-03 10:50',
            'Actas Vehiculos/CGVC-41/Devolucion.pdf',
        );

        $this->assertSame('estado_actualizado', $devolucion['estado']);
        $this->assertSame('DEVUELTA', $devolucion['reserva']->estado);
        $this->assertNotNull($devolucion['reserva']->devuelta_at);
        $this->assertDatabaseHas('reservas_vehiculos', [
            'id' => $reserva->id,
            'kizeo_devolucion_sharepoint_path' => 'Actas Vehiculos/CGVC-41/Devolucion.pdf',
        ]);
        $this->assertDatabaseCount('reserva_vehiculo_eventos', 3);
    }

    public function test_vehicle_management_renders_the_kizeo_preparation_action_for_a_confirmed_reservation(): void
    {
        config([
            'services.kizeo.vehicle_form_id' => '1165545',
            'services.kizeo.vehicle_recipient_user_id' => '657579',
            'services.kizeo.vehicle_reservation_code_field' => 'codigo_de_reserva_saep',
        ]);

        $role = Rol::where('codigo', 'BODEGA_VEHICULOS')->firstOrFail();
        $operator = User::create([
            'name' => 'Bodega Visual',
            'email' => 'bodega.visual@saep.cl',
            'rol_id' => $role->id,
            'password' => bcrypt('secret'),
            'activo' => true,
        ]);
        $vehiculo = Vehiculo::create([
            'patente' => 'VIEW-01', 'marca' => 'Fiat', 'modelo' => 'Fiorino', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        $reserva = app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-03 09:00:00',
            'termino' => '2026-08-03 11:00:00',
            'motivo' => 'Reserva para revisar la gestion visual',
        ], ['oid' => 'kizeo-view', 'email' => 'view.test@saep.cl', 'name' => 'Vista QA']);

        $this->actingAs($operator)
            ->get(route('gestion-vehiculos.index'))
            ->assertOk()
            ->assertSee($reserva->codigo)
            ->assertSee('Pendiente de preparar')
            ->assertSee('Preparar ficha de entrega en Kizeo para Bodega');
    }

    public function test_bodega_can_review_managed_reservations_and_open_each_archived_act(): void
    {
        $role = Rol::where('codigo', 'BODEGA_VEHICULOS')->firstOrFail();
        $operator = User::create([
            'name' => 'Bodega Actas',
            'email' => 'bodega.actas@saep.cl',
            'rol_id' => $role->id,
            'password' => bcrypt('secret'),
            'activo' => true,
        ]);
        $vehiculo = Vehiculo::create([
            'patente' => 'ACTA-01', 'marca' => 'Fiat', 'modelo' => 'Fiorino', 'estado' => 'DISPONIBLE', 'reservas_habilitadas' => true,
        ]);
        $reserva = app(ReservaVehiculoService::class)->crearReserva([
            'vehiculo_id' => $vehiculo->id,
            'inicio' => '2026-08-03 09:00:00',
            'termino' => '2026-08-03 11:00:00',
            'motivo' => 'Revisión de actas firmadas',
        ], ['oid' => 'acta-test', 'email' => 'acta.test@saep.cl', 'name' => 'Actas QA']);
        $reserva->update([
            'estado' => 'DEVUELTA',
            'kizeo_data_id' => 'kizeo-acta-123',
            'kizeo_entrega_sharepoint_path' => 'ACTA-01/Entrega.pdf',
            'kizeo_devolucion_sharepoint_path' => 'ACTA-01/Devolucion.pdf',
        ]);

        $this->actingAs($operator)
            ->get(route('gestion-vehiculos.index'))
            ->assertOk()
            ->assertSee('Reservas gestionadas y actas')
            ->assertSee($reserva->codigo)
            ->assertSee('Entrega')
            ->assertSee('Devolución');

        $oneDrive = Mockery::mock(OneDriveService::class);
        $oneDrive->shouldReceive('downloadFile')
            ->once()
            ->with('ACTA-01/Entrega.pdf')
            ->andReturn('%PDF-1.4 acta entrega');
        $this->app->instance(OneDriveService::class, $oneDrive);

        $this->actingAs($operator)
            ->get(route('gestion-vehiculos.reservas.acta', [$reserva, 'entrega']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertSee('%PDF-1.4 acta entrega');
    }
}
