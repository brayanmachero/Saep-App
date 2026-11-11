<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Tarifas FACT de Walmart según la planilla "COSTO 2024.xlsx", pestaña COSTO_WM_2024
 * (versión del 03/11/2026). Pago al trabajador: columna "PAGO A COLABORADOR" (base,
 * sin 9%), por decisión del 11/11/2026.
 *
 * Solo toca tarifas generales de WM (sin centro). Las descargas validadas o liquidadas
 * conservan su monto congelado; los borradores toman la tarifa vigente.
 */
return new class extends Migration
{
    private const CLIENTE = 'WM';

    /** [codigo, proceso, costo_unitario, pago_colaborador, observaciones] */
    private const TARIFAS = [
        ['ACE001', 'ACEITES', 75000, 33000, null],
        ['ACE002', 'ACEITES', 150000, 66000, null],
        ['ACE003', 'ACEITES', 225000, 66000, null],
        ['BRTR001', 'DESCARGA BH/TR', 22000, 9680, null],
        ['CAJ001', 'CAJA DE CARNE', 97, 33, null],
        ['CAJ002', 'FERIADO/DOMINGO CAJA', 194, 66, null],
        ['CNT000', 'SIN PRODUCCION', 0, 0, null],
        ['CNT001', 'CONTENEDOR ESTANDAR', 75000, 33000, null],
        ['CNT002', 'CONTENEDOR DOBLE', 150000, 66000, null],
        ['CNT002', 'FERIADO/DOMINGO CONT', 150000, 66000, null],
        ['CNT003', 'CONTENEDOR >5.000 CAJAS', 150000, 68750, 'En Peñón y Quilicura el umbral es 4.000 cajas.'],
        ['CNT004', 'CONTENEDOR >10 ITEMS', 150000, 68750, null],
        ['AHL001', 'APOYO HAULER', 500, 320.83, null],
        ['LEC001', 'LECHES', 75000, 33000, null],
        ['PAS001', 'PASTAS', 75000, 33000, null],
        ['PAS002', 'PASTAS', 150000, 66000, null],
        ['REP001', 'REPALETIZADO X PALLET', 1400, 672, null],
        ['CNTMC001', 'CONTENEDORES 50 MC', 112500, 54000, null],
        ['DESV001', 'DESVALIJADO X CAJA', 220, 105.6, null],
        ['ETI001', 'ETIQUETADO X CAJA', 160, 76.8, null],
        ['HUE001', 'HUEVOS 50 MC', 112500, 54000, null],
        ['HUE002', 'HUEVOS 40 MC', 75000, 36000, null],
    ];

    /** Valores vigentes antes de esta migración (producción, 11/11/2026), para revertir. */
    private const PREVIAS = [
        ['ACE001', 'ACEITES', 75000, 36000],
        ['BRTR001', 'DESCARGA BH/TR', 22000, 10560],
        ['CAJ001', 'CAJA DE CARNE', 97, 36],
        ['CAJ002', 'FERIADO/DOMINGO CAJA', 194, 72],
        ['CNT000', 'SIN PRODUCCION', 0, 0],
        ['CNT001', 'CONTENEDOR ESTANDAR', 75000, 36000],
        ['CNT002', 'CONTENEDOR DOBLE', 150000, 36000],
        ['CNT002', 'FERIADO/DOMINGO CONT', 150000, 72000],
        ['CNT003', 'CONTENEDOR >4.000 CAJAS', 150000, 75000],
        ['CNT004', 'CONTENEDOR >10 ITEMS', 150000, 75000],
        ['LEC001', 'LECHES', 75000, 36000],
        ['PAS001', 'PASTAS', 75000, 36000],
        ['REP001', 'REPALETIZADO X PALLET', 1400, 672],
        ['CNTMC001', 'CONTENEDORES 50 MC', 112500, 54000],
        ['DESV001', 'DESVALIJADO X CAJA', 220, 105.6],
        ['ETI001', 'ETIQUETADO X CAJA', 160, 76.8],
        ['HUE001', 'HUEVOS 50 MC', 112500, 54000],
        ['HUE002', 'HUEVOS 40 MC', 75000, 36000],
    ];

    private const NUEVAS = ['ACE002', 'ACE003', 'AHL001', 'PAS002'];

    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now) {
            foreach (self::TARIFAS as [$codigo, $proceso, $costo, $pago, $obs]) {
                // CNT003 cambia de nombre (>4.000 a >5.000): se busca también por el nombre previo.
                $existente = $this->buscar($codigo, $proceso)
                    ?? ($codigo === 'CNT003' ? $this->buscar($codigo, 'CONTENEDOR >4.000 CAJAS') : null);

                if ($existente) {
                    DB::table('descarga_contenedor_tarifas')->where('id', $existente->id)->update([
                        'proceso' => $proceso,
                        'costo_unitario' => $costo,
                        'pago_colaborador' => $pago,
                        'observaciones' => $obs ?? $existente->observaciones,
                        'requiere_revision' => false,
                        'activo' => true,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('descarga_contenedor_tarifas')->insert([
                        'cliente' => self::CLIENTE,
                        'centro_costo_id' => null,
                        'codigo' => $codigo,
                        'proceso' => $proceso,
                        'costo_unitario' => $costo,
                        'pago_colaborador' => $pago,
                        'requiere_revision' => false,
                        'observaciones' => $obs,
                        'activo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        $now = now();

        DB::transaction(function () use ($now) {
            foreach (self::PREVIAS as [$codigo, $proceso, $costo, $pago]) {
                $actual = $this->buscar($codigo, $proceso)
                    ?? ($codigo === 'CNT003' ? $this->buscar($codigo, 'CONTENEDOR >5.000 CAJAS') : null);
                if ($actual) {
                    DB::table('descarga_contenedor_tarifas')->where('id', $actual->id)->update([
                        'proceso' => $proceso,
                        'costo_unitario' => $costo,
                        'pago_colaborador' => $pago,
                        'observaciones' => $codigo === 'CNT003' ? null : $actual->observaciones,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Las nuevas se desactivan (pueden estar referenciadas por descargas).
            DB::table('descarga_contenedor_tarifas')
                ->where('cliente', self::CLIENTE)->whereNull('centro_costo_id')
                ->whereIn('codigo', self::NUEVAS)
                ->update(['activo' => false, 'updated_at' => $now]);
        });
    }

    private function buscar(string $codigo, string $proceso): ?object
    {
        $candidatas = DB::table('descarga_contenedor_tarifas')
            ->where('cliente', self::CLIENTE)
            ->whereNull('centro_costo_id')
            ->where('codigo', $codigo)
            ->get();

        if ($candidatas->count() <= 1) {
            return $candidatas->first();
        }

        // Código repetido (CNT002): se distingue por el proceso.
        $norm = fn ($v) => preg_replace('/[^A-Z0-9]/', '', strtoupper(\Illuminate\Support\Str::ascii((string) $v)));

        return $candidatas->first(fn ($t) => $norm($t->proceso) === $norm($proceso));
    }
};
