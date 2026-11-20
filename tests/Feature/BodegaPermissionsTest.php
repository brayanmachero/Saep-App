<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Models\Rol;
use App\Models\User;
use App\Models\ReservaVehiculo;
use App\Models\Vehiculo;
use App\Services\ReservaVehiculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BodegaPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([VerificarConsentimientoDatos::class, ForcePasswordChange::class]);
        Http::preventStrayRequests();
        Mail::fake();
    }

    private function user(string $role, string $email): User
    {
        return User::create(['name'=>'Prueba Bodega', 'email'=>$email, 'activo'=>true,
            'password'=>bcrypt('test'), 'rol_id'=>Rol::firstOrCreate(['codigo'=>$role], ['nombre'=>$role])->id]);
    }

    public function test_operational_roles_see_requests_and_vehicles_but_cannot_open_inventory_or_deliveries(): void
    {
        $reservation = ReservaVehiculo::create(['codigo'=>'RV-PERMISOS','vehiculo_id'=>Vehiculo::firstOrFail()->id,
            'solicitante_nombre'=>'Prueba','solicitante_email'=>'prueba@saep.cl','inicio'=>now()->addDay(),
            'termino'=>now()->addDay()->addHour(),'motivo'=>'Prueba','estado'=>'CONFIRMADA']);
        foreach (['BODEGA_ENTREGAS','BODEGA_VEHICULOS'] as $code) {
            $user=$this->user($code, strtolower($code).'@saep.cl');
            $this->assertTrue($user->tieneAcceso('solicitudes_epp','puede_editar'));
            $this->assertTrue($user->tieneAcceso('gestion_vehiculos','puede_eliminar'));
            $this->actingAs($user)->get(route('inventario-bodega.epp.index'))->assertOk()
                ->assertSee('Solicitudes de EPP')->assertSee('Vehiculos')
                ->assertDontSee('href="'.route('inventario-bodega.index').'"',false)
                ->assertDontSee('href="'.route('entregas-bodega-dashboard.index').'"',false);
            $this->get(route('inventario-bodega.index'))->assertForbidden();
            $this->get(route('entregas-bodega-dashboard.index'))->assertForbidden();
            $this->get(route('gestion-vehiculos.index'))->assertOk()->assertSee('Confirmar eliminación');
            $this->patch(route('inventario-bodega.epp.manage',999999),['revision'=>0])->assertNotFound();
        }
    }

    public function test_gabriela_is_assigned_full_bodega_access_and_remains_in_mail_recipients(): void
    {
        $gabriela=$this->user('BODEGA_ENTREGAS','ginfante@saep.cl');
        (require database_path('migrations/2026_11_20_170000_separate_bodega_epp_permissions.php'))->up();
        $gabriela->refresh();
        $this->assertSame('BODEGA_JEFATURA',$gabriela->rol->codigo);
        foreach(['inventario_bodega','entregas_bodega_dashboard','solicitudes_epp','gestion_vehiculos'] as $module) {
            foreach(['puede_ver','puede_crear','puede_editar','puede_eliminar'] as $action) {
                $this->assertTrue($gabriela->tieneAcceso($module,$action));
            }
        }
        $this->actingAs($gabriela)->get(route('inventario-bodega.epp.index'))->assertOk()
            ->assertSee('href="'.route('inventario-bodega.index').'"',false)
            ->assertSee('href="'.route('entregas-bodega-dashboard.index').'"',false);
        $this->assertTrue(app(ReservaVehiculoService::class)->destinatariosGestionBodega()->contains('id',$gabriela->id));
        $this->assertFalse($gabriela->esSuperAdmin());
    }

    public function test_operational_login_does_not_redirect_to_revoked_deliveries_page(): void
    {
        $this->user('BODEGA_VEHICULOS','operador@saep.cl');
        $this->withSession(['url.intended'=>route('entregas-bodega-dashboard.index')])
            ->post('/login',['email'=>'operador@saep.cl','password'=>'test'])
            ->assertRedirect(route('inventario-bodega.epp.index'));
    }

    public function test_both_super_admins_keep_full_bodega_access(): void
    {
        foreach(['bmachero@saep.cl','Jrodriguez@saep.cl'] as $email) {
            $user=$this->user('SUPER_ADMIN',$email);
            foreach(['inventario_bodega','entregas_bodega_dashboard','solicitudes_epp','gestion_vehiculos'] as $module) {
                $this->assertTrue($user->tieneAcceso($module,'puede_ver'));
                $this->assertTrue($user->tieneAcceso($module,'puede_eliminar'));
            }
        }
    }
}
