<?php

namespace Tests\Unit;

use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Services\CalculadoraESTService;
use App\Modules\Comercial\Services\CalculadoraSUBService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ComercialCalculadoraTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('comercial_parametros', function ($table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('nombre');
            $table->text('valor');
            $table->string('tipo')->default('decimal');
            $table->boolean('editable')->default(true);
            $table->date('fecha_vigencia_desde')->nullable();
            $table->date('fecha_vigencia_hasta')->nullable();
            $table->string('categoria')->nullable();
            $table->integer('version')->default(1);
            $table->text('valor_anterior')->nullable();
            $table->unsignedBigInteger('actualizado_por')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_est_calculator_uses_configured_margin(): void
    {
        $this->seedParametro('MARGEN_EST', '12');

        $result = (new CalculadoraESTService())->calcular([
            'remuneraciones' => [
                ['concepto' => 'Sueldo Base', 'valor' => 539000],
            ],
            'asignacion_movilizacion' => 10000,
            'asignacion_colacion' => 20000,
        ]);

        $this->assertSame('EST', $result['modalidad']);
        $this->assertSame(12.0, $result['margen_porcentaje']);
        $this->assertEquals(134750.0, $result['resumen_excel']['gratificacion']);
        $this->assertEquals(703750.0, $result['total_remuneraciones']);
        $this->assertGreaterThan($result['subtotal'], $result['precio_venta']);
    }

    public function test_sub_calculator_uses_configured_weekly_hours_for_overtime_factor(): void
    {
        $this->seedParametro('MARGEN_SUB', '15');
        $this->seedParametro('JORNADA_SEMANAL_SUB', '40');

        $result = (new CalculadoraSUBService())->calcular([
            'remuneraciones' => [
                ['concepto' => 'Sueldo Base', 'valor' => 539000],
            ],
        ]);

        $this->assertSame('SUB', $result['modalidad']);
        $this->assertSame(15.0, $result['margen_porcentaje']);
        $this->assertEquals(40.0, $result['horas']['jornada_semanal_hhee']);
        $this->assertEquals(0.005833, $result['horas']['factor_normal_hhee']);
        $this->assertGreaterThan($result['horas']['normal_hhee'], $result['horas']['extra_50']);
    }

    public function test_est_full_reference_case_matches_expected_totals(): void
    {
        $this->seedParametrosReferencia();

        $result = (new CalculadoraESTService())->calcular([
            'remuneraciones' => [
                ['concepto' => 'Sueldo Base', 'valor' => 600000],
                ['concepto' => 'Bono Asistencia', 'valor' => 50000],
                ['concepto' => 'Bono Compromiso', 'valor' => 25000],
                ['concepto' => 'Otros Haberes', 'valor' => 10000],
            ],
            'asignacion_movilizacion' => 20000,
            'asignacion_colacion' => 30000,
            'servicios_casino' => 15000,
            'seguro_accidentes' => 5000,
            'otros_gastos' => 8000,
            'otros_beneficios' => 12000,
            'uniformes' => [
                ['descripcion' => 'Polera', 'cantidad' => 2, 'precio_unitario' => 10000],
                ['descripcion' => 'Credencial', 'cantidad' => 1, 'precio_unitario' => 5000],
            ],
        ]);

        $this->assertSame('EST', $result['modalidad']);
        $this->assertEquals(906250.0, $result['total_remuneraciones']);
        $this->assertEquals(57882.5, $result['total_cotizaciones']);
        $this->assertEquals(53324.4, $result['total_provisiones']);
        $this->assertEquals(97473.71, $result['total_gastos']);
        $this->assertEquals(1114930.6, $result['subtotal']);
        $this->assertEquals(111493.06, $result['margen']);
        $this->assertEquals(1226423.66, $result['precio_venta']);
        $this->assertEquals(171250.0, $result['resumen_excel']['gratificacion']);
        $this->assertEquals(32473.71, $result['resumen_excel']['gastosAdministracion']);
        $this->assertEquals(6813.46, $result['horas']['normal']);
        $this->assertEquals(5713.33, $result['horas']['normal_hhee']);
        $this->assertCount(2, $result['uniformes']);
    }

    public function test_sub_full_reference_case_matches_expected_totals(): void
    {
        $this->seedParametrosReferencia();

        $result = (new CalculadoraSUBService())->calcular([
            'remuneraciones' => [
                ['concepto' => 'Sueldo Base', 'valor' => 620000],
                ['concepto' => 'Bono Asistencia', 'valor' => 40000],
                ['concepto' => 'Bono Compromiso', 'valor' => 30000],
                ['concepto' => 'Otros Haberes', 'valor' => 15000],
            ],
            'asignacion_movilizacion' => 25000,
            'asignacion_colacion' => 35000,
            'servicios_casino' => 18000,
            'seguro_accidentes' => 9000,
            'otros_gastos' => 11000,
            'otros_beneficios' => 16000,
            'uniformes' => [
                ['descripcion' => 'Uniforme base', 'cantidad' => 3, 'precio_unitario' => 8000],
            ],
        ]);

        $this->assertSame('SUB', $result['modalidad']);
        $this->assertEquals(941250.0, $result['total_remuneraciones']);
        $this->assertEquals(72967.5, $result['total_cotizaciones']);
        $this->assertEquals(135180.81, $result['total_provisiones']);
        $this->assertEquals(114821.95, $result['total_gastos']);
        $this->assertEquals(1264220.26, $result['subtotal']);
        $this->assertEquals(176990.84, $result['margen']);
        $this->assertEquals(1441211.1, $result['precio_venta']);
        $this->assertEquals(55662.69, $result['resumen_excel']['provisionVacaciones']);
        $this->assertEquals(79518.13, $result['resumen_excel']['provisionIndemnizaciones']);
        $this->assertEquals(8006.73, $result['horas']['normal']);
        $this->assertEquals(5768.65, $result['horas']['normal_hhee']);
        $this->assertEquals(0.005303, $result['horas']['factor_normal_hhee']);
    }

    public function test_cotizacion_number_generation_reserves_yearly_sequence(): void
    {
        Schema::create('comercial_cotizacion_secuencias', function ($table) {
            $table->unsignedSmallInteger('anio')->primary();
            $table->unsignedInteger('siguiente_numero')->default(1);
            $table->timestamps();
        });

        $year = now()->format('Y');

        $this->assertSame("COTIZ-{$year}-00001", Cotizacion::generarNumero());
        $this->assertSame("COTIZ-{$year}-00002", Cotizacion::generarNumero());

        $this->assertDatabaseHas('comercial_cotizacion_secuencias', [
            'anio' => (int) $year,
            'siguiente_numero' => 3,
        ]);
    }

    private function seedParametro(string $clave, string $valor): void
    {
        DB::table('comercial_parametros')->insert([
            'clave' => $clave,
            'nombre' => $clave,
            'valor' => $valor,
            'tipo' => 'decimal',
            'editable' => true,
            'categoria' => 'TEST',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedParametrosReferencia(): void
    {
        foreach ([
            'GRATIFICACION_TOPE' => '209396',
            'IMPOSICIONES_PORCENTAJE' => '19',
            'IMPUESTO_UNICO_FACTOR' => '4',
            'IMPUESTO_UNICO_REBAJA' => '36338.76',
            'REFPREV_EST' => '1',
            'SIS_EST' => '1.49',
            'MUTUAL_EST' => '1.27',
            'CESANTIA_EST' => '3',
            'VACACIONES_DIAS_EST' => '21',
            'GASTOS_ADMIN_EST' => '3',
            'MARGEN_EST' => '10',
            'HORAS_MENSUALES_EST' => '180',
            'HORAS_HHEE_EST' => '176',
            'REFPREV_SUB' => '1',
            'SIS_SUB' => '1.78',
            'MUTUAL_SUB' => '2.5',
            'CESANTIA_SUB' => '3',
            'VACACIONES_FACTOR_SUB' => '1.75',
            'INDEMNIZACION_MESES_SUB' => '12',
            'GASTOS_ADMIN_SUB' => '3',
            'MARGEN_SUB' => '14',
            'HORAS_MENSUALES_SUB' => '180',
            'JORNADA_SEMANAL_SUB' => '44',
        ] as $clave => $valor) {
            $this->seedParametro($clave, $valor);
        }
    }
}
