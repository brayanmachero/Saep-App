<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Tabla completa de la planilla "COSTO 2024.xlsx" (pestaña COSTO_WM_2024, 03/11/2026)
 * también para el cliente MAERSK, con el pago base (sin 9%). Decisión del 11/11/2026:
 * WM y MAERSK comparten esta tabla.
 *
 * Los borradores que ya usan una tarifa MAERSK toman los montos nuevos (snapshot y
 * monto por participante). Validados y liquidados no se tocan.
 */
return new class extends Migration
{
    private const CLIENTE = 'MAERSK';

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

    /** Tarifas MAERSK que existían antes (para revertir): [codigo, proceso, costo, pago]. */
    private const PREVIAS = [
        ['CNT001', 'CONTENEDOR ESTANDAR', 75000, 36000],
    ];

    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now) {
            foreach (self::TARIFAS as [$codigo, $proceso, $costo, $pago, $obs]) {
                $existente = $this->buscar($codigo, $proceso);
                $valores = [
                    'proceso' => $proceso,
                    'costo_unitario' => $costo,
                    'pago_colaborador' => $pago,
                    'requiere_revision' => false,
                    'activo' => true,
                    'updated_at' => $now,
                ];

                if ($existente) {
                    DB::table('descarga_contenedor_tarifas')->where('id', $existente->id)
                        ->update($valores + ['observaciones' => $obs ?? $existente->observaciones]);
                } else {
                    DB::table('descarga_contenedor_tarifas')->insert($valores + [
                        'cliente' => self::CLIENTE,
                        'centro_costo_id' => null,
                        'codigo' => $codigo,
                        'observaciones' => $obs,
                        'created_at' => $now,
                    ]);
                }
            }

            $this->refrescarBorradores($now);
        });
    }

    public function down(): void
    {
        $now = now();

        DB::transaction(function () use ($now) {
            $previas = collect(self::PREVIAS)->map(fn ($p) => $p[0] . '|' . $p[1]);

            foreach (self::PREVIAS as [$codigo, $proceso, $costo, $pago]) {
                DB::table('descarga_contenedor_tarifas')
                    ->where('cliente', self::CLIENTE)->whereNull('centro_costo_id')
                    ->where('codigo', $codigo)->where('proceso', $proceso)
                    ->update(['costo_unitario' => $costo, 'pago_colaborador' => $pago, 'updated_at' => $now]);
            }

            // Las demás se desactivan (pueden estar referenciadas por descargas).
            foreach (self::TARIFAS as [$codigo, $proceso]) {
                if ($previas->contains($codigo . '|' . $proceso)) {
                    continue;
                }
                DB::table('descarga_contenedor_tarifas')
                    ->where('cliente', self::CLIENTE)->whereNull('centro_costo_id')
                    ->where('codigo', $codigo)->where('proceso', $proceso)
                    ->update(['activo' => false, 'updated_at' => $now]);
            }

            $this->refrescarBorradores($now);
        });
    }

    /** Borradores con tarifa MAERSK: snapshot y montos por participante con la tarifa vigente. */
    private function refrescarBorradores($now): void
    {
        $tarifas = DB::table('descarga_contenedor_tarifas')->where('cliente', self::CLIENTE)->get()->keyBy('id');
        if ($tarifas->isEmpty()) {
            return;
        }

        $descargas = DB::table('descarga_contenedores')
            ->where('estado', 'borrador')
            ->whereNull('deleted_at')
            ->whereIn('tarifa_id', $tarifas->keys())
            ->get();

        foreach ($descargas as $descarga) {
            $tarifa = $tarifas[$descarga->tarifa_id];

            DB::table('descarga_contenedores')->where('id', $descarga->id)->update([
                'tarifa_cliente_snapshot' => $tarifa->cliente,
                'tarifa_proceso_snapshot' => $tarifa->proceso,
                'costo_unitario_snapshot' => $tarifa->costo_unitario,
                'pago_colaborador_snapshot' => $tarifa->pago_colaborador,
                'updated_at' => $now,
            ]);

            if ($descarga->requiere_revision_tarifa || $tarifa->pago_colaborador === null) {
                continue;
            }

            DB::table('descarga_contenedor_participantes')
                ->where('descarga_contenedor_id', $descarga->id)
                ->get()
                ->each(fn ($p) => DB::table('descarga_contenedor_participantes')->where('id', $p->id)->update([
                    'monto_calculado' => round((float) $tarifa->pago_colaborador * (float) $p->porcentaje_participacion / 100, 2),
                    'updated_at' => $now,
                ]));
        }
    }

    private function buscar(string $codigo, string $proceso): ?object
    {
        $candidatas = DB::table('descarga_contenedor_tarifas')
            ->where('cliente', self::CLIENTE)
            ->whereNull('centro_costo_id')
            ->where('codigo', $codigo)
            ->get();

        $norm = fn ($v) => preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii((string) $v)));

        // Con un solo registro por código se actualiza ese, salvo CNT002 que tiene dos procesos.
        if ($candidatas->count() === 1 && $codigo !== 'CNT002') {
            return $candidatas->first();
        }

        return $candidatas->first(fn ($t) => $norm($t->proceso) === $norm($proceso));
    }
};
