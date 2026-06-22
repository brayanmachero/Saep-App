<?php

namespace Tests\Unit;

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
}
