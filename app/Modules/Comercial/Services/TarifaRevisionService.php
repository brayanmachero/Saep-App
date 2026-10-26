<?php
namespace App\Modules\Comercial\Services;

use App\Modules\Comercial\Models\TarifaReferencia;
use Illuminate\Support\Str;

class TarifaRevisionService
{
    public function pendientes(TarifaReferencia $tarifa): array
    {
        $faltantes = [];
        foreach (['cliente'=>'Cliente', 'unidad'=>'Unidad de cobro', 'modalidad'=>'Modalidad', 'categoria'=>'Categoría', 'fecha_referencia'=>'Fecha de referencia', 'vigente_desde'=>'Inicio de vigencia'] as $campo=>$nombre) {
            if (blank($tarifa->$campo) || ($campo === 'cliente' && $tarifa->$campo === 'Por homologar')) $faltantes[] = $nombre;
        }
        if ($tarifa->precio === null) $faltantes[] = 'Precio';
        if (collect($tarifa->conceptos ?? [])->contains(fn ($c) => is_string($c['valor'] ?? null) && str_starts_with($c['valor'], '#'))) $faltantes[] = 'Error en desglose';
        return $faltantes;
    }

    public function diferencia(TarifaReferencia $base, TarifaReferencia $otra): ?array
    {
        $normalizar = fn ($v) => Str::lower(trim((string) $v));
        if ($base->precio === null || $otra->precio === null || blank($base->unidad) || blank($otra->unidad) || blank($base->moneda) || blank($otra->moneda)
            || $normalizar($base->unidad) !== $normalizar($otra->unidad) || $normalizar($base->moneda) !== $normalizar($otra->moneda)) return null;
        $delta = round((float)$otra->precio - (float)$base->precio, 6);
        return ['importe'=>$delta, 'porcentaje'=>(float)$base->precio == 0 ? null : round($delta / (float)$base->precio * 100, 2)];
    }
}
