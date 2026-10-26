<?php

namespace App\Modules\Comercial\Services;

use App\Modules\Comercial\Models\TarifaReferencia;
use Illuminate\Support\Str;

/** Equivalencias explícitas; nunca agrupar cargos mediante similitud aproximada. */
class CargoComercialService
{
    private const ALIASES = [
        'SUPERVISOR DE OPERACIONES' => ['SUPERVISOR OPERACIONES', 'SUPERVISOR OPERACCIONES', 'SUPERVISOR DE OPERACCIONES', 'SUPERVISOR(A) DE OPERACIONES', 'SUPERVISOR(A) OPERACIONES'],
        'SUPERVISOR DE PROVEEDORES' => ['SUPERVISOR PROVEEDORES'],
        'SUPERVISOR DE BODEGA' => ['SUPERVISOR(A) DE BODEGA'],
        'SUPERVISOR DE DIRECCIÓN TÉCNICA' => ['SUPERVISOR(A) DE DIRECCION TECNICA', 'SUPOERVISOR DE DIRECCIÓN TÉCNICA'],
        'AUXILIAR DE BODEGA' => ['AUXULIAR DE BODEGA'],
        'AUXILIAR DE BODEGA SUB' => ['AUXULIAR DE BODEGA SUB'],
        'OPERADOR DE GRÚA' => ['OPERDOR DE GRÚA'],
        'TÉCNICO MECÁNICO' => ['TÉNICO MECÁNICO'],
        'OPERARIO HAULER' => ['OPERARIO HAULLER'],
        'ADMINISTRATIVO DE RECEPCIÓN' => ['ADMINISTRATIVO RECEPCIÓN'],
        'PREVENCIONISTA DE RIESGOS' => ['PREVENCIONISTA DE RIESGO'],
        'OPERARIO DE ESTIBA Y DESESTIBA DE CONTENEDORES' => ['OPERARIO DE ESTIBA Y DESESTIBA DE CONTENENDORES'],
        'OPERARIO DE ESTIBA Y DESESTIBA DE CONTENEDORES EST' => ['OPERARIO DE ESTIBA Y DESESTIBA DE CONTENENDORES EST'],
        'OPERARIO DE ESTIBA Y DESESTIBA DE CONTENEDORES SUB' => ['OPERARIO DE ESTIBA Y DESESTIBA DE CONTENENDORES SUB'],
    ];

    private static function clave(string $cargo): string
    {
        return Str::upper(Str::ascii(trim(preg_replace('/\s+/u', ' ', $cargo))));
    }

    public static function normalizar(?string $cargo): string
    {
        static $indice = null;
        if ($indice === null) {
            $indice = [];
            foreach (self::ALIASES as $canonico => $aliases) {
                foreach ([$canonico, ...$aliases] as $alias) $indice[self::clave($alias)] = $canonico;
            }
        }
        $clave = self::clave($cargo ?? '');
        return $indice[$clave] ?? trim(preg_replace('/\s+/u', ' ', $cargo ?? ''));
    }

    public static function variantes(string $cargo): array
    {
        $canonico = self::normalizar($cargo);
        // Leer las grafías realmente almacenadas: también resuelve espacios y acentos de los alias conocidos.
        return TarifaReferencia::query()->distinct()->pluck('cargo')
            ->filter(fn ($original) => self::normalizar($original) === $canonico)
            ->push($canonico)->push($cargo)->unique()->values()->all();
    }
}
