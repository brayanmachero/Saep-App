<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Models\DescargaContenedor;
use App\Models\DescargaContenedorTarifa;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TarifasWmCosto2024MigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration()
    {
        return require database_path('migrations/2026_11_11_000001_update_tarifas_wm_costo_2024.php');
    }

    private function wm(string $codigo, ?string $proceso = null): ?DescargaContenedorTarifa
    {
        return DescargaContenedorTarifa::where('cliente', 'WM')->whereNull('centro_costo_id')
            ->where('codigo', $codigo)
            ->when($proceso, fn ($q) => $q->where('proceso', $proceso))
            ->first();
    }

    public function test_wm_tariffs_match_costo_2024_base_pay(): void
    {
        $this->assertSame('33000.00', $this->wm('CNT001')->pago_colaborador);
        $this->assertSame('75000.00', $this->wm('CNT001')->costo_unitario);
        $this->assertSame('66000.00', $this->wm('CNT002', 'CONTENEDOR DOBLE')->pago_colaborador);
        $this->assertSame('66000.00', $this->wm('CNT002', 'FERIADO/DOMINGO CONT')->pago_colaborador);
        $this->assertSame('68750.00', $this->wm('CNT003')->pago_colaborador);
        $this->assertSame('CONTENEDOR >5.000 CAJAS', $this->wm('CNT003')->proceso);
        $this->assertStringContainsString('4.000', $this->wm('CNT003')->observaciones);
        $this->assertSame('9680.00', $this->wm('BRTR001')->pago_colaborador);
        $this->assertSame('54000.00', $this->wm('CNTMC001')->pago_colaborador);

        foreach (['ACE002' => '66000.00', 'ACE003' => '66000.00', 'PAS002' => '66000.00', 'AHL001' => '320.83'] as $codigo => $pago) {
            $this->assertSame($pago, $this->wm($codigo)?->pago_colaborador, $codigo);
        }
        $this->assertSame('225000.00', $this->wm('ACE003')->costo_unitario);

        // Sin duplicados: un registro por código, salvo CNT002 (dos procesos).
        $this->assertSame(2, DescargaContenedorTarifa::where('cliente', 'WM')->where('codigo', 'CNT002')->count());
        $this->assertSame(1, DescargaContenedorTarifa::where('cliente', 'WM')->where('codigo', 'CNT003')->count());

        // SMU y CNT100 no se tocan.
        $this->assertSame('25500.00', DescargaContenedorTarifa::where('cliente', 'SMU')->where('codigo', 'CNT001')->value('pago_colaborador'));
        $this->assertNotNull($this->wm('CNT100'));
    }

    public function test_rollback_restores_previous_values_and_rerun_is_idempotent(): void
    {
        $total = DescargaContenedorTarifa::count();

        $this->migration()->down();
        $this->assertSame('36000.00', $this->wm('CNT001')->pago_colaborador);
        $this->assertSame('36000.00', $this->wm('CNT002', 'CONTENEDOR DOBLE')->pago_colaborador);
        $this->assertSame('CONTENEDOR >4.000 CAJAS', $this->wm('CNT003')->proceso);
        $this->assertSame('75000.00', $this->wm('CNT003')->pago_colaborador);
        $this->assertFalse((bool) $this->wm('AHL001')->activo);

        $this->migration()->up();
        $this->migration()->up();
        $this->assertSame($total, DescargaContenedorTarifa::count());
        $this->assertSame('33000.00', $this->wm('CNT001')->pago_colaborador);
        $this->assertTrue((bool) $this->wm('AHL001')->activo);
    }

    public function test_maersk_has_the_same_table_and_draft_amounts_are_refreshed(): void
    {
        $maersk = DescargaContenedorTarifa::where('cliente', 'MAERSK')->where('activo', true)->get();
        $this->assertCount(22, $maersk);
        $this->assertSame('33000.00', $maersk->firstWhere('codigo', 'CNT001')->pago_colaborador);
        $this->assertSame('320.83', $maersk->firstWhere('codigo', 'AHL001')->pago_colaborador);

        // Borrador creado con la tarifa MAERSK previa ($36.000) y dos participantes al 50%.
        $tarifa = $maersk->firstWhere('codigo', 'CNT001');
        $tarifa->update(['pago_colaborador' => 36000]);
        $base = ['origen' => 'pegado', 'fecha' => '2026-11-02', 'fact_codigo' => 'CNT001', 'tarifa_id' => $tarifa->id,
            'pago_colaborador_snapshot' => 36000, 'costo_unitario_snapshot' => 75000, 'tarifa_cliente_snapshot' => 'MAERSK',
            'requiere_revision_tarifa' => false, 'created_at' => now(), 'updated_at' => now()];
        $id = DB::table('descarga_contenedores')->insertGetId($base + ['estado' => 'borrador', 'contenedor' => 'TXGU5578197']);
        $validado = DB::table('descarga_contenedores')->insertGetId($base + ['estado' => 'validado', 'contenedor' => 'MSBU5332438']);
        foreach ([1, 2] as $n) {
            DB::table('descarga_contenedor_participantes')->insert([
                'descarga_contenedor_id' => $id, 'nombre_snapshot' => "Trabajador {$n}", 'rol_en_descarga' => 'descargador',
                'porcentaje_participacion' => 50, 'monto_calculado' => 18000, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        (require database_path('migrations/2026_11_11_000002_create_tarifas_maersk_costo_2024.php'))->up();

        $this->assertSame('33000.00', DescargaContenedor::find($id)->pago_colaborador_snapshot);
        $this->assertSame([16500.0, 16500.0], DB::table('descarga_contenedor_participantes')
            ->where('descarga_contenedor_id', $id)->pluck('monto_calculado')->map(fn ($v) => (float) $v)->all());
        $this->assertSame('36000.00', DescargaContenedor::find($validado)->pago_colaborador_snapshot, 'validados no se tocan');
        $this->assertSame(22, DescargaContenedorTarifa::where('cliente', 'MAERSK')->count(), 'sin duplicados al repetir');
    }

    public function test_bulk_row_from_maersk_center_takes_maersk_tariff(): void
    {
        $rol = Rol::firstOrCreate(['codigo' => 'SUPER_ADMIN'], ['nombre' => 'Super Admin']);
        $user = User::factory()->create(['rol_id' => $rol->id]);
        $this->withoutMiddleware([ForcePasswordChange::class, VerificarConsentimientoDatos::class]);

        $this->actingAs($user)->post(route('descarga-contenedores.store-bulk'), [
            'registros_json' => json_encode([
                ['bodega' => 'MAERSK LA VARA', 'fecha' => '02/11/2026', 'contenedor' => 'MAEU1234567', 'fact_codigo' => 'CNT001'],
                ['operacion' => 'WALMART', 'fecha' => '02/11/2026', 'contenedor' => 'WMTU1234567', 'fact_codigo' => 'CNT001'],
            ]),
        ])->assertRedirect();

        $this->assertSame('MAERSK', DescargaContenedor::where('contenedor', 'MAEU1234567')->value('tarifa_cliente_snapshot'));
        $this->assertSame('WM', DescargaContenedor::where('contenedor', 'WMTU1234567')->value('tarifa_cliente_snapshot'));
    }
}
