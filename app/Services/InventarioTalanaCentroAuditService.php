<?php

namespace App\Services;

use App\Models\InventarioCentroCosto;
use Illuminate\Support\Str;

class InventarioTalanaCentroAuditService
{
    public function __construct(private readonly TalanaService $talana) {}

    public function audit(): array
    {
        $index = [];
        foreach (config('services.talana.inventory_companies', ['SUB' => 1039, 'EST' => 1081]) as $mode => $company) {
            foreach ($this->talana->centrosCosto((int) $company) as $row) {
                if ((int) ($row['empresa'] ?? 0) !== (int) $company) {
                    throw new \RuntimeException('Talana devolvió centros de otra empresa.');
                }
                $index[strtoupper(trim((string) $row['codigo'])).'-'.$mode][] = $row;
            }
        }
        $result = ['matched' => 0, 'missing' => [], 'withoutCode' => [], 'statusDifferences' => [], 'nameDifferences' => [], 'readOnly' => true];
        foreach (InventarioCentroCosto::all() as $center) {
            if (! $center->codigo_origen) {
                $result['withoutCode'][] = $center->nombre;

                continue;
            }
            $matches = $index[$center->codigo_origen] ?? [];
            if (count($matches) !== 1 || ! str_ends_with($center->codigo_origen, '-'.$center->tipo)) {
                $result['missing'][] = $center->nombre;

                continue;
            }
            $row = $matches[0];
            $result['matched']++;
            if ((bool) $row['vigente'] !== $center->activo) {
                $result['statusDifferences'][] = ['centro' => $center->nombre, 'app' => $center->activo, 'talana' => $row['vigente']];
            }
            $norm = fn ($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($s))));
            if ($norm($row['nombre']) !== $norm($center->nombre)) {
                $result['nameDifferences'][] = ['centro' => $center->nombre, 'talana' => $row['nombre'], 'codigo' => $center->codigo_origen];
            }
        }

        return $result;
    }
}
