<?php

namespace App\Modules\Comercial\Services;

use Illuminate\Support\Str;

class TarifaPresentacionService
{
    public static function importe(mixed $valor, int $decimales = 6): string
    {
        if ($valor === null || $valor === '') return 'Por confirmar';
        if (! is_numeric($valor)) return (string) $valor;
        return rtrim(rtrim(number_format((float) $valor, $decimales, ',', '.'), '0'), ',');
    }

    public function grupos(array $conceptos): array
    {
        $grupos = array_fill_keys(['Remuneraciones', 'Asignaciones y beneficios', 'Costos del empleador', 'Provisiones', 'Gastos del servicio', 'Costo y precio de venta', 'Horas y otras unidades', 'Otros conceptos'], []);
        foreach ($conceptos as $concepto) {
            $nombre = trim((string) ($concepto['concepto'] ?? ''));
            if ($nombre === '') continue;
            $n = Str::lower(Str::ascii($nombre));
            $grupo = match (true) {
                (bool) preg_match('/hora|hhee|jornada|tarifa diaria/', $n) => 'Horas y otras unidades',
                (bool) preg_match('/precio|facturar|margen|costo (bruto|final|total)|subtotal/', $n) => 'Costo y precio de venta',
                (bool) preg_match('/vacacion|indemniza|provision/', $n) => 'Provisiones',
                (bool) preg_match('/moviliza|colacion|no imponible|beneficio|aguinaldo/', $n) => 'Asignaciones y beneficios',
                (bool) preg_match('/\bsis\b|mutual|cesantia|refprev|seguro social|aporte/', $n) => 'Costos del empleador',
                (bool) preg_match('/uniforme|casino|gasto|seguro accidente|administracion/', $n) => 'Gastos del servicio',
                (bool) preg_match('/sueldo|bono|gratifica|imponible|haberes|liquido|imposicion|tributable|impuesto/', $n) => 'Remuneraciones',
                default => 'Otros conceptos',
            };
            // Only organize stored values. Never sum subtotals or infer a currency/unit.
            $grupos[$grupo][] = ['nombre' => $nombre, 'valor' => self::importe($concepto['valor'] ?? null), 'total' => (bool) preg_match('/^total|precio.*venta|facturar|costo (bruto|final|total)/', $n)];
        }
        return array_filter($grupos);
    }
}
