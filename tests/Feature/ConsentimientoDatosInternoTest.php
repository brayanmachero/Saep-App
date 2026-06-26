<?php

namespace Tests\Feature;

use App\Models\ConsentimientoDatos;
use App\Models\RegistroTratamientoDatos;
use App\Models\Rol;
use App\Models\User;
use App\Support\PrivacyPolicy;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsentimientoDatosInternoTest extends TestCase
{
    private array $userIds = [];

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            RegistroTratamientoDatos::where('tabla_afectada', 'consentimientos_datos')
                ->whereIn('user_id', $this->userIds)
                ->delete();

            ConsentimientoDatos::whereIn('user_id', $this->userIds)->delete();
            User::whereIn('id', $this->userIds)->forceDelete();
        }

        parent::tearDown();
    }

    public function test_boolean_legacy_consent_is_not_enough_without_auditable_record(): void
    {
        $user = $this->createConsentTestUser([
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('proteccion-datos.consentimiento'));
    }

    public function test_active_consent_record_allows_protected_navigation(): void
    {
        $user = $this->createConsentTestUser([
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
        ]);

        ConsentimientoDatos::create([
            'user_id' => $user->id,
            'version_politica' => PrivacyPolicy::VERSION,
            'texto_aceptado' => PrivacyPolicy::internalConsentText(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'fecha_aceptacion' => now(),
            'vigente' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_accepting_policy_creates_active_auditable_consent(): void
    {
        $user = $this->createConsentTestUser();

        $this->actingAs($user)
            ->post(route('proteccion-datos.aceptar-politica'))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('consentimientos_datos', [
            'user_id' => $user->id,
            'version_politica' => PrivacyPolicy::VERSION,
            'vigente' => true,
        ]);

        $this->assertTrue($user->fresh()->tieneConsentimientoDatosVigente());
    }

    private function createConsentTestUser(array $overrides = []): User
    {
        $role = Rol::where('codigo', 'SUPER_ADMIN')->firstOrFail();

        $user = User::create(array_merge([
            'name' => 'Usuario Test Consentimiento',
            'email' => 'consentimiento-test-' . uniqid() . '@saep.local',
            'rol_id' => $role->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
            'acepta_politica_datos' => false,
            'fecha_aceptacion_politica' => null,
            'must_change_password' => false,
        ], $overrides));

        $this->userIds[] = $user->id;

        return $user;
    }
}
