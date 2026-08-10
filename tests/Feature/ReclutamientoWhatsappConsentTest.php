<?php

namespace Tests\Feature;

use App\Models\ReclutamientoWhatsappContacto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReclutamientoWhatsappConsentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');
        foreach ([
            'reclutamiento_whatsapp_eventos',
            'reclutamiento_whatsapp_mensajes',
            'reclutamiento_whatsapp_conversaciones',
            'registro_tratamiento_datos',
            'reclutamiento_whatsapp_contactos',
        ] as $table) {
            $schema->dropIfExists($table);
        }

        $schema->create('reclutamiento_whatsapp_contactos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('telefono', 20)->unique();
            $table->string('email', 200)->nullable();
            $table->string('origen', 40)->default('manual');
            $table->string('origen_detalle', 160)->nullable();
            $table->boolean('consentimiento_whatsapp')->default(false);
            $table->timestamp('consentimiento_aceptado_at')->nullable();
            $table->string('consentimiento_origen', 120)->nullable();
            $table->text('consentimiento_texto')->nullable();
            $table->string('consentimiento_finalidad', 80)->nullable();
            $table->string('consentimiento_version', 50)->nullable();
            $table->string('consentimiento_evidencia_ref', 500)->nullable();
            $table->timestamp('consentimiento_verificado_at')->nullable();
            $table->unsignedBigInteger('consentimiento_verificado_por')->nullable();
            $table->date('retencion_hasta')->nullable();
            $table->string('consentimiento_ip', 45)->nullable();
            $table->text('consentimiento_user_agent')->nullable();
            $table->timestamp('consentimiento_revocado_at')->nullable();
            $table->string('motivo_revocacion', 200)->nullable();
            $table->timestamps();
        });

        $schema->create('reclutamiento_whatsapp_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contacto_id');
            $table->string('estado', 30)->default('nueva');
            $table->unsignedBigInteger('asignada_a')->nullable();
            $table->text('ultimo_mensaje_preview')->nullable();
            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->timestamp('ultimo_mensaje_entrante_at')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();
        });

        $schema->create('reclutamiento_whatsapp_mensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversacion_id');
            $table->string('direccion', 20);
            $table->string('tipo', 30)->default('texto');
            $table->string('meta_message_id', 255)->nullable()->unique();
            $table->text('contenido')->nullable();
            $table->unsignedBigInteger('enviado_por')->nullable();
            $table->string('estado', 30)->default('recibido');
            $table->timestamp('ocurrido_at')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();
        });

        $schema->create('reclutamiento_whatsapp_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('meta_event_id', 255)->nullable();
            $table->string('tipo', 50);
            $table->json('datos')->nullable();
            $table->timestamp('ocurrido_at')->nullable();
            $table->timestamps();
        });

        $schema->create('registro_tratamiento_datos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('accion', 50);
            $table->string('tabla_afectada', 100);
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->string('tipo_dato', 100);
            $table->text('descripcion')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->timestamps();
        });
    }

    public function test_only_contacts_with_complete_current_consent_are_eligible_for_their_authorized_purpose(): void
    {
        $elegible = $this->crearContactoElegible([
            'telefono' => '+56911111111',
            'consentimiento_finalidad' => 'convocatorias_laborales',
        ]);
        $finalidadDistinta = $this->crearContactoElegible([
            'telefono' => '+56922222222',
            'consentimiento_finalidad' => 'seguimiento_postulacion',
        ]);
        $vencido = $this->crearContactoElegible([
            'telefono' => '+56933333333',
            'retencion_hasta' => today()->subDay(),
        ]);
        $sinEvidencia = $this->crearContactoElegible([
            'telefono' => '+56944444444',
            'consentimiento_evidencia_ref' => null,
        ]);
        $conBaja = $this->crearContactoElegible([
            'telefono' => '+56955555555',
            'consentimiento_whatsapp' => false,
            'consentimiento_revocado_at' => now(),
        ]);

        $this->assertTrue($elegible->puedeRecibirCampanias('convocatorias_laborales'));
        $this->assertFalse($finalidadDistinta->puedeRecibirCampanias('convocatorias_laborales'));
        $this->assertFalse($vencido->puedeRecibirCampanias());
        $this->assertFalse($sinEvidencia->puedeRecibirCampanias());
        $this->assertFalse($conBaja->puedeRecibirCampanias());

        $ids = ReclutamientoWhatsappContacto::elegiblesParaCampanias('convocatorias_laborales')
            ->pluck('id')
            ->all();

        $this->assertSame([$elegible->id], $ids);
    }

    public function test_clear_opt_out_message_revokes_future_campaign_eligibility(): void
    {
        config()->set('services.whatsapp.app_secret', 'test-webhook-secret');
        $contacto = $this->crearContactoElegible([
            'telefono' => '+56998765432',
        ]);

        $payload = [
            'entry' => [[
                'id' => 'entry-test-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'contacts' => [[
                            'wa_id' => '56998765432',
                            'profile' => ['name' => 'Contacto de prueba'],
                        ]],
                        'messages' => [[
                            'from' => '56998765432',
                            'id' => 'wamid.test-opt-out',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'BAJA'],
                        ]],
                    ],
                ]],
            ]],
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', route('reclutamiento-whatsapp.webhook.handle'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $json, 'test-webhook-secret'),
        ], $json)
            ->assertOk();

        $contacto->refresh();

        $this->assertFalse($contacto->consentimiento_whatsapp);
        $this->assertNotNull($contacto->consentimiento_revocado_at);
        $this->assertSame('Solicitud de baja recibida por WhatsApp', $contacto->motivo_revocacion);
        $this->assertFalse($contacto->puedeRecibirCampanias());
    }

    public function test_meta_webhook_verification_returns_the_challenge_for_the_configured_token(): void
    {
        config()->set('services.whatsapp.verify_token', 'meta-verification-test');

        $this->get(route('reclutamiento-whatsapp.webhook.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'meta-verification-test',
            'hub_challenge' => 'challenge-123',
        ]))
            ->assertOk()
            ->assertSeeText('challenge-123');
    }

    private function crearContactoElegible(array $overrides = []): ReclutamientoWhatsappContacto
    {
        return ReclutamientoWhatsappContacto::create(array_merge([
            'nombre' => 'Postulante de prueba',
            'telefono' => '+56900000000',
            'origen' => 'manual',
            'origen_detalle' => 'Prueba automatizada',
            'consentimiento_whatsapp' => true,
            'consentimiento_aceptado_at' => now()->subDay(),
            'consentimiento_origen' => 'Formulario de consentimiento',
            'consentimiento_texto' => 'Autorizo comunicaciones de reclutamiento por WhatsApp.',
            'consentimiento_finalidad' => 'convocatorias_laborales',
            'consentimiento_version' => 'RRHH-WA-TEST-1',
            'consentimiento_evidencia_ref' => 'consent-test-001',
            'consentimiento_verificado_at' => now(),
            'consentimiento_verificado_por' => 1,
            'retencion_hasta' => today()->addMonths(6),
        ], $overrides));
    }
}
