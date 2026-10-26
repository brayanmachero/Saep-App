<?php

namespace App\Modules\Comercial\Services;

use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Models\TarifaReferencia;
use Illuminate\Support\Facades\DB;

class CotizacionTarifaService
{
    public function sincronizar(Cotizacion $cotizacion, bool $aplicar = true): string
    {
        return DB::transaction(function () use ($cotizacion, $aplicar) {
            // Serialize saves and reconciliation for the same quotation.
            $cotizacion = Cotizacion::withTrashed()->lockForUpdate()->findOrFail($cotizacion->id);
            $tarifa = TarifaReferencia::where('cotizacion_id', $cotizacion->id)->first();
            $origen = data_get($cotizacion->datos_calculo, 'origen');
            if ($origen) {
                if ($tarifa) {
                    if ($aplicar) $this->actualizarHistorico($tarifa, $cotizacion);
                    return 'historico_enlazado';
                }
                if (empty($origen['hash_sha256']) || empty($origen['hoja']) || empty($origen['celda_precio'])) return 'revisar_origen';
                $candidatas = TarifaReferencia::whereHas('fuente', fn ($q) => $q->where('hash_sha256', $origen['hash_sha256']))
                    ->where('hoja', $origen['hoja'])->where('celda', $origen['celda_precio'])->lockForUpdate()->get();
                if ($candidatas->count() !== 1) return 'revisar_origen';
                $tarifa = $candidatas->first();
                $normalizar = fn ($s) => mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $s)));
                $cliente = $cotizacion->cliente;
                if ($tarifa->cotizacion_id || $tarifa->precio === null
                    || round((float) $tarifa->precio, 2) !== round((float) $cotizacion->precio_venta, 2)
                    || $normalizar($tarifa->cliente) !== $normalizar($cliente?->nombre_comercial ?: $cliente?->nombre)
                    || $normalizar($tarifa->centro) !== $normalizar($cotizacion->centroCosto?->nombre)
                    || $normalizar($tarifa->cargo) !== $normalizar($cotizacion->cargo)
                    || $normalizar($tarifa->modalidad) !== $normalizar($cotizacion->modalidad?->codigo)
                    || $tarifa->fecha_referencia?->toDateString() !== $cotizacion->fecha_cotizacion?->toDateString()) return 'revisar_datos';
                if ($aplicar) {
                    $this->actualizarHistorico($tarifa, $cotizacion);
                }
                return 'historico_coincidente';
            }
            if (! $aplicar) return $tarifa ? 'cotizacion_sincronizable' : 'cotizacion_nueva';
            $cliente = $cotizacion->cliente;
            $datos = [
                'cliente' => $cliente?->nombre_comercial ?: ($cliente?->nombre ?? 'Por homologar'),
                'centro' => $cotizacion->centroCosto?->nombre, 'cargo' => $cotizacion->cargo ?: $cotizacion->titulo,
                'modalidad' => $cotizacion->modalidad?->codigo,
                // EST/SUB calculate a monthly price and derive hours from that monthly base.
                'unidad' => in_array(data_get($cotizacion->datos_calculo, 'modalidad'), ['EST', 'SUB']) ? 'mes' : null,
                'moneda' => 'CLP', 'precio' => $cotizacion->precio_venta,
                'fecha_referencia' => $cotizacion->fecha_cotizacion?->toDateString(),
                'vigente_desde' => $cotizacion->fecha_vigencia_desde?->toDateString(),
                'vigente_hasta' => $cotizacion->fecha_vigencia_hasta?->toDateString(),
                'estado' => $this->estado($cotizacion),
                'conceptos' => collect($cotizacion->detalles_json ?? [])->map(fn ($d) => [
                    'concepto' => $d['concepto'] ?? '', 'valor' => $d['valor'] ?? null,
                ])->push(['concepto' => 'Costo total del servicio', 'valor' => $cotizacion->subtotal])
                    ->push(['concepto' => 'Precio de venta', 'valor' => $cotizacion->precio_venta])->values()->all(),
                'observaciones' => $cotizacion->observaciones,
                'vinculo_tipo' => 'cotizador',
            ];
            $datos['grupo'] = TarifaReferencia::grupoPara($datos);
            // MySQL normalizes JSON object key order. Do not audit a change for
            // serialization-only differences when the stored concepts are equal.
            if ($tarifa && $tarifa->conceptos == $datos['conceptos']) {
                unset($datos['conceptos']);
            }
            $tarifa ??= new TarifaReferencia(['cotizacion_id' => $cotizacion->id, 'identidad' => hash('sha256', 'cotizador:'.$cotizacion->id)]);
            $antes = $tarifa->exists ? $tarifa->toArray() : [];
            $tarifa->fill($datos);
            if ($tarifa->isDirty()) {
                $tarifa->save();
                $this->auditar($tarifa, $antes, 'Actualización desde cotizador '.$cotizacion->numero.'.');
            }
            return 'cotizacion_sincronizada';
        });
    }

    private function estado(Cotizacion $cotizacion): string
    {
        return $cotizacion->trashed() ? 'no_vigente' : match ($cotizacion->estadoOperativo()) {
            'vigente' => 'confirmada', 'en_cotizacion' => 'en_cotizacion', default => 'no_vigente',
        };
    }

    private function actualizarHistorico(TarifaReferencia $tarifa, Cotizacion $cotizacion): void
    {
        $antes = $tarifa->toArray();
        $tarifa->fill(['cotizacion_id' => $cotizacion->id, 'vinculo_tipo' => 'historico', 'estado' => $this->estado($cotizacion)]);
        // Imported dates describe the source, not an approval. Only transfer validity after an actual approval.
        if ($cotizacion->fecha_aprobacion) {
            $tarifa->vigente_desde = $cotizacion->fecha_vigencia_desde;
            $tarifa->vigente_hasta = $cotizacion->fecha_vigencia_hasta;
        }
        if ($tarifa->isDirty()) {
            $tarifa->save();
            $this->auditar($tarifa, $antes, 'Histórico conciliado por origen y datos; estado reflejado desde '.$cotizacion->numero.'. Precio y respaldo conservados.');
        }
    }

    private function auditar(TarifaReferencia $tarifa, array $antes, string $motivo): void
    {
        $tarifa->revisiones()->create(['usuario_id' => auth()->id(), 'antes' => $antes,
            'despues' => $tarifa->toArray() + ['motivo_revision' => $motivo]]);
    }
}
