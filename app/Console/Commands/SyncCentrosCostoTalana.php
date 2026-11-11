<?php

namespace App\Console\Commands;

use App\Services\CentroCostoTalanaSyncService;
use Illuminate\Console\Command;

class SyncCentrosCostoTalana extends Command
{
    protected $signature = 'centros:sync-talana
                            {--aplicar : Guarda los cambios (sin esta opción solo simula)}
                            {--crear-faltantes : Crea los centros vigentes de Talana que no existen en SAEP}
                            {--actualizar-nombres : Reemplaza el nombre local por el oficial de Talana (afecta a todos los módulos)}
                            {--desactivar-no-vigentes : Desactiva los centros que Talana marca como no vigentes}
                            {--detalle : Muestra también los vínculos y los centros locales sin equivalente}';

    protected $description = 'Vincula centros_costo con los centros oficiales de Talana (SUB y EST). Por defecto solo simula.';

    public function handle(CentroCostoTalanaSyncService $service): int
    {
        $dryRun = ! $this->option('aplicar');

        try {
            $r = $service->synchronize(
                $dryRun,
                (bool) $this->option('crear-faltantes'),
                (bool) $this->option('actualizar-nombres'),
                (bool) $this->option('desactivar-no-vigentes'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info($dryRun ? 'SIMULACIÓN: no se guardó ningún cambio.' : 'Cambios aplicados.');
        $this->newLine();

        $porMetodo = collect($r['vinculos'])->countBy('metodo');
        $this->table(['Resultado', 'Cantidad'], array_merge(
            [['Centros en Talana (SUB + EST)', $r['talana_total']]],
            $porMetodo->map(fn ($n, $m) => ["Vinculados por {$m}", $n])->values()->all(),
            [
                ['Faltantes en SAEP (vigentes)', collect($r['faltantes'])->where('vigente', true)->count()],
                ['Faltantes en SAEP (no vigentes, se ignoran)', collect($r['faltantes'])->where('vigente', false)->count()],
                ['Conflictos a revisar', count($r['conflictos'])],
                ['Centros locales sin equivalente en Talana', count($r['locales_sin_talana'])],
                ['Vínculos huérfanos (código ya no existe en Talana)', count($r['huerfanos'])],
                ['A crear', count($r['creados'])],
                ['A renombrar', count($r['renombrados'])],
                ['A desactivar', count($r['desactivados'])],
            ],
        ));

        if ($r['conflictos']) {
            $this->warn('Conflictos (no se tocan; requieren decisión manual):');
            $this->table(['Talana', 'Motivo'], array_map(fn ($c) => [$c['talana'], $c['motivo']], $r['conflictos']));
        }

        $nombresDistintos = collect($r['vinculos'])->filter(
            fn ($v) => \App\Models\CentroCosto::normalizarNombre($v['local_nombre']) !== \App\Models\CentroCosto::normalizarNombre($v['talana_nombre'])
        );
        if ($nombresDistintos->isNotEmpty()) {
            $this->line('Vinculados con nombre local distinto al oficial:');
            $this->table(['Talana', 'Nombre oficial', 'ID local', 'Nombre local', 'Método'],
                $nombresDistintos->map(fn ($v) => [$v['talana'], $v['talana_nombre'], $v['local_id'], $v['local_nombre'], $v['metodo']])->values()->all());
        }

        if ($r['faltantes']) {
            $this->line('Faltantes en SAEP:');
            $this->table(['Talana', 'Nombre', 'Vigente'],
                array_map(fn ($f) => [$f['talana'], $f['nombre'], $f['vigente'] ? 'Sí' : 'No'], $r['faltantes']));
        }

        foreach (['creados' => ['Talana', 'Nombre', 'Código'], 'renombrados' => ['ID', 'De', 'A'], 'desactivados' => ['ID', 'Nombre', 'Talana']] as $key => $headers) {
            if ($r[$key]) {
                $this->line(ucfirst($key) . ':');
                $this->table($headers, array_map('array_values', $r[$key]));
            }
        }

        if ($this->option('detalle')) {
            $this->line('Vínculos:');
            $this->table(['Talana', 'Nombre oficial', 'Vigente', 'ID local', 'Nombre local', 'Activo', 'Método'], array_map(fn ($v) => [
                $v['talana'], $v['talana_nombre'], $v['vigente'] ? 'Sí' : 'No', $v['local_id'], $v['local_nombre'], $v['local_activo'] ? 'Sí' : 'No', $v['metodo'],
            ], $r['vinculos']));
            $this->line('Centros locales sin equivalente en Talana:');
            $this->table(['ID', 'Código', 'Nombre', 'Activo'], array_map(fn ($l) => [$l['id'], $l['codigo'], $l['nombre'], $l['activo'] ? 'Sí' : 'No'], $r['locales_sin_talana']));
        }

        return self::SUCCESS;
    }
}
