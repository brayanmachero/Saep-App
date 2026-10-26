<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\User;
use App\Modules\Comercial\database\seeders\ComercialSeeder;
use App\Modules\Comercial\Models\CentroCosto;
use App\Modules\Comercial\Models\Cliente;
use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Models\CotizacionAuditoria;
use App\Modules\Comercial\Models\Modalidad;
use App\Modules\Comercial\Services\CalculadoraCotizacionService;
use App\Modules\Comercial\Services\GeneradorPDFService;
use App\Modules\Comercial\Services\ImportadorHistoricoCotizacionesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ComercialCotizacionFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('session.driver', 'array');
        Config::set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->withoutMiddleware([
            VerificarConsentimientoDatos::class,
            ForcePasswordChange::class,
            VerifyCsrfToken::class,
        ]);

        $this->mock(GeneradorPDFService::class, function (MockInterface $mock) {
            $mock->shouldReceive('guardarPDFFinal')->andReturn([
                'path' => 'testing/cotizacion-final.pdf',
                'hash' => sha1('cotizacion-final'),
                'generado_at' => now(),
            ]);
            $mock->shouldReceive('descargar')->andReturn(response('PDF'));
            $mock->shouldReceive('contenidoPDF')->andReturn('%PDF-test');
        });

        $this->createTestSchema();
        $this->seed(ComercialSeeder::class);
    }

    public function test_flujo_crea_aprueba_reemplaza_duplica_y_marca_no_vigente(): void
    {
        $admin = $this->createAdminUser();
        ['cliente' => $cliente, 'centro' => $centro, 'modalidad' => $modalidad] = $this->createCommercialFixture();

        $this->actingAs($admin);

        $this->post(route('comercial.cotizaciones.store'), $this->quotePayload($cliente, $centro, $modalidad, 700000))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $primera = Cotizacion::firstOrFail();
        $this->assertSame(Cotizacion::ESTADO_EN_COTIZACION, $primera->estado);

        $this->patch(route('comercial.cotizaciones.aprobar', $primera))
            ->assertRedirect()
            ->assertSessionHas('success');

        $primera->refresh();
        $this->assertSame(Cotizacion::ESTADO_VIGENTE, $primera->estado);
        $this->assertNotNull($primera->fecha_vigencia);
        $this->assertSame('testing/cotizacion-final.pdf', $primera->pdf_final_path);

        $this->post(route('comercial.cotizaciones.store'), $this->quotePayload($cliente, $centro, $modalidad, 760000))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $segunda = Cotizacion::whereKeyNot($primera->id)->firstOrFail();

        $this->patch(route('comercial.cotizaciones.aprobar', $segunda))
            ->assertRedirect()
            ->assertSessionHas('success');

        $primera->refresh();
        $segunda->refresh();
        $this->assertSame(Cotizacion::ESTADO_NO_VIGENTE, $primera->estado);
        $this->assertSame(Cotizacion::ESTADO_VIGENTE, $segunda->estado);
        $this->assertNotNull($primera->fecha_fin_vigencia_real);

        $this->post(route('comercial.cotizaciones.duplicar', $segunda))
            ->assertRedirect()
            ->assertSessionHas('success');

        $duplicada = Cotizacion::latest('id')->firstOrFail();
        $this->assertSame(Cotizacion::ESTADO_EN_COTIZACION, $duplicada->estado);
        $this->assertSame($segunda->id, $duplicada->cotizacion_anterior_id);

        $this->patch(route('comercial.cotizaciones.rechazar', $duplicada), [
            'motivo' => 'Prueba de descarte controlado',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $duplicada->refresh();
        $this->assertSame(Cotizacion::ESTADO_NO_VIGENTE, $duplicada->estado);
        $this->assertTrue(
            CotizacionAuditoria::where('cotizacion_id', $duplicada->id)
                ->where('accion', 'no_vigente')
                ->exists()
        );
    }

    public function test_estados_antiguos_se_normalizan_a_los_tres_estados_operativos(): void
    {
        $this->assertSame(Cotizacion::ESTADO_VIGENTE, Cotizacion::normalizarEstado('aprobada'));
        $this->assertSame(Cotizacion::ESTADO_NO_VIGENTE, Cotizacion::normalizarEstado('rechazada'));
        $this->assertSame(Cotizacion::ESTADO_NO_VIGENTE, Cotizacion::normalizarEstado('cancelada'));
        $this->assertSame(Cotizacion::ESTADO_EN_COTIZACION, Cotizacion::normalizarEstado('en_cotizacion'));
    }

    public function test_reajuste_ipc_crea_una_nueva_version_y_conserva_la_cotizacion_origen(): void
    {
        $admin = $this->createAdminUser();
        ['cliente' => $cliente, 'centro' => $centro, 'modalidad' => $modalidad] = $this->createCommercialFixture();
        $this->actingAs($admin);

        $this->post(route('comercial.cotizaciones.store'), $this->quotePayload($cliente, $centro, $modalidad, 700000))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $origen = Cotizacion::firstOrFail();
        $precioOrigen = (float) $origen->precio_venta;
        $sueldoOrigen = (float) $origen->detalles()
            ->where('concepto', 'Sueldo Base')
            ->value('valor');

        $this->post(route('comercial.cotizaciones.reajustar-ipc', $origen), [
            'ipc_porcentaje' => 2.8,
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $reajustada = Cotizacion::latest('id')->firstOrFail();
        $origen->refresh();

        $this->assertNotSame($origen->id, $reajustada->id);
        $this->assertSame(Cotizacion::ESTADO_EN_COTIZACION, $reajustada->estado);
        $this->assertSame($origen->id, $reajustada->cotizacion_anterior_id);
        $this->assertSame(((int) $origen->version) + 1, (int) $reajustada->version);
        $this->assertSame(Cotizacion::ESTADO_EN_COTIZACION, $origen->estado);
        $this->assertEqualsWithDelta(round($precioOrigen * 1.028, 2), (float) $reajustada->precio_venta, 0.01);
        $this->assertEqualsWithDelta(round($sueldoOrigen * 1.028, 2), (float) $reajustada->detalles()
            ->where('concepto', 'Sueldo Base')
            ->value('valor'), 0.01);
        $this->assertSame('ipc', data_get($reajustada->datos_calculo, 'reajuste.tipo'));
        $this->assertSame(2.8, (float) data_get($reajustada->datos_calculo, 'reajuste.porcentaje'));
        $this->assertTrue(CotizacionAuditoria::query()
            ->where('cotizacion_id', $reajustada->id)
            ->where('accion', 'reajustada_ipc')
            ->exists());
    }

    public function test_editar_una_version_abre_el_editor_completo_de_cotizaciones(): void
    {
        $admin = $this->createAdminUser();
        ['cliente' => $cliente, 'centro' => $centro, 'modalidad' => $modalidad] = $this->createCommercialFixture();
        $this->actingAs($admin);

        $this->post(route('comercial.cotizaciones.store'), $this->quotePayload($cliente, $centro, $modalidad, 700000))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cotizacion = Cotizacion::firstOrFail();

        $this->get(route('comercial.cotizaciones.edit', $cotizacion))
            ->assertOk()
            ->assertViewIs('comercial::cotizador.create')
            ->assertViewHas('cotizacion', fn (Cotizacion $vista) => $vista->is($cotizacion))
            ->assertViewHas('parametrosPorCategoria')
            ->assertViewHas('uniformesCatalogo');
    }

    public function test_calculo_resiste_lote_local_de_cotizaciones_sin_errores(): void
    {
        $this->createCommercialFixture();
        $calculadora = app(CalculadoraCotizacionService::class);
        $modalidades = Modalidad::orderBy('codigo')->get()->values();
        $iteraciones = 300;
        $inicio = microtime(true);

        for ($i = 0; $i < $iteraciones; $i++) {
            $modalidad = $modalidades[$i % $modalidades->count()];
            $resultado = $calculadora->calcular($this->calculationPayload($modalidad, 620000 + ($i * 750)));

            $this->assertGreaterThan(0, $resultado['precio_venta']);
            $this->assertNotEmpty($resultado['detalles']);
        }

        $duracion = microtime(true) - $inicio;
        $this->assertLessThan(15, $duracion, "El lote de {$iteraciones} cálculos tardó {$duracion} segundos.");
    }

    public function test_importa_fotografia_historica_con_fecha_del_origen_sin_recalcularla(): void
    {
        $cliente = Cliente::create([
            'rut' => '76111222-3',
            'nombre' => 'DHL',
            'nombre_comercial' => 'DHL',
            'estado' => 'activo',
        ]);
        CentroCosto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'DHL GLOBAL EST',
            'codigo' => 'HIST-DHL-GLOBAL-EST',
            'estado' => 'activo',
        ]);

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'saep-historico-'.uniqid();
        mkdir($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'DHL GLOBAL EST - TARIFAS 2026.xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['COTIZACIÓN 03-06-2026', null],
            ['OPERARIO DE BODEGA', null],
            ['SUELDO BASE', 595000],
            ['TOTAL HABERES', 715000],
            ['TOTAL COTIZACIONES', 58200],
            ['TOTAL PROVISIONES', 45500],
            ['GASTOS ADMINISTRACIÓN', 28500],
            ['SERVICIOS DE MOVILIZACION', 0],
            ['COSTO BRUTO', 847200],
            ['MARGEN', 9],
            ['PRECIO VENTA', 923448],
        ]);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        try {
            $service = app(ImportadorHistoricoCotizacionesService::class);
            $analysis = $service->analizar($path, $directory);

            $this->assertSame('listo', $analysis['status']);
            $this->assertCount(1, $analysis['records']);
            $record = $analysis['records'][0];
            $this->assertSame('2026-06-03', $record['fecha_cotizacion']->toDateString());
            $this->assertSame('OPERARIO DE BODEGA', $record['cargo']);
            $this->assertSame(923448.0, $record['totales']['precio_venta']);

            $cotizacion = $service->importar($record);
            $cotizacion->refresh();

            $this->assertSame(Cotizacion::ESTADO_NO_VIGENTE, $cotizacion->estado);
            $this->assertSame('2026-06-03', $cotizacion->fecha_cotizacion->toDateString());
            $this->assertSame('923448.00', $cotizacion->precio_venta);
            $this->assertTrue((bool) data_get($cotizacion->datos_calculo, 'es_fotografia_historica'));
            $this->assertSame('contenido_cotizacion', data_get($cotizacion->datos_calculo, 'origen.fecha_fuente'));
            app(ImportadorHistoricoCotizacionesService::class)->importar($record);
            $this->assertSame(1, Cotizacion::count());
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    public function test_separa_tarifas_horizontales_por_cargo_y_fecha_de_cotizacion(): void
    {
        $cliente = Cliente::create([
            'rut' => '76111222-3',
            'nombre' => 'DHL',
            'nombre_comercial' => 'DHL',
            'estado' => 'activo',
        ]);
        CentroCosto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'DHL GLOBAL EST',
            'codigo' => 'HIST-DHL-GLOBAL-EST',
            'estado' => 'activo',
        ]);

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'saep-historico-horizontal-'.uniqid();
        mkdir($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'DHL GLOBAL EST - TARIFAS 2026.xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['COTIZACIÓN 03-06-2026', null, null, null, null, 'COTIZACIÓN 04-06-2026'],
            ['OPERARIO DE BODEGA', null, null, null, null, 'SUPERVISOR LOGÍSTICO'],
            ['SUELDO BASE', 595000, null, null, null, 'SUELDO BASE', 680000],
            ['TOTAL HABERES', 715000, null, null, null, 'TOTAL HABERES', 800000],
            ['TOTAL COTIZACIONES', 58200, null, null, null, 'TOTAL COTIZACIONES', 65000],
            ['TOTAL PROVISIONES', 45500, null, null, null, 'TOTAL PROVISIONES', 52000],
            ['GASTOS ADMINISTRACIÓN', 28500, null, null, null, 'GASTOS ADMINISTRACIÓN', 32000],
            ['COSTO BRUTO', 847200, null, null, null, 'COSTO BRUTO', 949000],
            ['MARGEN', 9, null, null, null, 'MARGEN', 10],
            ['PRECIO VENTA', 923448, null, null, null, 'PRECIO VENTA', 1043900],
        ]);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        try {
            $analysis = app(ImportadorHistoricoCotizacionesService::class)->analizar($path, $directory);

            $this->assertSame('listo', $analysis['status']);
            $this->assertCount(2, $analysis['records']);
            $this->assertNotSame($analysis['records'][0]['numero'], $analysis['records'][1]['numero']);
            $this->assertSame('OPERARIO DE BODEGA', $analysis['records'][0]['cargo']);
            $this->assertSame('2026-06-03', $analysis['records'][0]['fecha_cotizacion']->toDateString());
            $this->assertSame('SUPERVISOR LOGÍSTICO', $analysis['records'][1]['cargo']);
            $this->assertSame('2026-06-04', $analysis['records'][1]['fecha_cotizacion']->toDateString());
            $this->assertSame(1043900.0, $analysis['records'][1]['totales']['precio_venta']);
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    public function test_sabana_importa_sin_aprobar_ni_duplicar_y_conserva_revision(): void
    {
        (require app_path('Modules/Comercial/database/migrations/2026_10_13_000001_create_comercial_tarifas_referencia.php'))->up();
        $service = app(\App\Modules\Comercial\Services\SabanaTarifasService::class);
        $lote = [
            'fuentes' => [['clave' => 'idl', 'archivo' => 'IDL.xlsx', 'hash_sha256' => str_repeat('a', 64), 'estado' => 'parcial']],
            'tarifas' => [[
                'fuente_clave' => 'idl', 'cliente' => 'Cliente prueba', 'centro' => 'Centro Sur',
                'cargo' => 'Operario', 'modalidad' => 'EST', 'unidad' => 'mes', 'precio' => 123456.789012,
                'fecha_referencia' => '2026-06-01', 'hoja' => 'P626', 'celda' => 'AB3',
                'conceptos' => [['concepto' => 'Sueldo base', 'valor' => 529000, 'celda' => 'E3']],
            ]],
        ];
        $this->assertSame(1, $service->importar($lote)['nuevas']);
        $tarifa = \App\Modules\Comercial\Models\TarifaReferencia::firstOrFail();
        $this->assertSame('pendiente', $tarifa->estado);
        $this->assertSame('123456.789012', $tarifa->precio);
        $this->assertSame(0, $service->consulta(['vista' => 'vigentes'])->count());
        $this->actingAs($this->createAdminUser());
        $this->patch(route('comercial.tarifas.revisar', $tarifa), [
            'categoria' => 'Operaciones', 'modalidad' => 'EST', 'unidad' => 'mes',
            'estado' => 'confirmada', 'vigente_desde' => '2026-06-01', 'motivo' => 'Contrato revisado',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0, $service->importar($lote)['nuevas']);
        $this->assertSame('confirmada', $tarifa->fresh()->estado);
        $this->assertSame(1, $tarifa->revisiones()->count());
        $this->assertSame(1, $service->consulta(['vista' => 'vigentes', 'fecha' => '2026-06-01', 'categoria' => 'Operaciones'])->count());
        $this->assertSame(0, $service->consulta(['vista' => 'vigentes', 'fecha' => '2026-05-31'])->count());
        $this->get(route('comercial.tarifas.index', ['cliente' => 'Cliente prueba']))->assertOk()->assertSee('Sábana de tarifas')->assertSee('Operario');
        $this->get(route('comercial.tarifas.show', $tarifa))->assertOk()->assertSee('Sueldo base')->assertSee('Contrato revisado');
        $export = $this->get(route('comercial.tarifas.export', ['cliente' => 'Cliente prueba']))->assertOk();
        $this->assertStringContainsString('123456.789012', $export->streamedContent());
    }

    public function test_sabana_resuelve_versiones_sin_reactivar_tarifas_vencidas_y_respeta_unidades(): void
    {
        (require app_path('Modules/Comercial/database/migrations/2026_10_13_000001_create_comercial_tarifas_referencia.php'))->up();
        $service = app(\App\Modules\Comercial\Services\SabanaTarifasService::class);
        $modelo = \App\Modules\Comercial\Models\TarifaReferencia::class;
        $datos = ['cliente' => 'A', 'centro' => 'Sur', 'cargo' => 'Operario', 'unidad' => 'mes', 'moneda' => 'CLP', 'precio' => 100, 'estado' => 'confirmada'];
        $base = $modelo::create($datos + ['identidad' => str_repeat('a',64), 'grupo' => $modelo::grupoPara($datos), 'vigente_desde' => '2026-01-01']);
        $modelo::create(array_merge($datos, ['precio' => 200, 'identidad' => str_repeat('b',64), 'grupo' => $base->grupo, 'vigente_desde' => '2026-06-01', 'vigente_hasta' => '2026-06-30']));
        $modelo::create(array_merge($datos, ['precio' => 300, 'estado' => 'pendiente', 'identidad' => str_repeat('c',64), 'grupo' => $base->grupo, 'vigente_desde' => '2026-07-01']));
        $this->assertSame('100.000000', $service->consulta(['vista' => 'vigentes', 'fecha' => '2026-05-31'])->first()->precio);
        $this->assertSame('200.000000', $service->consulta(['vista' => 'vigentes', 'fecha' => '2026-06-30'])->first()->precio);
        $this->assertSame(0, $service->consulta(['vista' => 'vigentes', 'fecha' => '2026-07-01'])->count());
        $diaria = array_merge($datos, ['unidad' => 'día', 'precio' => 50]);
        $modelo::create($diaria + ['identidad' => str_repeat('d',64), 'grupo' => $modelo::grupoPara($diaria), 'vigente_desde' => '2026-01-01']);
        $this->assertSame(2, $service->consulta(['vista' => 'vigentes', 'fecha' => '2026-06-01'])->count());
        $this->assertSame(1, $service->consulta(['vista' => 'vigentes', 'fecha' => '2026-06-01', 'unidad' => 'día'])->count());
        $this->assertSame(0, $service->consulta(['cliente' => 'Otro'])->count());
        $base->update(['fecha_referencia' => '2025-01-01']);
        $posterior = $modelo::where('identidad', str_repeat('b',64))->firstOrFail();
        $posterior->update(['fecha_referencia' => '2026-06-01']);
        $this->assertFalse($service->consulta(['vista' => 'ultimas'])->get()->contains('id', $base->id));
        $this->assertTrue($service->consulta(['vista' => 'ultimas', 'anio' => 2025])->get()->contains('id', $base->id));
    }

    public function test_sabana_impide_aprobar_sin_datos_y_crea_nuevas_versiones_sin_sobrescribir(): void
    {
        (require app_path('Modules/Comercial/database/migrations/2026_10_13_000001_create_comercial_tarifas_referencia.php'))->up();
        $this->get(route('comercial.tarifas.index'))->assertRedirect(route('login'));
        $this->actingAs($this->createAdminUser());
        $payload = ['cliente' => 'A', 'cargo' => 'Operario', 'unidad' => 'mes', 'moneda' => 'CLP', 'precio' => 100, 'fecha_referencia' => '2026-01-01', 'observaciones' => 'Acuerdo comercial'];
        $this->post(route('comercial.tarifas.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $tarifa = \App\Modules\Comercial\Models\TarifaReferencia::firstOrFail();
        $this->patch(route('comercial.tarifas.revisar', $tarifa), ['estado' => 'confirmada'])->assertSessionHasErrors(['modalidad', 'unidad', 'vigente_desde', 'motivo']);
        $this->post(route('comercial.tarifas.store'), array_merge($payload, ['precio' => 200]))->assertSessionHasNoErrors();
        $this->assertSame(2, \App\Modules\Comercial\Models\TarifaReferencia::count());
        $this->assertSame('100.000000', $tarifa->fresh()->precio);
        $this->assertSame('pendiente', $tarifa->fresh()->estado);
        $usuario = auth()->user();
        $usuario->rol->update(['codigo' => 'CONSULTOR_COMERCIAL', 'puede_aprobar' => false]);
        $usuario->unsetRelation('rol');
        $this->patch(route('comercial.tarifas.revisar', $tarifa), [
            'estado' => 'confirmada', 'modalidad' => 'EST', 'unidad' => 'mes',
            'vigente_desde' => '2026-01-01', 'motivo' => 'Intento sin permiso',
        ])->assertForbidden();
    }

    public function test_homologacion_solo_corrige_clientes_inequivocos_y_conserva_auditoria(): void
    {
        $command = \App\Console\Commands\HomologarTarifas::class;
        $masters = [['centro' => 'Bodega Sur EST', 'cliente' => 'Cliente Uno'], ['centro' => 'DHL Norte EST', 'cliente' => 'DHL']];
        $this->assertSame('Cliente Uno', $command::resolver('Bodega Sur SUB.', $masters));
        $this->assertSame('DHL', $command::resolver('DHL Aramark', $masters));
        $this->assertNull($command::resolver('DHLotro', $masters));
        $this->assertSame('DHL', $command::resolver('16-FINAL DHL Aramark', $masters));
        $lts = [['centro' => 'LTS LO AGUIRRE SUB', 'cliente' => 'Walmart Chile'], ['centro' => 'LTS PEÑON EST', 'cliente' => 'Walmart Chile']];
        $this->assertSame('Walmart Chile', $command::resolver('14.1-FINAL LTS QUILICURA SUB', $lts));
        $this->assertNull($command::resolver('LTS QUILICURA', array_merge($lts, [['centro' => 'LTS OTRO', 'cliente' => 'Otro']])));
        $this->assertNull($command::resolver('Bodega Sur', array_merge($masters, [['centro' => 'Bodega Sur SUB', 'cliente' => 'Otro']])));
        (require app_path('Modules/Comercial/database/migrations/2026_10_13_000001_create_comercial_tarifas_referencia.php'))->up();
        $this->createCommercialFixture();
        $modelo = \App\Modules\Comercial\Models\TarifaReferencia::class;
        $base = ['cliente' => 'Por homologar', 'centro' => 'Cliente Prueba Norte', 'cargo' => 'Operario', 'precio' => 123456.123456, 'estado' => 'pendiente'];
        $tarifa = $modelo::create($base + ['identidad' => str_repeat('a', 64), 'grupo' => $modelo::grupoPara($base)]);
        $reviewed = $modelo::create($base + ['identidad' => str_repeat('b', 64), 'grupo' => $tarifa->grupo, 'revisado_at' => now()]);
        $this->artisan('comercial:homologar-tarifas')->assertExitCode(0);
        $this->assertSame('Por homologar', $tarifa->fresh()->cliente);
        $this->artisan('comercial:homologar-tarifas', ['--aplicar' => true])->assertExitCode(0);
        $this->assertSame('Cliente Prueba', $tarifa->fresh()->cliente);
        $this->assertSame('Por homologar', $reviewed->fresh()->cliente);
        $this->assertSame('123456.123456', $tarifa->fresh()->precio);
        $this->assertSame('pendiente', $tarifa->fresh()->estado);
        $this->assertNull($tarifa->fresh()->vigente_desde);
        $this->assertSame('Por homologar', $tarifa->revisiones()->first()->antes['cliente']);
        $this->artisan('comercial:homologar-tarifas', ['--aplicar' => true])->assertExitCode(0);
        $this->assertSame(1, $tarifa->revisiones()->count());
    }

    private function createAdminUser(): User
    {
        $rol = Rol::create([
            'codigo' => 'SUPER_ADMIN',
            'nombre' => 'Super Admin',
            'puede_crear_forms' => true,
            'puede_aprobar' => true,
            'puede_ver_dashboard' => true,
            'puede_admin_usuarios' => true,
        ]);

        $modulo = Modulo::create([
            'slug' => 'comercial',
            'nombre' => 'Comercial',
            'descripcion' => 'Modulo comercial',
            'grupo' => 'COMERCIAL',
            'orden' => 1,
            'activo' => true,
        ]);

        $rol->modulos()->attach($modulo->id, [
            'puede_ver' => true,
            'puede_crear' => true,
            'puede_editar' => true,
            'puede_eliminar' => true,
        ]);

        return User::create([
            'name' => 'Admin Comercial',
            'email' => 'admin.comercial@example.test',
            'password' => 'password',
            'rol_id' => $rol->id,
            'activo' => true,
            'must_change_password' => false,
            'acepta_politica_datos' => true,
        ]);
    }

    private function createCommercialFixture(): array
    {
        $cliente = Cliente::firstOrCreate(
            ['rut' => '76123456-7'],
            [
                'nombre' => 'Cliente Prueba',
                'nombre_comercial' => 'Cliente Prueba',
                'email' => 'cliente@example.test',
                'estado' => 'activo',
            ]
        );

        $centro = CentroCosto::firstOrCreate(
            ['codigo' => 'CC-TEST-001'],
            [
                'cliente_id' => $cliente->id,
                'nombre' => 'Centro Operaciones',
                'estado' => 'activo',
            ]
        );

        return [
            'cliente' => $cliente,
            'centro' => $centro,
            'modalidad' => Modalidad::where('codigo', 'EST')->firstOrFail(),
        ];
    }

    private function quotePayload(Cliente $cliente, CentroCosto $centro, Modalidad $modalidad, int $sueldoBase): array
    {
        return array_merge($this->calculationPayload($modalidad, $sueldoBase), [
            'titulo' => 'Cotización test',
            'cargo' => 'Operario de Producción',
            'cliente_id' => $cliente->id,
            'centro_costo_id' => $centro->id,
            'fecha_vigencia_desde' => now()->toDateString(),
            'fecha_vigencia_hasta' => now()->addDays(30)->toDateString(),
            'observaciones' => 'Prueba automatizada del flujo comercial.',
        ]);
    }

    private function calculationPayload(Modalidad $modalidad, int $sueldoBase): array
    {
        return [
            'modalidad_id' => $modalidad->id,
            'remuneraciones' => [
                ['concepto' => 'Sueldo Base', 'valor' => $sueldoBase],
                ['concepto' => 'Bono Asistencia', 'valor' => 45000],
                ['concepto' => 'Bono Compromiso', 'valor' => 25000],
            ],
            'uniformes' => [
                ['descripcion' => 'Uniforme base', 'cantidad' => 2, 'precio_unitario' => 11500],
            ],
            'asignacion_movilizacion' => 35000,
            'asignacion_colacion' => 55000,
            'servicios_casino' => 12000,
            'seguro_accidentes' => 5000,
            'otros_gastos' => 8000,
            'otros_beneficios' => 5000,
        ];
    }

    public function test_consulta_operativa_agrupa_sin_exponer_formulas_y_descarga_solo_respaldo_verificado(): void
    {
        (require app_path('Modules/Comercial/database/migrations/2026_10_13_000001_create_comercial_tarifas_referencia.php'))->up();
        $service = app(\App\Modules\Comercial\Services\SabanaTarifasService::class);
        $lote = [
            'fuentes' => [['clave' => 'idl', 'archivo' => 'IDL.xlsx', 'hash_sha256' => str_repeat('a', 64), 'estado' => 'parcial']],
            'tarifas' => [[
                'fuente_clave' => 'idl', 'cliente' => 'Cliente prueba', 'centro' => 'Centro Sur',
                'cargo' => 'Operario', 'modalidad' => 'EST', 'unidad' => 'mes', 'precio' => 123456.789012,
                'fecha_referencia' => '2026-06-01', 'hoja' => 'P626', 'celda' => 'AB3',
                'conceptos' => [['concepto' => 'Sueldo base', 'valor' => 529000, 'celda' => 'E3']],
            ]],
        ];
        $service->importar($lote);
        $tarifa = \App\Modules\Comercial\Models\TarifaReferencia::firstOrFail();
        $tarifa->update(['conceptos'=>[
            ['concepto'=>'Sueldo base','valor'=>745000,'celda'=>'G6','formula'=>'=SECRET_FORMULA'],
            ['concepto'=>'Bono Asistencia','valor'=>0],
            ['concepto'=>'Total Imponible','valor'=>931250],
            ['concepto'=>'SIS','valor'=>10000],
        ]]);
        $this->actingAs($this->createAdminUser());
        $page = $this->get(route('comercial.tarifas.show', $tarifa));
        $page->assertOk()->assertSee('Precio por confirmar')->assertSee('Remuneraciones')->assertSee('Costos del empleador')->assertDontSee('SECRET_FORMULA')->assertDontSee('Fórmula original');
        $groups = app(\App\Modules\Comercial\Services\TarifaPresentacionService::class)->grupos($tarifa->conceptos);
        $originalStorage = storage_path();
        $temporaryStorage = sys_get_temp_dir().'/comercial-'.bin2hex(random_bytes(8));
        $directory = $temporaryStorage.'/app/private/cotizador-evaluacion-20261013';
        mkdir($directory, 0777, true);
        $zip = new \ZipArchive;
        $zip->open($directory.'/2026.zip', \ZipArchive::CREATE);
        $zip->addFromString('carpeta/IDL.xlsx', 'respaldo de prueba');
        $zip->close();
        $this->app->useStoragePath($temporaryStorage);
        try {
            $this->get(route('comercial.tarifas.archivo', $tarifa))->assertNotFound();
            $tarifa->fuente->update(['hash_sha256'=>hash('sha256', 'respaldo de prueba')]);
            $download = $this->get(route('comercial.tarifas.archivo', $tarifa))->assertOk();
            $this->assertSame('respaldo de prueba', $download->streamedContent());
            auth()->logout();
            $this->get(route('comercial.tarifas.archivo', $tarifa))->assertRedirect(route('login'));
        } finally {
            $this->app->useStoragePath($originalStorage);
            unlink($directory.'/2026.zip');
            rmdir($directory); rmdir(dirname($directory)); rmdir(dirname($directory, 2)); rmdir($temporaryStorage);
        }
        $this->assertCount(3, $groups['Remuneraciones']);
        $this->assertSame('745.000', $groups['Remuneraciones'][0]['valor']);
        $this->assertSame('123.456,789012', \App\Modules\Comercial\Services\TarifaPresentacionService::importe('123456.789012'));
    }

    private function createTestSchema(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'comercial_cotizacion_auditorias',
            'comercial_cotizacion_uniformes',
            'comercial_cotizacion_detalles',
            'comercial_cotizaciones',
            'comercial_cotizacion_secuencias',
            'comercial_parametros',
            'comercial_modalidades',
            'comercial_centros_costo',
            'comercial_clientes',
            'rol_modulo',
            'modulos',
            'users',
            'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->boolean('puede_crear_forms')->default(false);
            $table->boolean('puede_aprobar')->default(false);
            $table->boolean('puede_ver_dashboard')->default(false);
            $table->boolean('puede_admin_usuarios')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->foreignId('rol_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->boolean('acepta_politica_datos')->default(true);
            $table->timestamp('fecha_aceptacion_politica')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('modulos', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('icono')->nullable();
            $table->string('grupo')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('rol_modulo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();
            $table->boolean('puede_ver')->default(false);
            $table->boolean('puede_crear')->default(false);
            $table->boolean('puede_editar')->default(false);
            $table->boolean('puede_eliminar')->default(false);
            $table->timestamps();
        });

        Schema::create('comercial_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('rut')->unique();
            $table->string('nombre');
            $table->string('nombre_comercial')->nullable();
            $table->string('email')->nullable();
            $table->string('estado')->default('activo');
            $table->json('datos_adicionales')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comercial_centros_costo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('comercial_clientes')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->string('estado')->default('activo');
            $table->json('datos_adicionales')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comercial_modalidades', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('margen_operacional', 8, 2)->default(0);
            $table->decimal('sis_porcentaje', 8, 2)->default(0);
            $table->decimal('mutual_porcentaje', 8, 2)->default(0);
            $table->decimal('cesantia_porcentaje', 8, 2)->default(0);
            $table->decimal('factor_vacaciones', 8, 3)->default(0);
            $table->decimal('refprev_porcentaje', 8, 2)->default(0);
            $table->string('estado')->default('activo');
            $table->json('configuracion_adicional')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comercial_parametros', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->text('valor');
            $table->string('tipo')->default('string');
            $table->boolean('editable')->default(true);
            $table->date('fecha_vigencia_desde')->nullable();
            $table->date('fecha_vigencia_hasta')->nullable();
            $table->string('categoria')->nullable();
            $table->integer('version')->default(1);
            $table->text('valor_anterior')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comercial_cotizacion_secuencias', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio')->primary();
            $table->unsignedInteger('siguiente_numero')->default(1);
            $table->timestamps();
        });

        Schema::create('comercial_cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique()->nullable();
            $table->string('titulo')->nullable();
            $table->string('cargo');
            $table->foreignId('cliente_id')->constrained('comercial_clientes')->cascadeOnDelete();
            $table->foreignId('centro_costo_id')->constrained('comercial_centros_costo')->cascadeOnDelete();
            $table->foreignId('modalidad_id')->constrained('comercial_modalidades')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado')->default(Cotizacion::ESTADO_EN_COTIZACION);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('cotizacion_anterior_id')->nullable()->constrained('comercial_cotizaciones')->nullOnDelete();
            $table->date('fecha_cotizacion')->nullable();
            $table->date('fecha_vigencia_desde')->nullable();
            $table->date('fecha_vigencia_hasta')->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('total_remuneraciones', 15, 2)->default(0);
            $table->decimal('total_cotizaciones', 15, 2)->default(0);
            $table->decimal('total_provisiones', 15, 2)->default(0);
            $table->decimal('total_gastos', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('margen', 15, 2)->default(0);
            $table->decimal('precio_venta', 15, 2)->default(0);
            $table->json('datos_calculo')->nullable();
            $table->json('detalles_json')->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamp('fecha_vigencia')->nullable();
            $table->timestamp('fecha_fin_vigencia_real')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->string('pdf_final_path')->nullable();
            $table->string('pdf_final_hash')->nullable();
            $table->timestamp('pdf_final_generado_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comercial_cotizacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('comercial_cotizaciones')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('concepto');
            $table->text('descripcion')->nullable();
            $table->decimal('valor_base', 15, 2)->default(0);
            $table->decimal('porcentaje', 8, 2)->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->json('formula')->nullable();
            $table->json('calculos_paso_a_paso')->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comercial_cotizacion_uniformes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('comercial_cotizaciones')->cascadeOnDelete();
            $table->string('descripcion');
            $table->text('especificaciones')->nullable();
            $table->integer('cantidad')->default(0);
            $table->decimal('precio_unitario', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comercial_cotizacion_auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('comercial_cotizaciones')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion');
            $table->text('descripcion')->nullable();
            $table->json('cambios')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }
}
