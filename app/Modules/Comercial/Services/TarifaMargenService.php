<?php

namespace App\Modules\Comercial\Services;

use App\Modules\Comercial\Models\{Cotizacion, TarifaReferencia};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TarifaMargenService
{
    public function tarifa(TarifaReferencia $tarifa): array
    {
        if ($tarifa->vinculo_tipo === 'cotizador' && $tarifa->cotizacion) {
            return $this->cotizacion($tarifa->cotizacion);
        }
        $conceptos = collect($tarifa->conceptos ?? []);
        $buscar = fn ($nombres) => $conceptos->filter(fn ($c) => in_array(Str::lower(trim(Str::ascii($c['concepto'] ?? ''))), $nombres, true));
        $costos = $buscar(['costo total del servicio', 'costo bruto normal', 'costo bruto', 'costo total']);
        $margenes = $buscar(['margen operacional', 'margen operacional normal']);
        if (!$tarifa->unidad || !$tarifa->moneda || $costos->count() !== 1 || $margenes->count() > 1) {
            return $this->sinDatos('Falta un costo total único, la unidad o la moneda. Registra una nueva versión con costo respaldado; no se suman conceptos ni se deduce el costo desde el margen.');
        }
        return $this->calcular($costos->first()['valor'] ?? null, $tarifa->precio,
            $margenes->first()['valor'] ?? null, null, 'Costo total informado en el desglose', $tarifa->moneda, $tarifa->unidad);
    }

    public function cotizacion(Cotizacion $cotizacion): array
    {
        if (data_get($cotizacion->datos_calculo, 'origen')) {
            $tarifa = $cotizacion->tarifaReferencia;
            return $tarifa && $tarifa->vinculo_tipo === 'historico' ? $this->tarifa($tarifa)
                : $this->sinDatos('El histórico no tiene un costo respaldado en una tarifa conciliada.');
        }
        if (!in_array(data_get($cotizacion->datos_calculo, 'modalidad'), ['EST', 'SUB'])) {
            return $this->sinDatos('No se conoce la base de cálculo de esta cotización.');
        }
        return $this->calcular($cotizacion->subtotal, $cotizacion->precio_venta, $cotizacion->margen,
            data_get($cotizacion->datos_calculo, 'margen_porcentaje'), 'Costo y margen guardados por el cotizador', 'CLP', 'mes');
    }

    public function exigir(array $revision): void
    {
        if (!$revision['verificable'] || !$revision['consistente']) {
            throw ValidationException::withMessages(['margen' => $revision['estado'].'. '.$revision['mensaje']]);
        }
    }

    private function sinDatos(string $mensaje): array
    {
        return ['verificable'=>false, 'consistente'=>false, 'estado'=>'Margen no verificable', 'mensaje'=>$mensaje];
    }

    private function calcular($costo, $precio, $guardado, $porcentaje, string $origen, string $moneda, string $unidad): array
    {
        foreach ([$costo, $precio] as $valor) {
            if (!is_numeric($valor) || !is_finite((float)$valor)) return $this->sinDatos('El costo o precio está ausente o no es numérico.');
        }
        if ((float)$costo <= 0 || (float)$precio < 0) return $this->sinDatos('El costo debe ser mayor que cero y el precio no puede ser negativo.');
        $diferencia = (float)$precio - (float)$costo;
        $sobreCosto = 100 * $diferencia / (float)$costo;
        $consistente = ($guardado === null || (is_numeric($guardado) && abs((float)$guardado - $diferencia) <= 0.02))
            && ($porcentaje === null || (is_numeric($porcentaje) && abs((float)$porcentaje - $sobreCosto) <= 0.01));
        return ['verificable'=>true, 'consistente'=>$consistente,
            'estado'=>$consistente ? 'Cálculo consistente' : 'Diferencia en el margen guardado',
            'mensaje'=>$consistente ? 'La comprobación aritmética no sustituye la revisión del costo ni la rentabilidad que Comercial debe aceptar.' : 'El margen guardado no coincide con precio menos costo o con el porcentaje sobre costo. Corrige el origen antes de aprobar.',
            'costo'=>(float)$costo, 'precio'=>(float)$precio, 'diferencia'=>$diferencia,
            'sobre_costo'=>$sobreCosto, 'sobre_venta'=>(float)$precio > 0 ? 100*$diferencia/(float)$precio : null,
            'margen_guardado'=>$guardado, 'porcentaje_guardado'=>$porcentaje, 'origen'=>$origen, 'moneda'=>$moneda, 'unidad'=>$unidad];
    }
}
