<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
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
            ->assertDontSee('Pago estimado')
            ->assertDontSee('"pago_colaborador"', false)
            ->assertDontSee('Pago $');

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

    public function test_capture_only_user_detail_hides_payment_amounts(): void
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
            ->assertDontSee('Costo unitario')
            ->assertDontSee('Pago colaboradores')
            ->assertDontSee('Tarifas relacionadas')
            ->assertDontSee('Monto')
            ->assertDontSee('$36.000');
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
            ->assertDontSee('Costo unitario')
            ->assertDontSee('Pago colaboradores')
            ->assertDontSee('Tarifas relacionadas')
            ->assertDontSee('Monto')
            ->assertDontSee('$36.000');

        $this->actingAs($operativo)
            ->get(route('descarga-contenedores.edit', $descarga))
            ->assertOk()
            ->assertSee('Tarifa FACT')
            ->assertDontSee('Pago estimado')
            ->assertDontSee('"pago_colaborador"', false)
            ->assertDontSee('Pago $');

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
        $coordinador = $this->createContainerModuleUser(true, 'COORDINADOR', 'Coordinador');
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

    private function createContainerModuleUser(bool $puedeEditar, ?string $roleCode = null, ?string $roleName = null): User
    {
        $roleCode ??= 'CONTENEDORES_' . ($puedeEditar ? 'COORDINACION' : 'CAPTURA');
        $roleName ??= $puedeEditar ? 'Coordinación Contenedores' : 'Captura Contenedores';

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
                'puede_eliminar' => false,
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
        bool $requiereRevision = false
    ): DescargaContenedorTarifa {
        return DescargaContenedorTarifa::create([
            'cliente' => 'WM',
            'codigo' => $codigo,
            'proceso' => $proceso,
            'costo_unitario' => $costo,
            'pago_colaborador' => $pago,
            'requiere_revision' => $requiereRevision,
            'activo' => true,
        ]);
    }

    private function createTalanaWorker(string $nombre, CentroCosto $centro): TalanaTrabajador
    {
        $suffix = strtoupper(substr(md5($nombre . uniqid()), 0, 6));

        return TalanaTrabajador::create([
            'talana_id' => 'TAL-' . $suffix,
            'rut' => random_int(10000000, 25000000) . 'K',
            'nombre' => $nombre,
            'apellido_paterno' => 'QA',
            'email' => strtolower(str_replace(' ', '.', $nombre)) . '.' . strtolower($suffix) . '@talana.test',
            'centro_costo_id' => $centro->id,
            'centro_costo_nombre' => $centro->nombre,
            'cargo_nombre' => 'Descargador',
            'activo' => true,
            'origen' => 'talana_csv',
            'raw_payload' => ['test' => true],
        ]);
    }
}
