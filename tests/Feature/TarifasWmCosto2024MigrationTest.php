<?php

namespace Tests\Feature;

use App\Models\DescargaContenedorTarifa;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
