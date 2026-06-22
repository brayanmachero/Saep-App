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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class ComercialCotizacionFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->withoutMiddleware([
            VerificarConsentimientoDatos::class,
            ForcePasswordChange::class,
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
