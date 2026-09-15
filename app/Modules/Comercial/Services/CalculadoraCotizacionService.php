<?php

namespace App\Modules\Comercial\Services;

use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Models\CotizacionDetalle;
use App\Modules\Comercial\Models\CotizacionUniforme;
use App\Modules\Comercial\Models\Modalidad;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Servicio Orquestador de Cotizaciones
 *
 * Maneja el flujo general de cálculo de cotizaciones
 * Delega a CalculadoraESTService o CalculadoraSUBService según modalidad
 */
class CalculadoraCotizacionService
{
    private CalculadoraESTService $calculadoraEST;
    private CalculadoraSUBService $calculadoraSUB;

    public function __construct(
        CalculadoraESTService $calculadoraEST,
        CalculadoraSUBService $calculadoraSUB
    ) {
        $this->calculadoraEST = $calculadoraEST;
        $this->calculadoraSUB = $calculadoraSUB;
    }

    /**
     * Calcular cotización completa
     *
     * @param array $datos {
     *     cliente_id: int,
     *     centro_costo_id: int,
     *     modalidad_id: int,
     *     usuario_id: int,
     *     remuneraciones: [
     *         { concepto: string, valor: float }
     *     ],
     *     uniformes?: [ { descripcion, cantidad, precio_unitario } ]
     * }
     */
    public function calcular(array $datos): array
    {
        try {
            // Validar que existan los modelos requeridos
            $modalidad = Modalidad::findOrFail($datos['modalidad_id'] ?? null);

            // Delegar al calculador específico según modalidad
            if ($modalidad->codigo === 'EST') {
                return $this->calculadoraEST->calcular($datos);
            } elseif ($modalidad->codigo === 'SUB') {
                return $this->calculadoraSUB->calcular($datos);
            }

            throw new \InvalidArgumentException("Modalidad no reconocida: {$modalidad->codigo}");

        } catch (ModelNotFoundException $e) {
            throw new \InvalidArgumentException("Datos inválidos: {$e->getMessage()}");
        }
    }

    /**
     * Guardar cotización con todos sus detalles
     */
    public function guardar(Cotizacion $cotizacion, array $datosCalculo): Cotizacion
    {
        // Guardar datos de cálculo
        $cotizacion->datos_calculo = $datosCalculo;
        $cotizacion->detalles_json = $datosCalculo['detalles'] ?? [];
        $cotizacion->numero = Cotizacion::generarNumero();
        $cotizacion->save();

        $this->sincronizarDetalles($cotizacion, $datosCalculo);

        // Registrar auditoría
        $cotizacion->auditorias()->create([
            'usuario_id' => auth()->id(),
            'accion' => 'creada',
            'descripcion' => "Cotización {$cotizacion->numero} creada con precio venta $".number_format((float) $cotizacion->precio_venta, 0, ',', '.'),
            'cambios' => [
                'numero' => $cotizacion->numero,
                'total_remuneraciones' => $cotizacion->total_remuneraciones,
                'total_cotizaciones' => $cotizacion->total_cotizaciones,
                'total_provisiones' => $cotizacion->total_provisiones,
                'total_gastos' => $cotizacion->total_gastos,
                'subtotal' => $cotizacion->subtotal,
                'margen' => $cotizacion->margen,
                'precio_venta' => $cotizacion->precio_venta,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);

        return $cotizacion->fresh(['detalles', 'uniformes']);
    }

    public function actualizar(Cotizacion $cotizacion, array $datosCalculo, array $atributos = []): Cotizacion
    {
        $cotizacion->fill(array_merge($atributos, [
            'total_remuneraciones' => $datosCalculo['total_remuneraciones'],
            'total_cotizaciones' => $datosCalculo['total_cotizaciones'],
            'total_provisiones' => $datosCalculo['total_provisiones'],
            'total_gastos' => $datosCalculo['total_gastos'],
            'subtotal' => $datosCalculo['subtotal'],
            'margen' => $datosCalculo['margen'],
            'precio_venta' => $datosCalculo['precio_venta'],
            'datos_calculo' => $datosCalculo,
            'detalles_json' => $datosCalculo['detalles'] ?? [],
        ]));
        $cotizacion->save();

        $cotizacion->detalles()->delete();
        $cotizacion->uniformes()->delete();
        $this->sincronizarDetalles($cotizacion, $datosCalculo);

        $cotizacion->auditorias()->create([
            'usuario_id' => auth()->id(),
            'accion' => 'actualizada',
            'descripcion' => 'Cotización recalculada',
            'cambios' => ['resumen' => $datosCalculo['resumen_excel'] ?? []],
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);

        return $cotizacion->fresh(['detalles', 'uniformes']);
    }

    /**
     * Recalcular una cotización existente
     */
    public function recalcular(Cotizacion $cotizacion): array
    {
        // Reconstruir datos de entrada
        $datos = [
            'cliente_id' => $cotizacion->cliente_id,
            'centro_costo_id' => $cotizacion->centro_costo_id,
            'modalidad_id' => $cotizacion->modalidad_id,
            'usuario_id' => auth()->id(),
            'remuneraciones' => $cotizacion->detalles()
                ->where('tipo', 'remuneracion')
                ->get(['concepto', 'valor_base as valor'])
                ->toArray(),
            'uniformes' => $cotizacion->uniformes()
                ->get(['descripcion', 'cantidad', 'precio_unitario'])
                ->toArray(),
        ];

        // Calcular nuevamente
        return $this->calcular($datos);
    }

    /**
     * Recalcula una cotización aplicando IPC únicamente al sueldo base.
     *
     * La matriz histórica utilizada por Comercial reajusta también el precio
     * de venta en el mismo factor de IPC. Se conserva esa política para que
     * la versión creada en SAEP sea comparable con la planilla de origen.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $resumenOrigen
     * @param  array<string, mixed>  $horasOrigen
     * @return array<string, mixed>
     */
    public function recalcularPorIpc(
        array $datos,
        float $ipcPorcentaje,
        float $precioVentaOrigen,
        array $resumenOrigen = [],
        array $horasOrigen = [],
    ): array {
        if ($ipcPorcentaje <= 0 || $ipcPorcentaje > 100) {
            throw new \InvalidArgumentException('El IPC debe ser mayor que 0 y no superar 100%.');
        }

        if ($precioVentaOrigen <= 0) {
            throw new \InvalidArgumentException('La cotización de origen no tiene un precio de venta válido para reajustar.');
        }

        $factor = 1 + ($ipcPorcentaje / 100);
        $sueldoBaseReajustado = false;

        foreach ($datos['remuneraciones'] ?? [] as $indice => $remuneracion) {
            if (! $this->esSueldoBase((string) ($remuneracion['concepto'] ?? ''))) {
                continue;
            }

            $datos['remuneraciones'][$indice]['valor'] = round(((float) ($remuneracion['valor'] ?? 0)) * $factor, 2);
            $sueldoBaseReajustado = true;
        }

        if (! $sueldoBaseReajustado) {
            throw new \InvalidArgumentException('No se encontró un sueldo base en la cotización de origen.');
        }

        $calculo = $this->calcular($datos);
        $precioVenta = round($precioVentaOrigen * $factor, 2);
        $margen = round($precioVenta - (float) $calculo['subtotal'], 2);
        $margenPorcentaje = (float) $calculo['subtotal'] > 0
            ? round(($margen / (float) $calculo['subtotal']) * 100, 4)
            : 0.0;

        $calculo['precio_venta'] = $precioVenta;
        $calculo['margen'] = $margen;
        $calculo['margen_porcentaje'] = $margenPorcentaje;

        $resumen = $calculo['resumen_excel'] ?? [];
        $resumen['margen'] = $margen;
        $resumen['precioVenta'] = $precioVenta;

        $precioVentaHheeOrigen = (float) ($resumenOrigen['precioVentaHhee'] ?? 0);
        if ($precioVentaHheeOrigen > 0) {
            $precioVentaHhee = round($precioVentaHheeOrigen * $factor, 2);
            $resumen['precioVentaHhee'] = $precioVentaHhee;
            $resumen['margenHhee'] = round($precioVentaHhee - (float) ($resumen['costoBrutoHhee'] ?? 0), 2);
        }

        $calculo['resumen_excel'] = $resumen;
        $calculo['horas'] = $this->reajustarHoras(
            $calculo['horas'] ?? [],
            $horasOrigen,
            $precioVentaOrigen,
            $precioVenta,
            $precioVentaHheeOrigen,
            (float) ($resumen['precioVentaHhee'] ?? 0),
        );

        foreach ($calculo['detalles'] ?? [] as $indice => $detalle) {
            if (($detalle['tipo'] ?? null) !== 'margen') {
                continue;
            }

            $calculo['detalles'][$indice]['valor_base'] = round((float) $calculo['subtotal'], 2);
            $calculo['detalles'][$indice]['porcentaje'] = $margenPorcentaje;
            $calculo['detalles'][$indice]['valor'] = $margen;
            $calculo['detalles'][$indice]['formula'] = [
                'descripcion' => 'Precio de venta de origen reajustado por IPC; margen resultante sobre el costo recalculado.',
            ];
        }

        return $calculo;
    }

    /** @param array<string, mixed> $horasCalculadas @param array<string, mixed> $horasOrigen */
    private function reajustarHoras(
        array $horasCalculadas,
        array $horasOrigen,
        float $precioVentaOrigen,
        float $precioVenta,
        float $precioVentaHheeOrigen,
        float $precioVentaHhee,
    ): array {
        $factorNormal = $precioVentaOrigen > 0 && isset($horasOrigen['normal'])
            ? (float) $horasOrigen['normal'] / $precioVentaOrigen
            : ((float) ($horasCalculadas['normal'] ?? 0) / max($precioVenta, 1));
        $horasCalculadas['normal'] = round($precioVenta * $factorNormal, 2);

        if ($precioVentaHheeOrigen > 0 && isset($horasOrigen['normal_hhee'])) {
            $factorHhee = (float) $horasOrigen['normal_hhee'] / $precioVentaHheeOrigen;
            $horasCalculadas['normal_hhee'] = round($precioVentaHhee * $factorHhee, 2);
        }

        $horaNormalHhee = (float) ($horasCalculadas['normal_hhee'] ?? 0);
        if ($horaNormalHhee > 0) {
            $horasCalculadas['extra_50'] = round($horaNormalHhee * 1.5, 2);
            $horasCalculadas['extra_100'] = round($horaNormalHhee * 2, 2);
        }

        return $horasCalculadas;
    }

    private function esSueldoBase(string $concepto): bool
    {
        $concepto = mb_strtolower($concepto, 'UTF-8');

        return str_contains($concepto, 'sueldo')
            && (str_contains($concepto, 'base') || trim($concepto) === 'sueldo');
    }

    private function sincronizarDetalles(Cotizacion $cotizacion, array $datosCalculo): void
    {
        foreach ($datosCalculo['detalles'] ?? [] as $detalle) {
            $cotizacion->detalles()->create($detalle);
        }

        foreach ($datosCalculo['uniformes'] ?? [] as $uniforme) {
            if (! empty($uniforme['descripcion']) && (int) ($uniforme['cantidad'] ?? 0) > 0) {
                $cotizacion->uniformes()->create($uniforme);
            }
        }
    }

    /**
     * Generar versión nueva de cotización
     */
    public function versionarCotizacion(Cotizacion $cotizacion, array $cambios): Cotizacion
    {
        // Crear nueva cotización
        $nuevaCotizacion = new Cotizacion($cotizacion->only([
            'cliente_id',
            'centro_costo_id',
            'modalidad_id',
        ]));

        $nuevaCotizacion->usuario_id = auth()->id();
        $nuevaCotizacion->version = $cotizacion->version + 1;
        $nuevaCotizacion->cotizacion_anterior_id = $cotizacion->id;

        // Aplicar cambios
        if (isset($cambios['remuneraciones'])) {
            $datosCalculo = $this->calcular([
                'cliente_id' => $nuevaCotizacion->cliente_id,
                'centro_costo_id' => $nuevaCotizacion->centro_costo_id,
                'modalidad_id' => $nuevaCotizacion->modalidad_id,
                'usuario_id' => auth()->id(),
                'remuneraciones' => $cambios['remuneraciones'],
            ]);

            $this->guardar($nuevaCotizacion, $datosCalculo);
        }

        // Cambiar estado de la anterior a no_vigente
        $cotizacion->estado = 'no_vigente';
        $cotizacion->save();

        // Registrar auditoría
        $nuevaCotizacion->auditorias()->create([
            'usuario_id' => auth()->id(),
            'accion' => 'versionada',
            'descripcion' => "Nueva versión {$nuevaCotizacion->version} creada",
            'cambios' => $cambios,
            'ip_address' => request()->ip(),
        ]);

        return $nuevaCotizacion;
    }
}
