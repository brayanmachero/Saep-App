<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Models\ArchivoAdjunto;
use App\Models\CentroCosto;
use App\Models\ConsentimientoDatos;
use App\Models\DescargaContenedor;
use App\Models\DescargaContenedorCarga;
use App\Models\DescargaContenedorTarifa;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\TalanaTrabajador;
use App\Models\User;
use App\Support\PrivacyPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DescargaContenedorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            VerificarConsentimientoDatos::class,
            ForcePasswordChange::class,
        ]);

        Modulo::updateOrCreate(
            ['slug' => 'descarga_contenedores'],
            [
                'nombre' => 'Contenedores',
                'descripcion' => 'Registro operativo, carga rápida y participantes por descarga.',
                'icono' => 'bi-box-seam-fill',
                'grupo' => 'Operaciones',
                'orden' => 18,
                'activo' => true,
            ]
        );
    }

    public function test_manual_store_uses_login_supervisor_talana_workers_and_percentage_payment(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Contenedores QA');
        $tarifa = $this->createTarifa('CNTQA01', 75000, 36000);
        $workerA = $this->createTalanaWorker('Trabajador Talana A', $centro);
        $workerB = $this->createTalanaWorker('Trabajador Talana B', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'estado' => 'validado',
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Contenedores QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-QA-001',
                'tarifa_id' => $tarifa->id,
                'fact_codigo' => 'CNT000',
                'participantes_json' => json_encode([
                    ['id' => $workerA->id, 'porcentaje' => 60],
                    ['id' => $workerB->id, 'porcentaje' => 40],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-QA-001')->firstOrFail();

        $this->assertSame($user->id, $descarga->supervisor_id);
        $this->assertSame('borrador', $descarga->estado);
        $this->assertNull($descarga->validado_at);
        $this->assertSame($tarifa->id, $descarga->tarifa_id);
        $this->assertSame($tarifa->codigo, $descarga->fact_codigo);
        $this->assertSame('WM', $descarga->tarifa_cliente_snapshot);
        $this->assertSame('CONTENEDOR QA', $descarga->tarifa_proceso_snapshot);
        $this->assertEquals(75000.0, (float) $descarga->costo_unitario_snapshot);
        $this->assertEquals(36000.0, (float) $descarga->pago_colaborador_snapshot);

        $participantes = $descarga->participantes()->orderBy('id')->get();

        $this->assertCount(2, $participantes);
        $this->assertTrue($participantes->every(fn ($p) => $p->user_id === null));
        $this->assertEqualsCanonicalizing(
            [$workerA->id, $workerB->id],
            $participantes->pluck('talana_trabajador_id')->all()
        );

        $byWorker = $participantes->keyBy('talana_trabajador_id');
        $this->assertEquals(60.0, (float) $byWorker[$workerA->id]->porcentaje_participacion);
        $this->assertEquals(40.0, (float) $byWorker[$workerB->id]->porcentaje_participacion);
        $this->assertEquals(21600.0, (float) $byWorker[$workerA->id]->monto_calculado);
        $this->assertEquals(14400.0, (float) $byWorker[$workerB->id]->monto_calculado);

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.validar', $descarga))
            ->assertRedirect()
            ->assertSessionHas('success');

        $descarga->refresh();

        $this->assertSame('validado', $descarga->estado);
        $this->assertSame($user->id, $descarga->validado_por);
        $this->assertNotNull($descarga->validado_at);

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.volver-borrador', $descarga))
            ->assertRedirect()
            ->assertSessionHas('success');

        $descarga->refresh();

        $this->assertSame('borrador', $descarga->estado);
        $this->assertNull($descarga->validado_por);
        $this->assertNull($descarga->validado_at);
    }

    public function test_capture_user_can_attach_private_photo_evidence_on_store(): void
    {
        Storage::fake('local');

        $capturador = $this->createContainerModuleUser(false);
        $centro = $this->createCentroCosto('CD Evidencia QA');
        $tarifa = $this->createTarifa('CNTEVID', 75000, 36000, 'EVIDENCIA QA');
        $worker = $this->createTalanaWorker('Trabajador Evidencia QA', $centro);
        $foto = UploadedFile::fake()->image('evidencia-descarga.jpg', 900, 700)->size(640);

        $this->actingAs($capturador)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Evidencia QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-EVID-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
                'evidencias' => [$foto],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-EVID-001')->firstOrFail();
        $evidencia = ArchivoAdjunto::where('entidad_tipo', 'descarga_contenedor')
            ->where('entidad_id', $descarga->id)
            ->firstOrFail();

        $this->assertSame('evidencia-descarga.jpg', $evidencia->nombre_original);
        $this->assertSame($capturador->id, $evidencia->subido_por);
        Storage::disk('local')->assertExists($evidencia->ruta);

        $this->actingAs($capturador)
            ->get(route('descarga-contenedores.show', $descarga))
            ->assertOk()
            ->assertSee('Evidencia fotográfica')
            ->assertSee('evidencia-descarga.jpg');

        $this->actingAs($capturador)
            ->get(route('descarga-contenedores.evidencias.ver', $evidencia))
            ->assertOk();

        $sinModuloRole = Rol::firstOrCreate(
            ['codigo' => 'SIN_CONTENEDORES'],
            ['nombre' => 'Sin Contenedores']
        );
        $sinModulo = User::create([
            'name' => 'Usuario Sin Contenedores',
            'email' => 'sin-contenedores-' . uniqid() . '@saep.local',
            'rol_id' => $sinModuloRole->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
        ]);

        $this->actingAs($sinModulo)
            ->get(route('archivos.descargar', $evidencia))
            ->assertForbidden();

        $this->actingAs($capturador)
            ->delete(route('descarga-contenedores.evidencias.destroy', [$descarga, $evidencia]))
            ->assertRedirect();

        Storage::disk('local')->assertMissing($evidencia->ruta);
        $this->assertDatabaseMissing('archivos_adjuntos', ['id' => $evidencia->id]);
    }

    public function test_capture_user_can_edit_only_own_draft_without_status_actions(): void
    {
        $admin = $this->createSuperAdminUser();
        $capturador = $this->createContainerModuleUser(false);
        $otroCapturador = $this->createContainerModuleUser(false, 'CONTENEDORES_CAPTURADOR_ALT', 'Capturador Contenedores Alt');
        $centro = $this->createCentroCosto('LTS Capturador Edicion QA');
        $tarifa = $this->createTarifa('CNTCAPEDIT', 75000, 36000, 'CAPTURADOR EDICION QA', false, $centro);
        $worker = $this->createTalanaWorker('Trabajador Capturador Edicion QA', $centro);

        $this->actingAs($capturador)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'LTS Capturador Edicion QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-CAP-EDIT-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descargaPropia = DescargaContenedor::where('contenedor', 'CONT-CAP-EDIT-001')->firstOrFail();

        $this->actingAs($capturador)
            ->get(route('descarga-contenedores.index'))
            ->assertOk()
            ->assertSee('Completar mi borrador', false)
            ->assertDontSee('Validar registro');

        $this->actingAs($capturador)
            ->get(route('descarga-contenedores.show', $descargaPropia))
            ->assertOk()
            ->assertSee('Editar')
            ->assertDontSee('Validar')
            ->assertDontSee('Liquidar');

        $this->actingAs($capturador)
            ->get(route('descarga-contenedores.edit', $descargaPropia))
            ->assertOk()
            ->assertSee('Pago estimado')
            ->assertDontSee('"costo_unitario"', false);

        $this->actingAs($capturador)
            ->put(route('descarga-contenedores.update', $descargaPropia), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'LTS Capturador Edicion QA',
                'fecha' => '2026-07-03',
                'contenedor' => 'CONT-CAP-EDIT-002',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descargaPropia->refresh();
        $this->assertSame('CONT-CAP-EDIT-002', $descargaPropia->contenedor);
        $this->assertSame('borrador', $descargaPropia->estado);

        $this->actingAs($otroCapturador)
            ->get(route('descarga-contenedores.edit', $descargaPropia))
            ->assertForbidden();

        $descargaPropia->update([
            'estado' => 'validado',
            'validado_por' => $admin->id,
            'validado_at' => now(),
        ]);

        $this->actingAs($capturador)
            ->get(route('descarga-contenedores.edit', $descargaPropia))
            ->assertForbidden();
    }

    public function test_edit_form_keeps_existing_center_tariff_and_workers_outside_current_selector_scope(): void
    {
        $capturador = $this->createContainerModuleUser(false);
        $centro = $this->createCentroCosto('Centro Externo Selector QA');
        $tarifa = $this->createTarifa('CNTEXTERNO', 75000, 36000, 'EXTERNO SELECTOR QA', false, $centro);
        $worker = $this->createTalanaWorker('Trabajador Externo Selector QA', $centro, [
            'cargo_nombre' => 'Cargo Fuera Filtro QA',
            'centro_costo_nombre' => 'Centro Externo Selector QA',
        ]);

        $descarga = DescargaContenedor::create([
            'estado' => 'borrador',
            'origen' => 'manual',
            'operacion' => 'Walmart',
            'centro_costo_id' => $centro->id,
            'bodega' => $centro->nombre,
            'fecha' => '2026-07-02',
            'contenedor' => 'CONT-EXTERNO-SELECTOR-001',
            'tarifa_id' => $tarifa->id,
            'fact_codigo' => $tarifa->codigo,
            'tarifa_cliente_snapshot' => $tarifa->cliente,
            'tarifa_proceso_snapshot' => $tarifa->proceso,
            'costo_unitario_snapshot' => $tarifa->costo_unitario,
            'pago_colaborador_snapshot' => $tarifa->pago_colaborador,
            'creado_por' => $capturador->id,
            'supervisor_id' => $capturador->id,
        ]);

        $descarga->participantes()->create([
            'talana_trabajador_id' => $worker->id,
            'nombre_snapshot' => $worker->nombre_completo ?: $worker->nombre,
            'rut_snapshot' => $worker->rut,
            'cargo_snapshot' => $worker->cargo_nombre,
            'centro_costo_id_snapshot' => $centro->id,
            'centro_costo_snapshot' => $centro->nombre,
            'rol_en_descarga' => 'descargador',
            'porcentaje_participacion' => 100,
            'monto_calculado' => 36000,
        ]);

        $this->actingAs($capturador)
            ->get(route('descarga-contenedores.edit', $descarga))
            ->assertOk()
            ->assertSee('Centro Externo Selector QA')
            ->assertSee('CNTEXTERNO')
            ->assertSee('Trabajador Externo Selector QA');

        $this->actingAs($capturador)
            ->put(route('descarga-contenedores.update', $descarga), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => $centro->nombre,
                'fecha' => '2026-07-03',
                'contenedor' => 'CONT-EXTERNO-SELECTOR-002',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([
                    ['id' => $worker->id, 'porcentaje' => 100],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga->refresh();
        $this->assertSame('CONT-EXTERNO-SELECTOR-002', $descarga->contenedor);
        $this->assertSame(1, $descarga->participantes()->count());
        $this->assertSame($worker->id, $descarga->participantes()->first()->talana_trabajador_id);
    }

    public function test_tariff_changes_do_not_rewrite_existing_descarga_snapshots(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Snapshot QA');
        $tarifa = $this->createTarifa('CNTSNAP', 50000, 25000, 'PROCESO ORIGINAL');
        $worker = $this->createTalanaWorker('Trabajador Snapshot', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'estado' => 'borrador',
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Snapshot QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-SNAPSHOT-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-SNAPSHOT-001')->firstOrFail();

        $this->actingAs($user)
            ->put(route('descarga-contenedores.tarifas.update', $tarifa), [
                'cliente' => 'WM',
                'codigo' => 'CNTSNAP',
                'proceso' => 'PROCESO MODIFICADO',
                'costo_unitario' => 99000,
                'pago_colaborador' => 49000,
                'requiere_revision' => 0,
                'activo' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga->refresh();

        $this->assertSame('PROCESO ORIGINAL', $descarga->tarifa_proceso_snapshot);
        $this->assertEquals(50000.0, (float) $descarga->costo_unitario_snapshot);
        $this->assertEquals(25000.0, (float) $descarga->pago_colaborador_snapshot);
    }

    public function test_bulk_store_distributes_equal_percentages_for_talana_team(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Bulk QA');
        $tarifa = $this->createTarifa('CNTBULK', 600, 300, 'BULK QA');
        $workers = collect([
            $this->createTalanaWorker('Trabajador Bulk A', $centro),
            $this->createTalanaWorker('Trabajador Bulk B', $centro),
            $this->createTalanaWorker('Trabajador Bulk C', $centro),
        ]);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store-bulk'), [
                'nombre' => 'Carga bulk QA',
                'registros_json' => json_encode([[
                    'estado' => 'validado',
                    'operacion' => 'Walmart',
                    'centro_costo_id' => $centro->id,
                    'bodega' => 'CD Bulk QA',
                    'fecha' => '02/07/2026',
                    'contenedor' => 'CONT-BULK-001',
                    'fact_codigo' => $tarifa->codigo,
                    'participantes' => $workers->pluck('id')->all(),
                ]]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-BULK-001')->firstOrFail();
        $participantes = $descarga->participantes()->orderBy('id')->get();

        $this->assertSame($tarifa->id, $descarga->tarifa_id);
        $this->assertSame($tarifa->codigo, $descarga->fact_codigo);
        $this->assertCount(3, $participantes);
        $this->assertSame([33.33, 33.33, 33.34], $participantes->map(fn ($p) => (float) $p->porcentaje_participacion)->all());
        $this->assertSame([99.99, 99.99, 100.02], $participantes->map(fn ($p) => (float) $p->monto_calculado)->all());
        $this->assertEquals(100.0, round((float) $participantes->sum('porcentaje_participacion'), 2));
        $this->assertEquals(300.0, round((float) $participantes->sum('monto_calculado'), 2));
    }

    public function test_bulk_store_does_not_guess_duplicated_fact_code_without_tarifa_id(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Fact Duplicado QA');
        $worker = $this->createTalanaWorker('Trabajador Fact Duplicado QA', $centro);

        $this->createTarifa('CNTDUPQA', 75000, 36000, 'DUPLICADO A');
        $tarifaB = $this->createTarifa('CNTDUPQA', 95000, 46000, 'DUPLICADO B');

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store-bulk'), [
                'nombre' => 'Carga FACT duplicado QA',
                'registros_json' => json_encode([[
                    'operacion' => 'Walmart',
                    'centro_costo_id' => $centro->id,
                    'bodega' => 'CD Fact Duplicado QA',
                    'fecha' => '02/07/2026',
                    'contenedor' => 'CONT-DUP-001',
                    'fact_codigo' => 'CNTDUPQA',
                    'participantes' => [$worker->id],
                ]]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-DUP-001')->firstOrFail();
        $carga = DescargaContenedorCarga::where('nombre', 'Carga FACT duplicado QA')->firstOrFail();

        $this->assertNull($descarga->tarifa_id);
        $this->assertSame('CNTDUPQA', $descarga->fact_codigo);
        $this->assertNull($descarga->costo_unitario_snapshot);
        $this->assertNull($descarga->pago_colaborador_snapshot);
        $this->assertSame(1, (int) $carga->filas_con_alertas);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store-bulk'), [
                'nombre' => 'Carga FACT seleccionado QA',
                'registros_json' => json_encode([[
                    'operacion' => 'Walmart',
                    'centro_costo_id' => $centro->id,
                    'bodega' => 'CD Fact Duplicado QA',
                    'fecha' => '02/07/2026',
                    'contenedor' => 'CONT-DUP-002',
                    'tarifa_id' => $tarifaB->id,
                    'fact_codigo' => 'CNTDUPQA',
                    'participantes' => [$worker->id],
                ]]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descargaSeleccionada = DescargaContenedor::where('contenedor', 'CONT-DUP-002')->firstOrFail();
        $this->assertSame($tarifaB->id, $descargaSeleccionada->tarifa_id);
        $this->assertSame('DUPLICADO B', $descargaSeleccionada->tarifa_proceso_snapshot);
    }

    public function test_walmart_operation_resolves_wm_tariff_when_smu_shares_the_fact_code(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Cliente FACT QA');
        $worker = $this->createTalanaWorker('Trabajador Cliente FACT QA', $centro);
        $wm = $this->createTarifa('CNTCLIENTE', 75000, 36000, 'CONTENEDOR ESTANDAR WM');
        DescargaContenedorTarifa::create([
            'cliente' => 'SMU',
            'codigo' => 'CNTCLIENTE',
            'proceso' => 'CONTENEDOR ESTANDAR SMU',
            'costo_unitario' => 50000,
            'pago_colaborador' => 25500,
            'requiere_revision' => false,
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store-bulk'), [
                'nombre' => 'Carga FACT por cliente QA',
                'registros_json' => json_encode([[
                    'operacion' => 'WALMART',
                    'centro_costo_id' => $centro->id,
                    'bodega' => 'CD Cliente FACT QA',
                    'fecha' => '02/07/2026',
                    'contenedor' => 'CONT-CLIENTE-001',
                    'fact_codigo' => 'CNTCLIENTE',
                    'participantes' => [$worker->id],
                ]]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-CLIENTE-001')->firstOrFail();
        $this->assertSame($wm->id, $descarga->tarifa_id);
        $this->assertSame('WM', $descarga->tarifa_cliente_snapshot);
        $this->assertEquals(36000.0, (float) $descarga->pago_colaborador_snapshot);
    }

    public function test_bulk_store_skips_existing_container_date_duplicates_when_requested(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Duplicado Carga QA');
        $tarifa = $this->createTarifa('CNTSKIPDUP', 75000, 36000, 'SKIP DUP QA');
        $worker = $this->createTalanaWorker('Trabajador Skip Dup QA', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store-bulk'), [
                'nombre' => 'Carga original QA',
                'registros_json' => json_encode([[
                    'operacion' => 'Walmart',
                    'centro_costo_id' => $centro->id,
                    'bodega' => 'CD Duplicado Carga QA',
                    'fecha' => '21/07/2026',
                    'contenedor' => 'GLDU7323259',
                    'fact_codigo' => $tarifa->codigo,
                    'participantes' => [$worker->id],
                ]]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store-bulk'), [
                'nombre' => 'Carga repetida QA',
                'omitir_duplicados' => '1',
                'registros_json' => json_encode([[
                    'operacion' => 'Walmart',
                    'centro_costo_id' => $centro->id,
                    'bodega' => 'CD Duplicado Carga QA',
                    'fecha' => '21/07/2026',
                    'contenedor' => 'GLDU7323259',
                    'fact_codigo' => $tarifa->codigo,
                    'participantes' => [$worker->id],
                ]]),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, DescargaContenedor::where('contenedor', 'GLDU7323259')->count());
    }

    public function test_fact_code_is_resolved_by_selected_center_before_other_tariffs(): void
    {
        $user = $this->createSuperAdminUser();
        $centroA = $this->createCentroCosto('LTS PEÑON FACT QA');
        $centroB = $this->createCentroCosto('LTS QUILICURA FACT QA');
        $tarifaA = $this->createTarifa('CNTCENTRO', 75000, 36000, 'TARIFA PEÑON', false, $centroA);
        $tarifaB = $this->createTarifa('CNTCENTRO', 90000, 42000, 'TARIFA QUILICURA', false, $centroB);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centroB->id,
                'bodega' => 'LTS QUILICURA',
                'fecha' => '2026-07-04',
                'contenedor' => 'CONT-CENTRO-QA',
                'fact_codigo' => 'CNTCENTRO',
                'participantes_json' => json_encode([]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-CENTRO-QA')->firstOrFail();

        $this->assertSame($tarifaB->id, $descarga->tarifa_id);
        $this->assertSame('TARIFA QUILICURA', $descarga->tarifa_proceso_snapshot);
        $this->assertNotSame($tarifaA->id, $descarga->tarifa_id);
    }

    public function test_dotacion_search_accepts_rut_without_dots_and_operational_center_override(): void
    {
        $user = $this->createSuperAdminUser();
        $centroTalana = $this->createCentroCosto('LTS CAMPOS DE CHILE DOT QA');
        $centroOperativo = $this->createCentroCosto('LTS QUILICURA DOT QA');
        $trabajador = $this->createTalanaWorker('Rut Operativo QA', $centroTalana, [
            'rut' => '18.202.202-K',
        ]);

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.dotacion.trabajadores.update', $trabajador), [
                'centro_operativo_id' => $centroOperativo->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $trabajador->refresh();
        $this->assertSame($centroOperativo->id, $trabajador->centro_operativo_id);

        $this->actingAs($user)
            ->get(route('descarga-contenedores.dotacion', [
                'buscar' => '18202202-K',
                'centro_costo_id' => $centroOperativo->id,
            ]))
            ->assertOk()
            ->assertSee('Rut Operativo QA')
            ->assertSee('18202202-K')
            ->assertSee('LTS QUILICURA DOT QA');
    }

    public function test_dotacion_can_bulk_update_operational_center_without_changing_talana_center(): void
    {
        $user = $this->createSuperAdminUser();
        $centroTalana = $this->createCentroCosto('LTS CAMPOS DE CHILE BULK QA');
        $centroOperativo = $this->createCentroCosto('LTS QUILICURA BULK QA');
        $workerA = $this->createTalanaWorker('Bulk Centro Real A', $centroTalana);
        $workerB = $this->createTalanaWorker('Bulk Centro Real B', $centroTalana);

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.dotacion.trabajadores.bulk-update'), [
                'trabajadores' => [$workerA->id, $workerB->id],
                'centro_operativo_id' => $centroOperativo->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $workerA->refresh();
        $workerB->refresh();

        $this->assertSame($centroTalana->id, $workerA->centro_costo_id);
        $this->assertSame($centroTalana->id, $workerB->centro_costo_id);
        $this->assertSame($centroOperativo->id, $workerA->centro_operativo_id);
        $this->assertSame($centroOperativo->id, $workerB->centro_operativo_id);
        $this->assertSame($centroOperativo->nombre, $workerA->centro_operativo_nombre);
        $this->assertSame($centroOperativo->nombre, $workerB->centro_operativo_nombre);
    }

    public function test_manual_store_uses_operational_center_in_participant_snapshot(): void
    {
        $user = $this->createSuperAdminUser();
        $centroTalana = $this->createCentroCosto('LTS CAMPOS DE CHILE SNAP QA');
        $centroOperativo = $this->createCentroCosto('LTS QUILICURA SNAP QA');
        $tarifa = $this->createTarifa('CNTSNAPCENTRO', 75000, 36000);
        $trabajador = $this->createTalanaWorker('Centro Operativo QA', $centroTalana, [
            'centro_operativo_id' => $centroOperativo->id,
            'centro_operativo_nombre' => $centroOperativo->nombre,
        ]);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centroOperativo->id,
                'bodega' => 'LTS QUILICURA',
                'fecha' => '2026-07-05',
                'contenedor' => 'CONT-SNAP-CENTRO-QA',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([
                    ['id' => $trabajador->id, 'porcentaje' => 100],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-SNAP-CENTRO-QA')->firstOrFail();
        $participante = $descarga->participantes()->firstOrFail();

        $this->assertSame($centroOperativo->id, $participante->centro_costo_id_snapshot);
        $this->assertSame($centroOperativo->nombre, $participante->centro_costo_snapshot);
    }

    public function test_create_form_lists_talana_workers_not_plain_users(): void
    {
        $user = $this->createSuperAdminUser();
        $role = Rol::where('codigo', 'SUPER_ADMIN')->firstOrFail();
        $centro = $this->createCentroCosto('CD Selector QA');

        User::create([
            'name' => 'Usuario Solo Sistema QA',
            'email' => 'solo-sistema-' . uniqid() . '@saep.local',
            'rol_id' => $role->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
            'must_change_password' => false,
        ]);

        $this->createTalanaWorker('Trabajador Talana Visible', $centro);
        $this->createTarifa('CNTSELECT', 75000, 36000, 'SELECTOR QA');

        $this->actingAs($user)
            ->get(route('descarga-contenedores.create'))
            ->assertOk()
            ->assertSee('Tarifa FACT')
            ->assertSee('Buscar código FACT')
            ->assertSee('CNTSELECT')
            ->assertSee('Todos los centros')
            ->assertSee('Todos los cargos')
            ->assertSee('CD Selector QA')
            ->assertSee('Descargador')
            ->assertSee('Trabajador Talana Visible')
            ->assertDontSee('Usuario Solo Sistema QA');

        $this->actingAs($user)
            ->get(route('descarga-contenedores.carga-rapida'))
            ->assertOk()
            ->assertSee('Todos los centros')
            ->assertSee('Todos los cargos')
            ->assertSee('CD Selector QA')
            ->assertSee('Descargador')
            ->assertSee('Trabajador Talana Visible')
            ->assertDontSee('Usuario Solo Sistema QA');
    }

    public function test_container_worker_selectors_are_scoped_to_excel_managed_centers(): void
    {
        $user = $this->createSuperAdminUser();
        $centroGestionado = $this->createCentroCosto('LTS PENON QA');
        $centroFuera = $this->createCentroCosto('CD Administrativo Fuera QA');

        $workerGestionado = $this->createTalanaWorker('Trabajador Centro Excel QA', $centroGestionado);
        $workerFuera = $this->createTalanaWorker('Trabajador Centro Fuera QA', $centroFuera, [
            'centro_costo_nombre' => $centroFuera->nombre,
        ]);

        $this->actingAs($user)
            ->get(route('descarga-contenedores.create'))
            ->assertOk()
            ->assertSee('LTS PENON QA')
            ->assertSee('Trabajador Centro Excel QA')
            ->assertDontSee('CD Administrativo Fuera QA')
            ->assertDontSee('Trabajador Centro Fuera QA');

        $this->actingAs($user)
            ->get(route('descarga-contenedores.carga-rapida'))
            ->assertOk()
            ->assertSee('LTS PENON QA')
            ->assertSee('Trabajador Centro Excel QA')
            ->assertDontSee('CD Administrativo Fuera QA')
            ->assertDontSee('Trabajador Centro Fuera QA');

        $this->actingAs($user)
            ->get(route('descarga-contenedores.dotacion'))
            ->assertOk()
            ->assertSee('LTS PENON QA')
            ->assertSee('Trabajador Centro Excel QA')
            ->assertDontSee('CD Administrativo Fuera QA')
            ->assertDontSee('Trabajador Centro Fuera QA');

        $tarifa = $this->createTarifa('CNTSCOPEQA', 75000, 36000, 'SCOPE QA');
        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centroGestionado->id,
                'bodega' => 'LTS PENON QA',
                'fecha' => '2026-07-03',
                'contenedor' => 'CONT-SCOPE-QA',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([
                    ['id' => $workerGestionado->id, 'porcentaje' => 50],
                    ['id' => $workerFuera->id, 'porcentaje' => 50],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-SCOPE-QA')->firstOrFail();
        $this->assertSame(1, $descarga->participantes()->count());
        $this->assertDatabaseHas('descarga_contenedor_participantes', [
            'descarga_contenedor_id' => $descarga->id,
            'talana_trabajador_id' => $workerGestionado->id,
        ]);
        $this->assertDatabaseMissing('descarga_contenedor_participantes', [
            'descarga_contenedor_id' => $descarga->id,
            'talana_trabajador_id' => $workerFuera->id,
        ]);
    }

    public function test_index_filters_operational_pending_records(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Filtros QA');
        $worker = $this->createTalanaWorker('Trabajador Filtro QA', $centro);
        $tarifaOk = $this->createTarifa('CNTOKQA', 75000, 36000, 'FILTRO OK');
        $tarifaRevision = $this->createTarifa('CNTREVQA', 0, 0, 'FILTRO REVISION', true);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'estado' => 'validado',
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Filtros QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-FILTRO-OK',
                'tarifa_id' => $tarifaOk->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'estado' => 'borrador',
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Filtros QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-FILTRO-REVISION',
                'tarifa_id' => $tarifaRevision->id,
                'participantes_json' => json_encode([]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('descarga-contenedores.index', [
                'tarifa_estado' => 'revision',
                'equipo_estado' => 'sin_equipo',
            ]))
            ->assertOk()
            ->assertSee('CONT-FILTRO-REVISION')
            ->assertDontSee('CONT-FILTRO-OK');

        $this->actingAs($user)
            ->get(route('descarga-contenedores.index', [
                'validacion_estado' => 'listos',
            ]))
            ->assertOk()
            ->assertSee('CONT-FILTRO-OK')
            ->assertSee('Listo')
            ->assertDontSee('CONT-FILTRO-REVISION');

        $this->actingAs($user)
            ->get(route('descarga-contenedores.index', [
                'validacion_estado' => 'pendientes',
            ]))
            ->assertOk()
            ->assertSee('CONT-FILTRO-REVISION')
            ->assertSee('Falta equipo de trabajadores')
            ->assertDontSee('CONT-FILTRO-OK');
    }

    public function test_index_exposes_container_operational_submodules(): void
    {
        $user = $this->createSuperAdminUser();

        $this->actingAs($user)
            ->get(route('descarga-contenedores.index'))
            ->assertOk()
            ->assertSee('Programación')
            ->assertSee('Cargas')
            ->assertSee('Dotación')
            ->assertSee('Liquidación')
            ->assertSee('Reportes')
            ->assertSee('Tarifas FACT');
    }

    public function test_cargas_page_lists_bulk_batches(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Cargas QA');
        $tarifa = $this->createTarifa('CNTHIST', 600, 300, 'HISTORIAL QA');
        $worker = $this->createTalanaWorker('Trabajador Historial QA', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store-bulk'), [
                'nombre' => 'Carga historial QA',
                'registros_json' => json_encode([[
                    'estado' => 'validado',
                    'operacion' => 'Walmart',
                    'centro_costo_id' => $centro->id,
                    'bodega' => 'CD Cargas QA',
                    'fecha' => '02/07/2026',
                    'contenedor' => 'CONT-HIST-001',
                    'fact_codigo' => $tarifa->codigo,
                    'participantes' => [$worker->id],
                ]]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('descarga-contenedores.cargas'))
            ->assertOk()
            ->assertSee('Carga historial QA')
            ->assertSee('Ver 1');

        $this->assertTrue(DescargaContenedorCarga::where('nombre', 'Carga historial QA')->exists());
    }

    public function test_liquidacion_aggregates_worker_payment(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Liquidacion QA');
        $tarifa = $this->createTarifa('CNTLIQ', 75000, 36000, 'LIQUIDACION QA');
        $workerA = $this->createTalanaWorker('Trabajador Liquidacion A', $centro);
        $workerB = $this->createTalanaWorker('Trabajador Liquidacion B', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'estado' => 'validado',
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Liquidacion QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-LIQ-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$workerA->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descargaA = DescargaContenedor::where('contenedor', 'CONT-LIQ-001')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.validar', $descargaA))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'estado' => 'validado',
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Liquidacion QA',
                'fecha' => '2026-07-03',
                'contenedor' => 'CONT-LIQ-002',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([
                    ['id' => $workerA->id, 'porcentaje' => 50],
                    ['id' => $workerB->id, 'porcentaje' => 50],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descargaB = DescargaContenedor::where('contenedor', 'CONT-LIQ-002')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.validar', $descargaB))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get(route('descarga-contenedores.liquidacion'))
            ->assertOk()
            ->assertSee('Trabajador Liquidacion A')
            ->assertSee('$54.000')
            ->assertSee('Trabajador Liquidacion B')
            ->assertSee('$18.000');
    }

    public function test_liquidacion_defaults_to_validated_records_and_exports_csv(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Liquidacion Estado QA');
        $tarifa = $this->createTarifa('CNTLIQEST', 75000, 36000, 'LIQUIDACION ESTADO QA');
        $validadoWorker = $this->createTalanaWorker('Trabajador Liquidacion Validado', $centro);
        $borradorWorker = $this->createTalanaWorker('Trabajador Liquidacion Borrador', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Liquidacion Estado QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-LIQ-VALIDADO',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$validadoWorker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descargaValidada = DescargaContenedor::where('contenedor', 'CONT-LIQ-VALIDADO')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.validar', $descargaValidada))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Liquidacion Estado QA',
                'fecha' => '2026-07-03',
                'contenedor' => 'CONT-LIQ-BORRADOR',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$borradorWorker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('descarga-contenedores.liquidacion'))
            ->assertOk()
            ->assertSee('Trabajador Liquidacion Validado')
            ->assertDontSee('Trabajador Liquidacion Borrador');

        $this->actingAs($user)
            ->get(route('descarga-contenedores.liquidacion', ['estado' => 'todos']))
            ->assertOk()
            ->assertSee('Trabajador Liquidacion Validado')
            ->assertSee('Trabajador Liquidacion Borrador');

        $response = $this->actingAs($user)
            ->get(route('descarga-contenedores.liquidacion.exportar'));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Trabajador Liquidacion Validado', $csv);
        $this->assertStringNotContainsString('Trabajador Liquidacion Borrador', $csv);
    }

    public function test_coordinator_can_liquidate_and_reopen_validated_record(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Liquidar QA');
        $tarifa = $this->createTarifa('CNTLIQACC', 75000, 36000, 'LIQUIDAR ACCION QA');
        $worker = $this->createTalanaWorker('Trabajador Liquidar Accion', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Liquidar QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-LIQ-ACCION',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-LIQ-ACCION')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.validar', $descarga))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.liquidar', $descarga))
            ->assertRedirect()
            ->assertSessionHas('success');

        $descarga->refresh();

        $this->assertSame('liquidado', $descarga->estado);
        $this->assertSame($user->id, $descarga->liquidado_por);
        $this->assertNotNull($descarga->liquidado_at);

        $this->actingAs($user)
            ->get(route('descarga-contenedores.show', $descarga))
            ->assertOk()
            ->assertSee('Liquidado')
            ->assertSee('Reabrir como validado')
            ->assertDontSee('Editar');

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.volver-validado', $descarga))
            ->assertRedirect()
            ->assertSessionHas('success');

        $descarga->refresh();

        $this->assertSame('validado', $descarga->estado);
        $this->assertNull($descarga->liquidado_por);
        $this->assertNull($descarga->liquidado_at);

        $this->actingAs($user)
            ->get(route('descarga-contenedores.show', $descarga))
            ->assertOk()
            ->assertSee('Liquidar')
            ->assertSee('Editar')
            ->assertDontSee('Liquidado por')
            ->assertDontSee('Fecha liquidación');
    }

    public function test_liquidated_records_are_locked_until_reopened(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Liquidado Bloqueado QA');
        $tarifa = $this->createTarifa('CNTLIQLOCK', 75000, 36000, 'LIQUIDADO BLOQUEADO QA');
        $worker = $this->createTalanaWorker('Trabajador Liquidado Bloqueado', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Liquidado Bloqueado QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-LIQ-LOCK',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-LIQ-LOCK')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.validar', $descarga))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.liquidar', $descarga))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get(route('descarga-contenedores.edit', $descarga))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('descarga-contenedores.destroy', $descarga))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('descarga_contenedores', ['id' => $descarga->id]);
    }

    public function test_dotacion_page_uses_talana_workers(): void
    {
        $user = $this->createSuperAdminUser();
        $role = Rol::where('codigo', 'SUPER_ADMIN')->firstOrFail();
        $centro = $this->createCentroCosto('CD Dotacion QA');

        User::create([
            'name' => 'Usuario No Dotacion QA',
            'email' => 'no-dotacion-' . uniqid() . '@saep.local',
            'rol_id' => $role->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
            'must_change_password' => false,
        ]);

        $this->createTalanaWorker('Trabajador Dotacion Visible', $centro);

        $this->actingAs($user)
            ->get(route('descarga-contenedores.dotacion'))
            ->assertOk()
            ->assertSee('Trabajador Dotacion Visible')
            ->assertDontSee('Usuario No Dotacion QA');
    }

    public function test_dotacion_can_filter_workers_by_cargo(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Cargo QA');
        $descargador = $this->createTalanaWorker('Trabajador Cargo Descargador', $centro);
        $apoyo = $this->createTalanaWorker('Trabajador Cargo Apoyo', $centro);
        $apoyo->update(['cargo_nombre' => 'Operario Apoyo']);

        $this->actingAs($user)
            ->get(route('descarga-contenedores.dotacion', ['cargo' => 'Operario Apoyo']))
            ->assertOk()
            ->assertSee('Cargos clasificados')
            ->assertSee('Trabajador Cargo Apoyo')
            ->assertDontSee('Trabajador Cargo Descargador');
    }

    public function test_dotacion_cargo_options_are_scoped_to_selected_center(): void
    {
        $user = $this->createSuperAdminUser();
        $centroA = $this->createCentroCosto('CD Cargo Centro A QA');
        $centroB = $this->createCentroCosto('CD Cargo Centro B QA');
        $workerA = $this->createTalanaWorker('Trabajador Centro A QA', $centroA);
        $workerB = $this->createTalanaWorker('Trabajador Centro B QA', $centroB);
        $workerA->update(['cargo_nombre' => 'Operario Centro A QA']);
        $workerB->update(['cargo_nombre' => 'Operario Centro B QA']);

        $this->actingAs($user)
            ->get(route('descarga-contenedores.dotacion', ['centro_costo_id' => $centroA->id]))
            ->assertOk()
            ->assertSee('Operario Centro A QA')
            ->assertDontSee('Operario Centro B QA');
    }

    public function test_validation_rejects_distribution_that_does_not_sum_100_percent(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Porcentaje QA');
        $tarifa = $this->createTarifa('CNTPCTQA', 75000, 36000, 'PORCENTAJE QA');
        $workerA = $this->createTalanaWorker('Trabajador Porcentaje A', $centro);
        $workerB = $this->createTalanaWorker('Trabajador Porcentaje B', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Porcentaje QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-PCT-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([
                    ['id' => $workerA->id, 'porcentaje' => 50],
                    ['id' => $workerB->id, 'porcentaje' => 50],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-PCT-001')->firstOrFail();
        $descarga->participantes()->where('talana_trabajador_id', $workerB->id)->update([
            'porcentaje_participacion' => 20,
            'monto_calculado' => 7200,
        ]);

        $this->actingAs($user)
            ->patch(route('descarga-contenedores.validar', $descarga))
            ->assertRedirect()
            ->assertSessionHas('error');

        $descarga->refresh();

        $this->assertSame('borrador', $descarga->estado);
        $this->assertTrue($descarga->validationBlockers()->contains('porcentajes no suman 100%'));
    }

    public function test_manual_store_preserves_explicit_percentages_instead_of_normalizing_them(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Porcentaje Manual QA');
        $tarifa = $this->createTarifa('CNTPCTMANUAL', 75000, 36000, 'PORCENTAJE MANUAL QA');
        $workerA = $this->createTalanaWorker('Trabajador Manual Porcentaje A', $centro);
        $workerB = $this->createTalanaWorker('Trabajador Manual Porcentaje B', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Porcentaje Manual QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-PCT-MANUAL-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([
                    ['id' => $workerA->id, 'porcentaje' => 50],
                    ['id' => $workerB->id, 'porcentaje' => 20],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-PCT-MANUAL-001')->firstOrFail();
        $participantes = $descarga->participantes()->orderBy('id')->get();

        $this->assertSame([50.0, 20.0], $participantes->map(fn ($p) => (float) $p->porcentaje_participacion)->all());
        $this->assertSame([18000.0, 7200.0], $participantes->map(fn ($p) => (float) $p->monto_calculado)->all());
        $this->assertSame('borrador', $descarga->estado);
        $this->assertTrue($descarga->validationBlockers()->contains('porcentajes no suman 100%'));
    }

    public function test_pending_detail_shows_workflow_status_and_completion_action(): void
    {
        $user = $this->createSuperAdminUser();

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'contenedor' => 'CONT-PENDIENTE-001',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-PENDIENTE-001')->firstOrFail();

        $this->actingAs($user)
            ->get(route('descarga-contenedores.show', $descarga))
            ->assertOk()
            ->assertSee('Estado del proceso')
            ->assertSee('Registro en carga, con pendientes antes de validar.')
            ->assertSee('Fecha registrada')
            ->assertSee('Antes de validar falta:')
            ->assertSee('Completar registro')
            ->assertSee('Pendiente')
            ->assertDontSee('Listo para validar.');
    }

    public function test_reportes_page_groups_by_operation_and_fact(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Reporte QA');
        $tarifa = $this->createTarifa('CNTREP', 75000, 36000, 'REPORTE QA');
        $worker = $this->createTalanaWorker('Trabajador Reporte QA', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'estado' => 'validado',
                'operacion' => 'Operacion Reporte QA',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Reporte QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-REP-001',
                'tarifa_id' => $tarifa->id,
                'cajas' => 1200,
                'pallets' => 24,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('descarga-contenedores.reportes'))
            ->assertOk()
            ->assertSee('Operacion Reporte QA')
            ->assertSee('CNTREP')
            ->assertSee('1.200');
    }

    public function test_capture_only_user_cannot_access_cost_management_sections(): void
    {
        $user = $this->createContainerModuleUser(false);

        $this->actingAs($user)
            ->get(route('descarga-contenedores.index'))
            ->assertOk()
            ->assertSee('Programación')
            ->assertSee('Cargas')
            ->assertDontSee('Dotación')
            ->assertDontSee('Liquidación')
            ->assertDontSee('Reportes')
            ->assertDontSee('Tarifas FACT')
            ->assertDontSee('Tarifa por revisar')
            ->assertDontSee('Pago total ref.');

        $this->actingAs($user)
            ->get(route('descarga-contenedores.create'))
            ->assertOk()
            ->assertSee('Tarifa FACT')
            ->assertSee('Pago estimado')
            ->assertDontSee('"costo_unitario"', false)
            ->assertDontSee('$75.000');

        $restrictedRoutes = [
            'descarga-contenedores.dotacion',
            'descarga-contenedores.liquidacion',
            'descarga-contenedores.reportes',
            'descarga-contenedores.tarifas',
        ];

        foreach ($restrictedRoutes as $routeName) {
            $this->actingAs($user)
                ->get(route($routeName))
                ->assertForbidden();
        }
    }

    public function test_capture_only_user_detail_shows_collaborator_pay_but_hides_company_cost(): void
    {
        $admin = $this->createSuperAdminUser();
        $capturador = $this->createContainerModuleUser(false);
        $centro = $this->createCentroCosto('CD Captura QA');
        $tarifa = $this->createTarifa('CNTCAP', 75000, 36000, 'CAPTURA QA');
        $worker = $this->createTalanaWorker('Trabajador Captura QA', $centro);

        $this->actingAs($admin)
            ->post(route('descarga-contenedores.store'), [
                'estado' => 'validado',
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Captura QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-CAPTURA-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-CAPTURA-001')->firstOrFail();

        $this->actingAs($capturador)
            ->get(route('descarga-contenedores.show', $descarga))
            ->assertOk()
            ->assertSee('CONT-CAPTURA-001')
            ->assertSee('100,00%')
            ->assertSee('Pago colaboradores')
            ->assertSee('$36.000')
            ->assertDontSee('Costo unitario')
            ->assertDontSee('$75.000')
            ->assertDontSee('Tarifas relacionadas');

        $this->actingAs($capturador)
            ->getJson(route('descarga-contenedores.panel', $descarga))
            ->assertOk()
            ->assertJsonPath('can_view_costs', false)
            ->assertJsonPath('descarga.costo_unitario', null);
        $this->assertEquals(36000.0, (float) $this->actingAs($capturador)
            ->getJson(route('descarga-contenedores.panel', $descarga))
            ->json('descarga.pago'));
    }

    public function test_capture_only_user_forms_do_not_receive_price_data(): void
    {
        $capturador = $this->createContainerModuleUser(false);
        $centro = $this->createCentroCosto('CD Captura Precio QA');
        $this->createTarifa('CNTPRECIOCAP', 75000, 36000, 'PRECIO OCULTO QA', false, $centro);

        foreach ([
            route('descarga-contenedores.create'),
            route('descarga-contenedores.carga-rapida'),
        ] as $url) {
            $this->actingAs($capturador)
                ->get($url)
                ->assertOk()
                ->assertSee('CNTPRECIOCAP')
                ->assertDontSee('Costo unitario')
                ->assertDontSee('75000')
                ->assertDontSee('$75.000')
                ->assertDontSee('"costo_unitario"', false);
        }
    }

    public function test_operational_editor_without_coordinator_role_cannot_see_costs(): void
    {
        $admin = $this->createSuperAdminUser();
        $operativo = $this->createContainerModuleUser(
            true,
            'CONTENEDORES_OPERATIVO_EDIT',
            'Supervisor Operativo Contenedores'
        );
        $centro = $this->createCentroCosto('CD Operativo QA');
        $tarifa = $this->createTarifa('CNTOPSEC', 75000, 36000, 'OPERATIVO QA');
        $worker = $this->createTalanaWorker('Trabajador Operativo QA', $centro);

        $this->actingAs($admin)
            ->post(route('descarga-contenedores.store'), [
                'estado' => 'validado',
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Operativo QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-OPERATIVO-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-OPERATIVO-001')->firstOrFail();

        $this->actingAs($operativo)
            ->get(route('descarga-contenedores.show', $descarga))
            ->assertOk()
            ->assertSee('CONT-OPERATIVO-001')
            ->assertSee('Editar')
            ->assertSee('Pago colaboradores')
            ->assertSee('$36.000')
            ->assertDontSee('Costo unitario')
            ->assertDontSee('Tarifas relacionadas')
            ->assertDontSee('$75.000');

        $this->actingAs($operativo)
            ->get(route('descarga-contenedores.edit', $descarga))
            ->assertOk()
            ->assertSee('Tarifa FACT')
            ->assertSee('Pago estimado')
            ->assertDontSee('"costo_unitario"', false)
            ->assertDontSee('$75.000');

        foreach ([
            'descarga-contenedores.dotacion',
            'descarga-contenedores.liquidacion',
            'descarga-contenedores.reportes',
            'descarga-contenedores.tarifas',
        ] as $routeName) {
            $this->actingAs($operativo)
                ->get(route($routeName))
                ->assertForbidden();
        }
    }

    public function test_coordinator_can_see_container_cost_detail(): void
    {
        $admin = $this->createSuperAdminUser();
        $coordinador = $this->createContainerModuleUser(true, 'CONTENEDORES_COORDINADOR', 'Coordinador Contenedores');
        $centro = $this->createCentroCosto('CD Coordinador QA');
        $tarifa = $this->createTarifa('CNTCOORD', 75000, 36000, 'COORDINADOR QA');
        $worker = $this->createTalanaWorker('Trabajador Coordinador QA', $centro);

        $this->actingAs($admin)
            ->post(route('descarga-contenedores.store'), [
                'estado' => 'validado',
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Coordinador QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-COORD-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-COORD-001')->firstOrFail();

        $this->actingAs($coordinador)
            ->get(route('descarga-contenedores.show', $descarga))
            ->assertOk()
            ->assertSee('Costo unitario')
            ->assertSee('Pago colaboradores')
            ->assertSee('Tarifas relacionadas')
            ->assertSee('$75.000')
            ->assertSee('$36.000');
    }

    public function test_generic_coordinator_role_does_not_see_container_cost_detail(): void
    {
        $admin = $this->createSuperAdminUser();
        $coordinadorGenerico = $this->createContainerModuleUser(true, 'COORDINADOR', 'Coordinador');
        $centro = $this->createCentroCosto('CD Coordinador Generico QA');
        $tarifa = $this->createTarifa('CNTCOORDGEN', 75000, 36000, 'COORDINADOR GENERICO QA');
        $worker = $this->createTalanaWorker('Trabajador Coordinador Generico QA', $centro);

        $this->actingAs($admin)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Coordinador Generico QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-COORD-GENERICO-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-COORD-GENERICO-001')->firstOrFail();

        $this->actingAs($coordinadorGenerico)
            ->get(route('descarga-contenedores.show', $descarga))
            ->assertOk()
            ->assertSee('CONT-COORD-GENERICO-001')
            ->assertSee('Pago colaboradores')
            ->assertSee('$36.000')
            ->assertDontSee('Costo unitario')
            ->assertDontSee('Tarifas relacionadas')
            ->assertDontSee('$75.000');

        foreach ([
            'descarga-contenedores.dotacion',
            'descarga-contenedores.liquidacion',
            'descarga-contenedores.reportes',
            'descarga-contenedores.tarifas',
        ] as $routeName) {
            $this->actingAs($coordinadorGenerico)
                ->get(route($routeName))
                ->assertForbidden();
        }
    }

    public function test_coordinator_review_queue_shows_ready_and_pending_drafts(): void
    {
        $admin = $this->createSuperAdminUser();
        $coordinador = $this->createContainerModuleUser(true, 'CONTENEDORES_COORDINADOR', 'Coordinador Contenedores');
        $capturador = $this->createContainerModuleUser(false);
        $centro = $this->createCentroCosto('LTS QUILICURA Revision QA');
        $tarifa = $this->createTarifa('CNTQUEUE', 75000, 36000, 'QUEUE QA', false, $centro);
        $worker = $this->createTalanaWorker('Trabajador Queue QA', $centro);

        $this->actingAs($admin)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'LTS QUILICURA Revision QA',
                'fecha' => '2026-07-02',
                'contenedor' => 'CONT-QUEUE-LISTO',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'LTS QUILICURA Revision QA',
                'fecha' => '2026-07-03',
                'contenedor' => 'CONT-QUEUE-PENDIENTE',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($coordinador)
            ->get(route('descarga-contenedores.index'))
            ->assertOk()
            ->assertSee('Bandeja de revisión')
            ->assertSee('CONT-QUEUE-LISTO')
            ->assertSee('CONT-QUEUE-PENDIENTE')
            ->assertSee('Listos para validar')
            ->assertSee('Pendientes por completar');

        $this->actingAs($capturador)
            ->get(route('descarga-contenedores.index'))
            ->assertOk()
            ->assertDontSee('Bandeja de revisión')
            ->assertDontSee('Validar registro');
    }

    public function test_index_exposes_inline_drawer_and_bulk_worker_assignment(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Panel QA');
        $tarifa = $this->createTarifa('CNTPANEL', 75000, 36000, 'PANEL QA', false, $centro);
        $worker = $this->createTalanaWorker('Trabajador Panel QA', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Panel QA',
                'fecha' => '2026-08-01',
                'contenedor' => 'CONT-PANEL-001',
                'tarifa_id' => $tarifa->id,
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('descarga-contenedores.index'))
            ->assertOk()
            ->assertSee('Asignar trabajadores')
            ->assertSee('data-contenedores-drawer', false)
            ->assertSee('data-select-all', false)
            ->assertSee('data-bulk-delete', false)
            ->assertSee('Editar en panel', false)
            ->assertSee('Edición completa')
            ->assertDontSee('Abrir página');
    }

    public function test_quick_panel_and_save_update_tariff_and_workers_without_leaving_the_list(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Rapido QA');
        $tarifa = $this->createTarifa('CNTRAPIDO', 75000, 36000, 'RAPIDO QA', false, $centro);
        $workerA = $this->createTalanaWorker('Trabajador Rapido A', $centro);
        $workerB = $this->createTalanaWorker('Trabajador Rapido B', $centro);

        $this->actingAs($user)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Rapido QA',
                'fecha' => '2026-08-01',
                'contenedor' => 'CONT-RAPIDO-001',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-RAPIDO-001')->firstOrFail();

        $this->actingAs($user)
            ->getJson(route('descarga-contenedores.panel', $descarga))
            ->assertOk()
            ->assertJsonPath('can_edit', true)
            ->assertJsonPath('descarga.contenedor', 'CONT-RAPIDO-001');

        $this->actingAs($user)
            ->patchJson(route('descarga-contenedores.rapido', $descarga), [
                'tarifa_id' => $tarifa->id,
                'fact_codigo' => 'CNTRAPIDO',
                'producto' => 'FREIDORA 7.2',
                'cajas' => 1044,
                'participantes_json' => json_encode([
                    ['id' => $workerA->id, 'porcentaje' => 60],
                    ['id' => $workerB->id, 'porcentaje' => 40],
                ]),
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('row.participantes_count', 2)
            ->assertJsonPath('row.fact_codigo', 'CNTRAPIDO')
            ->assertJsonPath('row.producto', 'FREIDORA 7.2')
            ->assertJsonPath('row.cajas', 1044);

        $descarga->refresh();
        $this->assertSame($tarifa->id, $descarga->tarifa_id);
        $this->assertSame('FREIDORA 7.2', $descarga->producto);
        $this->assertSame(1044, (int) $descarga->cajas);
        $this->assertSame('Walmart', $descarga->operacion);
        $this->assertSame(2, $descarga->participantes()->count());
        $this->assertEquals(60.0, (float) $descarga->participantes()->where('talana_trabajador_id', $workerA->id)->value('porcentaje_participacion'));
    }

    public function test_bulk_worker_assignment_applies_the_same_crew_to_selected_containers(): void
    {
        $user = $this->createSuperAdminUser();
        $centro = $this->createCentroCosto('CD Masivo QA');
        $tarifa = $this->createTarifa('CNTMASIVO', 75000, 36000, 'MASIVO QA', false, $centro);
        $workerA = $this->createTalanaWorker('Trabajador Masivo A', $centro);
        $workerB = $this->createTalanaWorker('Trabajador Masivo B', $centro);

        $ids = [];
        foreach (['CONT-MASIVO-001', 'CONT-MASIVO-002', 'CONT-MASIVO-003'] as $contenedor) {
            $this->actingAs($user)
                ->post(route('descarga-contenedores.store'), [
                    'operacion' => 'Walmart',
                    'centro_costo_id' => $centro->id,
                    'bodega' => 'CD Masivo QA',
                    'fecha' => '2026-08-01',
                    'contenedor' => $contenedor,
                    'tarifa_id' => $tarifa->id,
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $ids[] = DescargaContenedor::where('contenedor', $contenedor)->value('id');
        }

        $this->actingAs($user)
            ->postJson(route('descarga-contenedores.equipo-masivo'), [
                'descargas' => $ids,
                'participantes_json' => json_encode([$workerA->id, $workerB->id]),
            ])
            ->assertOk()
            ->assertJsonPath('updated', 3);

        foreach ($ids as $id) {
            $descarga = DescargaContenedor::findOrFail($id);
            $this->assertSame(2, $descarga->participantes()->count());
            $this->assertEquals(50.0, (float) $descarga->participantes()->where('talana_trabajador_id', $workerA->id)->value('porcentaje_participacion'));
            $this->assertEquals(50.0, (float) $descarga->participantes()->where('talana_trabajador_id', $workerB->id)->value('porcentaje_participacion'));
        }
    }

    public function test_capture_user_cannot_bulk_assign_workers_to_someone_elses_draft(): void
    {
        $admin = $this->createSuperAdminUser();
        $capturador = $this->createContainerModuleUser(false);
        $centro = $this->createCentroCosto('CD Masivo Permiso QA');
        $tarifa = $this->createTarifa('CNTMASPERM', 75000, 36000, 'MASIVO PERMISO QA', false, $centro);
        $worker = $this->createTalanaWorker('Trabajador Masivo Permiso QA', $centro);

        $this->actingAs($admin)
            ->post(route('descarga-contenedores.store'), [
                'operacion' => 'Walmart',
                'centro_costo_id' => $centro->id,
                'bodega' => 'CD Masivo Permiso QA',
                'fecha' => '2026-08-01',
                'contenedor' => 'CONT-MASPERM-001',
                'tarifa_id' => $tarifa->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $descarga = DescargaContenedor::where('contenedor', 'CONT-MASPERM-001')->firstOrFail();

        $this->actingAs($capturador)
            ->postJson(route('descarga-contenedores.equipo-masivo'), [
                'descargas' => [$descarga->id],
                'participantes_json' => json_encode([$worker->id]),
            ])
            ->assertStatus(422);

        $this->assertSame(0, $descarga->fresh()->participantes()->count());
    }

    public function test_admins_and_bosses_can_bulk_delete_containers_but_not_liquidated_ones(): void
    {
        $admin = $this->createSuperAdminUser();
        $jefe = $this->createContainerModuleUser(true, 'JEFE_OPERACIONES', 'Jefe de Operaciones', true);
        $capturador = $this->createContainerModuleUser(false);
        $centro = $this->createCentroCosto('CD Eliminar Masivo QA');
        $tarifa = $this->createTarifa('CNTELIMAS', 75000, 36000, 'ELIMINAR MASIVO QA', false, $centro);
        $worker = $this->createTalanaWorker('Trabajador Eliminar Masivo QA', $centro);

        $ids = [];
        foreach (['CONT-ELIM-001', 'CONT-ELIM-002', 'CONT-ELIM-LIQ'] as $contenedor) {
            $this->actingAs($admin)
                ->post(route('descarga-contenedores.store'), [
                    'operacion' => 'Walmart',
                    'centro_costo_id' => $centro->id,
                    'bodega' => 'CD Eliminar Masivo QA',
                    'fecha' => '2026-08-01',
                    'contenedor' => $contenedor,
                    'tarifa_id' => $tarifa->id,
                    'participantes_json' => json_encode([$worker->id]),
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $ids[$contenedor] = DescargaContenedor::where('contenedor', $contenedor)->value('id');
        }

        $liquidado = DescargaContenedor::findOrFail($ids['CONT-ELIM-LIQ']);
        $this->actingAs($admin)
            ->patch(route('descarga-contenedores.validar', $liquidado))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->actingAs($admin)
            ->patch(route('descarga-contenedores.liquidar', $liquidado))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($capturador)
            ->postJson(route('descarga-contenedores.eliminar-masivo'), [
                'descargas' => [$ids['CONT-ELIM-001']],
            ])
            ->assertForbidden();

        $this->actingAs($jefe)
            ->get(route('descarga-contenedores.index'))
            ->assertOk()
            ->assertSee('data-bulk-delete', false);

        $response = $this->actingAs($jefe)
            ->postJson(route('descarga-contenedores.eliminar-masivo'), [
                'descargas' => [$ids['CONT-ELIM-001'], $ids['CONT-ELIM-LIQ']],
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('skipped', 1);
        $this->assertEqualsCanonicalizing(
            [(int) $ids['CONT-ELIM-001']],
            array_map('intval', $response->json('deleted'))
        );

        $this->assertSoftDeleted('descarga_contenedores', ['id' => $ids['CONT-ELIM-001']]);
        $this->assertNotSoftDeleted('descarga_contenedores', ['id' => $ids['CONT-ELIM-LIQ']]);
        $this->assertNotSoftDeleted('descarga_contenedores', ['id' => $ids['CONT-ELIM-002']]);
    }

    private function createSuperAdminUser(): User
    {
        $role = Rol::firstOrCreate(
            ['codigo' => 'SUPER_ADMIN'],
            ['nombre' => 'Super Admin']
        );

        $user = User::create([
            'name' => 'Supervisor Descarga Test',
            'email' => 'descarga-superadmin-' . uniqid() . '@saep.local',
            'rol_id' => $role->id,
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

        return $user;
    }

    private function createContainerModuleUser(bool $puedeEditar, ?string $roleCode = null, ?string $roleName = null, bool $puedeEliminar = false): User
    {
        $roleCode ??= 'CONTENEDORES_' . ($puedeEditar ? 'COORDINADOR' : 'CAPTURADOR');
        $roleName ??= $puedeEditar ? 'Coordinador Contenedores' : 'Capturador Contenedores';

        $role = Rol::firstOrCreate(
            ['codigo' => $roleCode],
            ['nombre' => $roleName]
        );

        $modulo = Modulo::where('slug', 'descarga_contenedores')->firstOrFail();
        $role->modulos()->syncWithoutDetaching([
            $modulo->id => [
                'puede_ver' => true,
                'puede_crear' => true,
                'puede_editar' => $puedeEditar,
                'puede_eliminar' => $puedeEliminar,
            ],
        ]);

        $user = User::create([
            'name' => 'Usuario Captura Contenedores',
            'email' => 'captura-contenedores-' . uniqid() . '@saep.local',
            'rol_id' => $role->id,
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

        return $user;
    }

    private function createCentroCosto(string $nombre): CentroCosto
    {
        return CentroCosto::create([
            'codigo' => 'DC-' . strtoupper(substr(md5($nombre . uniqid()), 0, 10)),
            'nombre' => $nombre,
            'razon_social' => 'NORMAL',
            'activo' => true,
        ]);
    }

    private function createTarifa(
        string $codigo,
        float $costo,
        float $pago,
        string $proceso = 'CONTENEDOR QA',
        bool $requiereRevision = false,
        ?CentroCosto $centro = null
    ): DescargaContenedorTarifa {
        return DescargaContenedorTarifa::create([
            'cliente' => 'WM',
            'centro_costo_id' => $centro?->id,
            'codigo' => $codigo,
            'proceso' => $proceso,
            'costo_unitario' => $costo,
            'pago_colaborador' => $pago,
            'requiere_revision' => $requiereRevision,
            'activo' => true,
        ]);
    }

    private function createTalanaWorker(string $nombre, CentroCosto $centro, array $overrides = []): TalanaTrabajador
    {
        $suffix = strtoupper(substr(md5($nombre . uniqid()), 0, 6));
        $centroNombreTalana = $this->managedCenterNameForTest($centro->nombre);

        return TalanaTrabajador::create(array_merge([
            'talana_id' => 'TAL-' . $suffix,
            'rut' => random_int(10000000, 25000000) . 'K',
            'nombre' => $nombre,
            'apellido_paterno' => 'QA',
            'email' => strtolower(str_replace(' ', '.', $nombre)) . '.' . strtolower($suffix) . '@talana.test',
            'centro_costo_id' => $centro->id,
            'centro_costo_nombre' => $centroNombreTalana,
            'cargo_nombre' => 'Descargador',
            'activo' => true,
            'origen' => 'talana_csv',
            'raw_payload' => ['test' => true],
        ], $overrides));
    }

    private function managedCenterNameForTest(string $nombre): string
    {
        foreach (['LTS ', 'MAERSK ', 'DHL ', 'ECOMMERCE', 'TRANSPORTE', 'BRAZO'] as $keyword) {
            if (str_contains(mb_strtoupper($nombre), $keyword)) {
                return $nombre;
            }
        }

        return 'LTS QUILICURA ' . $nombre;
    }
}
