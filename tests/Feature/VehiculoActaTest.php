<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Jobs\NotificarVehiculoActa;
use App\Mail\VehiculoActaMail;
use App\Models\ReservaVehiculo;
use App\Models\Rol;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoActa;
use App\Services\VehiculoActaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehiculoActaTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private ReservaVehiculo $reserva;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ForcePasswordChange::class, VerificarConsentimientoDatos::class]);
        config(['vehiculos_actas.enabled' => true, 'vehiculos_actas.mail_enabled' => false, 'vehiculos_actas.calendar_enabled' => false]);
        Http::preventStrayRequests(); Mail::fake(); Storage::fake('local');
        $this->operator = User::create(['name' => 'Bodega prueba', 'email' => 'bodega@example.test', 'password' => bcrypt('test'), 'activo' => true, 'rol_id' => Rol::where('codigo', 'BODEGA_VEHICULOS')->firstOrFail()->id]);
        $vehicle = Vehiculo::firstOrFail();
        $this->reserva = ReservaVehiculo::create(['codigo' => 'RV-TEST', 'vehiculo_id' => $vehicle->id, 'solicitante_nombre' => 'Conductor Prueba', 'solicitante_email' => 'conductor@example.test', 'inicio' => now()->subMinutes(5), 'termino' => now()->addHours(2), 'motivo' => 'Prueba', 'estado' => 'CONFIRMADA']);
        $this->actingAs($this->operator);
    }

    private function signature(): string
    {
        $im = imagecreatetruecolor(500, 150); imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagesetthickness($im, 4); $ink = imagecolorallocate($im, 20, 30, 50);
        imageline($im, 30, 100, 220, 30, $ink); imageline($im, 220, 30, 350, 120, $ink);
        ob_start(); imagepng($im); $bytes = ob_get_clean(); imagedestroy($im);
        return 'data:image/png;base64,'.base64_encode($bytes);
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'accion' => 'preparar', 'revision' => 0, 'conductor_nombre' => 'Conductor Prueba', 'conductor_email' => 'conductor@example.test',
            'fecha' => now()->subMinute()->format('Y-m-d H:i'), 'kilometraje' => 1000, 'combustible' => 'Lleno',
            'condiciones' => array_fill_keys(array_keys(VehiculoActaService::PARTES), 'Bueno'),
            'kit' => array_fill_keys(array_keys(VehiculoActaService::KIT), 'Presente'), 'observaciones' => 'Inspección de prueba',
            'firma_encargado' => $this->signature(), 'fotos' => [UploadedFile::fake()->image('vehiculo.jpg', 400, 300)],
        ], $overrides);
    }

    public function test_reservation_identity_cannot_be_replaced_from_delivery_phone(): void
    {
        $this->post(route('vehiculos-actas.guardar', [$this->reserva, 'entrega']), $this->data([
            'conductor_nombre' => 'Persona diferente', 'conductor_email' => 'otro@example.test',
        ]))->assertSessionHasNoErrors();
        $acta = VehiculoActa::firstOrFail();
        $this->assertSame($this->reserva->solicitante_nombre, $acta->datos['conductor_nombre']);
        $this->assertSame($this->reserva->solicitante_email, $acta->datos['conductor_email']);
        $this->sign($acta)->assertSessionHasNoErrors();
        config(['vehiculos_actas.mail_enabled' => true]);
        (new NotificarVehiculoActa($acta->id))->handle();
        Mail::assertSent(VehiculoActaMail::class, fn ($mail) => $mail->hasTo($this->reserva->solicitante_email) && !$mail->hasTo('otro@example.test'));
    }

    public function test_legacy_in_use_reservation_can_close_without_fabricating_a_signed_acta(): void
    {
        $this->reserva->update(['estado' => 'EN_USO', 'entregada_at' => now()->subHour()]);
        config(['services.vehicle_reservations.calendar.enabled' => false]);
        $this->patch(route('gestion-vehiculos.reservas.update', $this->reserva), ['estado' => 'DEVUELTA'])->assertSessionHasNoErrors();
        $this->assertSame('DEVUELTA', $this->reserva->fresh()->estado);
        $this->assertSame(0, VehiculoActa::count());
    }

    public function test_pdf_photo_is_bounded_jpeg_without_changing_original_evidence(): void
    {
        $im = imagecreatetruecolor(3200, 2400);
        imagefill($im, 0, 0, imagecolorallocate($im, 130, 170, 200));
        ob_start(); imagepng($im); $original = ob_get_clean(); imagedestroy($im);
        Storage::disk('local')->put('evidence.png', $original);
        $url = app(\App\Services\VehiculoActaDocumentService::class)->fotoPdf('evidence.png');
        $bytes = base64_decode(explode(',', $url, 2)[1]);
        $size = getimagesizefromstring($bytes);
        $this->assertSame(IMAGETYPE_JPEG, $size[2]);
        $this->assertSame([1600, 1200], [$size[0], $size[1]]);
        $this->assertLessThan(500000, strlen($bytes));
        $this->assertSame(hash('sha256', $original), hash('sha256', Storage::disk('local')->get('evidence.png')));
    }

    private function prepare(): VehiculoActa
    {
        $this->post(route('vehiculos-actas.guardar', [$this->reserva, 'entrega']), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        return VehiculoActa::firstOrFail();
    }

    private function sign(VehiculoActa $acta, array $overrides = [])
    {
        return $this->post(route('vehiculos-actas.firmar', $acta), array_replace(['contenido_hash' => $acta->contenido_hash, 'aceptacion' => '1', 'presencial' => '1', 'nombre_firmante' => 'Conductor Prueba', 'firma_conductor' => $this->signature()], $overrides));
    }

    public function test_full_local_cycle_generates_private_evidence_and_pdf_without_external_calls(): void
    {
        $this->get(route('vehiculos-actas.editar', [$this->reserva, 'entrega']))->assertOk()->assertSee('Entregar vehículo');
        $acta = $this->prepare();
        $this->assertSame('CONFIRMADA', $this->reserva->fresh()->estado);
        $this->get(route('vehiculos-actas.show', $acta))->assertOk()->assertSee('Revisar y firmar consentimiento');
        $this->sign($acta)->assertSessionHasNoErrors();
        $this->assertSame('EN_USO', $this->reserva->fresh()->estado);
        $acta->refresh(); $this->assertSame('FIRMADA', $acta->estado);
        $this->get(route('vehiculos-actas.pdf', $acta))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('vehiculos-actas.correo', $acta))->assertOk()->assertSee('Entrega de vehículo confirmada');
        $this->get(route('vehiculos-actas.paquete', $acta))->assertOk();
        $this->post(route('vehiculos-actas.guardar', [$this->reserva, 'devolucion']), $this->data(['kilometraje' => 1020, 'danos_nuevos' => 'NO']))->assertSessionHasNoErrors();
        $retorno = VehiculoActa::where('tipo', 'devolucion')->firstOrFail();
        $this->sign($retorno)->assertSessionHasNoErrors();
        $this->assertSame('DEVUELTA', $this->reserva->fresh()->estado);
        $this->get(route('vehiculos-actas.show', $retorno))->assertOk()->assertSee('Devolución confirmada');
        Mail::assertNothingSent(); Http::assertNothingSent();
    }

    public function test_missing_evidence_or_blank_signature_cannot_prepare_an_act(): void
    {
        $this->post(route('vehiculos-actas.guardar', [$this->reserva, 'entrega']), $this->data(['fotos' => []]))->assertSessionHasErrors('fotos');
        $this->post(route('vehiculos-actas.guardar', [$this->reserva, 'entrega']), $this->data(['firma_encargado' => '']))->assertSessionHasErrors('firma_encargado');
        $this->assertDatabaseCount('vehiculo_actas', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_draft_can_be_resumed_but_stale_revision_is_rejected(): void
    {
        $this->post(route('vehiculos-actas.guardar', [$this->reserva, 'entrega']), $this->data(['accion' => 'borrador', 'fotos' => [], 'firma_encargado' => '']))->assertSessionHasNoErrors();
        $this->assertSame('BORRADOR', VehiculoActa::first()->estado);
        $this->post(route('vehiculos-actas.guardar', [$this->reserva, 'entrega']), $this->data())->assertSessionHasErrors('acta');
        $this->post(route('vehiculos-actas.guardar', [$this->reserva, 'entrega']), $this->data(['revision' => 1]))->assertSessionHasNoErrors();
    }

    public function test_acceptance_is_required_and_final_act_cannot_be_signed_twice_or_reopened(): void
    {
        $acta = $this->prepare();
        $this->sign($acta, ['aceptacion' => '0'])->assertSessionHasErrors('aceptacion');
        $this->sign($acta, ['contenido_hash' => str_repeat('0', 64)])->assertSessionHasErrors('acta');
        $this->sign($acta)->assertSessionHasNoErrors();
        $hash = $acta->fresh()->documento_hash;
        $this->sign($acta)->assertSessionHasErrors('acta');
        $this->post(route('vehiculos-actas.reabrir', $acta))->assertSessionHasErrors('acta');
        $this->assertSame($hash, $acta->fresh()->documento_hash);
    }

    public function test_reopening_invalidates_previous_signature_page(): void
    {
        $acta = $this->prepare();
        $this->post(route('vehiculos-actas.reabrir', $acta))->assertSessionHasNoErrors();
        $this->sign($acta)->assertSessionHasErrors('acta');
        $this->assertNull($acta->fresh()->firma_encargado_path);
    }

    public function test_return_rejects_lower_mileage_and_requires_a_signed_delivery(): void
    {
        $this->post(route('vehiculos-actas.guardar', [$this->reserva, 'devolucion']), $this->data(['danos_nuevos' => 'NO']))->assertSessionHasErrors('acta');
        $acta = $this->prepare(); $this->sign($acta);
        $this->post(route('vehiculos-actas.guardar', [$this->reserva, 'devolucion']), $this->data(['kilometraje' => 999, 'danos_nuevos' => 'NO']))->assertSessionHasErrors('kilometraje');
    }

    public function test_portal_only_allows_the_named_driver_to_read_media_and_sign(): void
    {
        $acta = $this->prepare();
        auth()->logout();
        $this->withSession(['reserva_vehiculo_microsoft_identity' => ['email' => 'other@example.test']])->get(route('portal-actas.show', $acta))->assertForbidden();
        $this->get(route('portal-actas.media', [$acta, '0']))->assertForbidden();
        $this->withSession(['reserva_vehiculo_microsoft_identity' => ['email' => 'conductor@example.test']])->get(route('portal-actas.show', $acta))->assertOk();
        $this->post(route('portal-actas.firmar', $acta), ['contenido_hash' => $acta->contenido_hash, 'aceptacion' => '1', 'nombre_firmante' => 'Conductor Prueba', 'firma_conductor' => $this->signature()])->assertSessionHasNoErrors();
        $this->assertSame('MICROSOFT_CORPORATIVO', $acta->fresh()->auditoria_firma['modalidad']);
    }

    public function test_existing_act_prevents_deletion_reprogramming_and_manual_status_override(): void
    {
        $acta = $this->prepare();
        $this->delete(route('gestion-vehiculos.reservas.destroy', $this->reserva))->assertSessionHas('error');
        $this->patch(route('gestion-vehiculos.reservas.update', $this->reserva), ['estado' => 'DEVUELTA'])->assertSessionHasErrors('estado');
        $this->assertDatabaseHas('vehiculo_actas', ['id' => $acta->id, 'estado' => 'PENDIENTE_FIRMA']);
    }

    public function test_second_delivery_is_blocked_while_vehicle_is_in_custody(): void
    {
        $acta = $this->prepare();
        ReservaVehiculo::create(['codigo' => 'RV-OTHER', 'vehiculo_id' => $this->reserva->vehiculo_id, 'solicitante_nombre' => 'Otro', 'solicitante_email' => 'otro@example.test', 'inicio' => now()->subHours(3), 'termino' => now()->subHour(), 'motivo' => 'Anterior', 'estado' => 'VENCIDA', 'entregada_at' => now()->subHours(3)]);
        $this->sign($acta)->assertSessionHasErrors('acta');
        $this->assertSame('CONFIRMADA', $this->reserva->fresh()->estado);
    }

    public function test_email_is_idempotent_and_copies_bodega_when_explicitly_enabled(): void
    {
        $acta = $this->prepare(); $this->sign($acta); $acta->refresh();
        config(['vehiculos_actas.mail_enabled' => true]);
        (new NotificarVehiculoActa($acta->id))->handle();
        (new NotificarVehiculoActa($acta->id))->handle();
        Mail::assertSent(VehiculoActaMail::class, fn ($m) => $m->hasTo('conductor@example.test') && $m->hasCc('bodega@example.test'));
        Mail::assertSentCount(1);
    }

    public function test_disabled_feature_and_non_bodega_users_cannot_access_actas(): void
    {
        $acta = $this->prepare();
        $role = Rol::create(['codigo' => 'SIN_BODEGA', 'nombre' => 'Sin Bodega']);
        $other = User::create(['name' => 'Sin acceso', 'email' => 'no@example.test', 'password' => bcrypt('test'), 'activo' => true, 'rol_id' => $role->id]);
        $this->actingAs($other)->get(route('vehiculos-actas.show', $acta))->assertForbidden();
        $this->get(route('vehiculos-actas.media', [$acta, '0']))->assertForbidden();
        config(['vehiculos_actas.enabled' => false]);
        $this->actingAs($this->operator)->get(route('vehiculos-actas.show', $acta))->assertNotFound();
    }
}
