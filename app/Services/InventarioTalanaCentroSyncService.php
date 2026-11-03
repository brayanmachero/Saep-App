<?php

namespace App\Services;

use App\Models\InventarioCentroCosto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventarioTalanaCentroSyncService
{
    public function __construct(private readonly TalanaService $talana) {}

    public function synchronize(bool $dryRun = false): array
    {
        $lock = Cache::lock('inventory-centers-talana-sync', 900);
        if (! $lock->get()) {
            throw new \RuntimeException('Ya hay una sincronización de centros Talana en curso.');
        }
        try {
            $source = [];
            $conflicts = [];
            foreach (config('services.talana.inventory_companies', ['SUB' => 1039, 'EST' => 1081]) as $mode => $company) {
                $rows = $this->talana->centrosCosto((int) $company);
                if (! $rows) {
                    throw new \RuntimeException("Talana no devolvió centros para {$mode}; no se modifica el maestro.");
                }
                foreach ($rows as $row) {
                    $code = strtoupper(trim((string) ($row['codigo'] ?? '')));
                    $name = trim((string) ($row['nombre'] ?? ''));
                    if ((int) ($row['empresa'] ?? 0) !== (int) $company || $code === '' || $name === '') {
                        throw new \RuntimeException('Respuesta Talana inválida: empresa, código o nombre.');
                    }
                    $key = $code.'-'.$mode;
                    if (isset($source[$key]) || isset($conflicts[$key])) {
                        unset($source[$key]);
                        $conflicts[$key] = true;
                        continue;
                    }
                    $source[$key] = ['codigo_origen' => $key, 'nombre' => $name, 'nombre_normalizado' => Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString(), 'tipo' => $mode, 'vigente' => (bool) ($row['vigente'] ?? false)];
                }
            }
            return DB::transaction(function () use ($source, $dryRun, $conflicts) {
                $result = ['created' => [], 'updated' => [], 'conflicts' => array_keys($conflicts), 'unchanged' => 0, 'dryRun' => $dryRun];
                foreach ($source as $code => $row) {
                    $matches = InventarioCentroCosto::where('codigo_origen', $code)->lockForUpdate()->get();
                    $center = $matches->first();
                    $sameName = InventarioCentroCosto::where('nombre_normalizado', $row['nombre_normalizado'])->first();
                    if ($matches->count() > 1 || ($sameName && $sameName->id !== $center?->id)) {
                        $result['conflicts'][] = $code;
                        continue;
                    }
                    // No importar centros históricos inactivos ni desactivar centros operativos.
                    if (! $center && ! $row['vigente']) {
                        continue;
                    }
                    $new = ! $center;
                    $center ??= new InventarioCentroCosto(['activo' => true]);
                    // Lista explícita: nunca sobrescribir información gestionada por Bodega.
                    $center->fill(collect($row)->only(['codigo_origen', 'nombre', 'nombre_normalizado', 'tipo'])->all());
                    if (! $new && ! $center->isDirty()) {
                        $result['unchanged']++;
                        continue;
                    }
                    $result[$new ? 'created' : 'updated'][] = $code;
                    if (! $dryRun) {
                        $center->save();
                    }
                }
                return $result;
            });
        } finally {
            $lock->release();
        }
    }
}
