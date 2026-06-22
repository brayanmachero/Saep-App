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
