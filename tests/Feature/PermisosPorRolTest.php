<?php

namespace Tests\Feature;

use App\Models\ConsentimientoDatos;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\User;
use App\Support\PrivacyPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PermisosPorRolTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_permissions_are_forced_on_when_permission_matrix_is_saved(): void
    {
        $superAdmin = Rol::where('codigo', 'SUPER_ADMIN')->firstOrFail();
        $modulo = Modulo::where('activo', true)->firstOrFail();

        DB::table('rol_modulo')->updateOrInsert(
            ['rol_id' => $superAdmin->id, 'modulo_id' => $modulo->id],
            [
                'puede_ver' => false,
                'puede_crear' => false,
                'puede_editar' => false,
                'puede_eliminar' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $user = User::create([
            'name' => 'Super Admin Permisos Test',
            'email' => 'permisos-superadmin-' . uniqid() . '@saep.local',
            'rol_id' => $superAdmin->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
            'must_change_password' => false,
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
            ->put(route('permisos.update'), ['permisos' => []])
            ->assertRedirect(route('permisos.index'));

        $this->assertDatabaseHas('rol_modulo', [
            'rol_id' => $superAdmin->id,
            'modulo_id' => $modulo->id,
            'puede_ver' => true,
            'puede_crear' => true,
            'puede_editar' => true,
            'puede_eliminar' => true,
        ]);
    }
}
