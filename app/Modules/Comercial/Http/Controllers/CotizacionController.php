<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\CentroCosto;
use App\Modules\Comercial\Models\Cliente;
use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Models\Modalidad;
use App\Modules\Comercial\Models\Parametro;
use App\Modules\Comercial\Services\CalculadoraCotizacionService;
use App\Modules\Comercial\Services\GeneradorPDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CotizacionController
{
    public function __construct(
        private readonly CalculadoraCotizacionService $calculador,
        private readonly GeneradorPDFService $generadorPDF
    ) {
    }

    public function index(Request $request)
    {
        $query = Cotizacion::with(['cliente', 'centroCosto', 'modalidad'])
            ->latest('fecha_cotizacion')
            ->latest('id');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->integer('cliente_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $cotizaciones = $query->paginate(20)->withQueryString();
        $clientes = Cliente::activos()->orderBy('nombre')->get();

        return view('comercial::cotizador.index', compact('cotizaciones', 'clientes'));
    }

    public function create()
    {
        $clientes = Cliente::activos()->orderBy('nombre')->get();
        $modalidades = Modalidad::activas()->orderBy('codigo')->get();
        $parametrosPorCategoria = Parametro::editables()
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get()
            ->groupBy('categoria');
        $sueldoMinimoLegal = Parametro::valor('SUELDO_MINIMO', 0);
        $centrosCostoAgrupados = CentroCosto::activos()
            ->orderBy('nombre')
            ->get(['id', 'cliente_id', 'nombre', 'codigo'])
            ->groupBy('cliente_id')
            ->map(fn ($items) => $items->values())
            ->toArray();

        return view('comercial::cotizador.create', compact(
            'clientes',
            'modalidades',
            'centrosCostoAgrupados',
            'parametrosPorCategoria',
            'sueldoMinimoLegal',
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validarCotizacion($request);

        try {
            $cotizacion = DB::transaction(function () use ($validated, $request) {
                $datosCalculo = $this->calculador->calcular($this->datosParaCalculo($validated));

                $cotizacion = new Cotizacion([
                    'titulo' => $validated['titulo'] ?? null,
                    'cargo' => $validated['cargo'],
                    'cliente_id' => $validated['cliente_id'],
                    'centro_costo_id' => $validated['centro_costo_id'],
                    'modalidad_id' => $validated['modalidad_id'],
                    'usuario_id' => auth()->id(),
                    'estado' => 'en_cotizacion',
                    'fecha_cotizacion' => now(),
                    'fecha_vigencia_desde' => $validated['fecha_vigencia_desde'] ?? now(),
                    'fecha_vigencia_hasta' => $validated['fecha_vigencia_hasta'] ?? now()->addDays(config('comercial.quotation.default_validity_days', 30)),
                    'observaciones' => $validated['observaciones'] ?? null,
                    'total_remuneraciones' => $datosCalculo['total_remuneraciones'],
                    'total_cotizaciones' => $datosCalculo['total_cotizaciones'],
                    'total_provisiones' => $datosCalculo['total_provisiones'],
                    'total_gastos' => $datosCalculo['total_gastos'],
                    'subtotal' => $datosCalculo['subtotal'],
                    'margen' => $datosCalculo['margen'],
                    'precio_venta' => $datosCalculo['precio_venta'],
                ]);

                return $this->calculador->guardar($cotizacion, $datosCalculo);
            });

            return redirect()->route('comercial.cotizaciones.show', $cotizacion)
                ->with('success', 'Cotización creada exitosamente.');
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Error al calcular la cotización: '.$e->getMessage());
        }
    }

    public function previsualizar(Request $request)
    {
        $validated = $request->validate([
            'modalidad_id' => 'required|exists:comercial_modalidades,id',
            'remuneraciones' => 'nullable|array',
            'remuneraciones.*.concepto' => 'nullable|string|max:160',
            'remuneraciones.*.valor' => 'nullable|numeric|min:0',
            'uniformes' => 'nullable|array',
            'uniformes.*.descripcion' => 'nullable|string|max:160',
            'uniformes.*.cantidad' => 'nullable|integer|min:0',
            'uniformes.*.precio_unitario' => 'nullable|numeric|min:0',
            'asignacion_movilizacion' => 'nullable|numeric|min:0',
            'asignacion_colacion' => 'nullable|numeric|min:0',
            'servicios_casino' => 'nullable|numeric|min:0',
            'seguro_accidentes' => 'nullable|numeric|min:0',
            'otros_gastos' => 'nullable|numeric|min:0',
            'otros_beneficios' => 'nullable|numeric|min:0',
        ]);

        $validated['remuneraciones'] = collect($validated['remuneraciones'] ?? [])
            ->filter(fn ($item) => ! empty($item['concepto']) && (float) ($item['valor'] ?? 0) >= 0)
            ->values()
            ->all();

        $calculo = $this->calculador->calcular($validated);

        return response()->json([
            'total_remuneraciones' => $calculo['total_remuneraciones'],
            'total_cotizaciones' => $calculo['total_cotizaciones'],
            'total_provisiones' => $calculo['total_provisiones'],
            'total_gastos' => $calculo['total_gastos'],
            'subtotal' => $calculo['subtotal'],
            'margen_porcentaje' => $calculo['margen_porcentaje'],
            'margen' => $calculo['margen'],
            'precio_venta' => $calculo['precio_venta'],
            'horas' => $calculo['horas'] ?? [],
        ]);
    }

    public function show(Cotizacion $cotizacion)
    {
        $cotizacion->load([
            'cliente',
            'centroCosto',
            'modalidad',
            'detalles' => fn ($query) => $query->orderBy('orden'),
            'uniformes',
            'usuario',
            'auditorias.usuario',
            'cotizacionesPosteriores',
        ]);

        return view('comercial::cotizador.show', compact('cotizacion'));
    }

    public function edit(Cotizacion $cotizacion)
    {
        if ($cotizacion->estado !== 'en_cotizacion') {
            return back()->with('error', 'Solo puedes editar cotizaciones en estado En Cotización.');
        }

        $cotizacion->load(['cliente', 'centroCosto', 'modalidad', 'detalles', 'uniformes']);

        return view('comercial::cotizador.edit', compact('cotizacion'));
    }

    public function update(Request $request, Cotizacion $cotizacion)
    {
        if ($cotizacion->estado !== 'en_cotizacion') {
            return back()->with('error', 'No se puede actualizar una cotización aprobada o vigente.');
        }

        $validated = $this->validarCotizacion($request, $cotizacion);

        try {
            DB::transaction(function () use ($cotizacion, $validated) {
                $datosCalculo = $this->calculador->calcular($this->datosParaCalculo($validated, $cotizacion));

                $this->calculador->actualizar($cotizacion, $datosCalculo, [
                    'titulo' => $validated['titulo'] ?? null,
                    'cargo' => $validated['cargo'],
                    'cliente_id' => $validated['cliente_id'],
                    'centro_costo_id' => $validated['centro_costo_id'],
                    'modalidad_id' => $validated['modalidad_id'],
                    'fecha_vigencia_desde' => $validated['fecha_vigencia_desde'] ?? $cotizacion->fecha_vigencia_desde,
                    'fecha_vigencia_hasta' => $validated['fecha_vigencia_hasta'] ?? $cotizacion->fecha_vigencia_hasta,
                    'observaciones' => $validated['observaciones'] ?? null,
                ]);
            });

            return redirect()->route('comercial.cotizaciones.show', $cotizacion)
                ->with('success', 'Cotización actualizada exitosamente.');
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Error al recalcular la cotización: '.$e->getMessage());
        }
    }

    public function destroy(Cotizacion $cotizacion)
    {
        $cotizacion->delete();

        return redirect()->route('comercial.cotizaciones.index')
            ->with('success', 'Cotización eliminada.');
    }

    public function aprobar(Cotizacion $cotizacion)
    {
        if ($cotizacion->estado !== 'en_cotizacion') {
            return back()->with('error', 'Solo puedes aprobar cotizaciones en estado En Cotización.');
        }

        $cotizacion->update([
            'estado' => 'aprobada',
            'fecha_aprobacion' => now(),
        ]);

        $this->registrarAuditoria($cotizacion, 'aprobada', 'Cotización aprobada');

        return back()->with('success', 'Cotización aprobada.');
    }

    public function hacerVigente(Cotizacion $cotizacion)
    {
        if ($cotizacion->estado !== 'aprobada') {
            return back()->with('error', 'La cotización debe estar aprobada antes de quedar vigente.');
        }

        DB::transaction(function () use ($cotizacion) {
            Cotizacion::where('id', '!=', $cotizacion->id)
                ->where('cliente_id', $cotizacion->cliente_id)
                ->where('centro_costo_id', $cotizacion->centro_costo_id)
                ->where('modalidad_id', $cotizacion->modalidad_id)
                ->where('cargo', $cotizacion->cargo)
                ->where('estado', 'vigente')
                ->update([
                    'estado' => 'no_vigente',
                    'fecha_vigencia_hasta' => now(),
                    'updated_at' => now(),
                ]);

            $cotizacion->update([
                'estado' => 'vigente',
                'fecha_vigencia' => now(),
            ]);

            $this->registrarAuditoria($cotizacion, 'vigente', 'Cotización marcada como vigente');
        });

        return back()->with('success', 'Cotización ahora es vigente.');
    }

    public function cancelar(Cotizacion $cotizacion)
    {
        if (! in_array($cotizacion->estado, ['en_cotizacion', 'aprobada', 'vigente'], true)) {
            return back()->with('error', 'Esta cotización no puede cancelarse.');
        }

        $cotizacion->update([
            'estado' => 'cancelada',
            'fecha_cancelacion' => now(),
        ]);

        $this->registrarAuditoria($cotizacion, 'cancelada', 'Cotización cancelada');

        return back()->with('success', 'Cotización cancelada.');
    }

    public function historico(Cotizacion $cotizacion)
    {
        $versiones = Cotizacion::with(['cliente', 'centroCosto', 'modalidad'])
            ->where('cliente_id', $cotizacion->cliente_id)
            ->where('centro_costo_id', $cotizacion->centro_costo_id)
            ->where('modalidad_id', $cotizacion->modalidad_id)
            ->where('cargo', $cotizacion->cargo)
            ->orderByDesc('created_at')
            ->get();

        return view('comercial::cotizador.historico', compact('cotizacion', 'versiones'));
    }

    public function enviarEmail(Request $request, Cotizacion $cotizacion)
    {
        $validated = $request->validate([
            'destinatario' => 'required|email',
            'asunto' => 'required|string|max:180',
            'mensaje' => 'nullable|string|max:2000',
        ]);

        try {
            $pdf = $this->generadorPDF->generar($cotizacion)->output();

            Mail::send('emails.comercial_cotizacion', [
                'cotizacion' => $cotizacion->load(['cliente', 'centroCosto', 'modalidad']),
                'mensajeUsuario' => $validated['mensaje'] ?? null,
            ], function ($message) use ($validated, $cotizacion, $pdf) {
                $message->to($validated['destinatario'])
                    ->subject($validated['asunto'])
                    ->attachData($pdf, "cotizacion-{$cotizacion->numero}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
            });

            $this->registrarAuditoria($cotizacion, 'email_enviado', 'Cotización enviada por email', [
                'destinatario' => $validated['destinatario'],
            ]);

            return back()->with('success', 'Cotización enviada por email.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No fue posible enviar el email: '.$e->getMessage());
        }
    }

    public function generatePdf(Cotizacion $cotizacion)
    {
        $this->registrarAuditoria($cotizacion, 'pdf_generado', 'PDF de cotización generado/descargado', [
            'numero' => $cotizacion->numero,
            'precio_venta' => $cotizacion->precio_venta,
        ]);

        return $this->generadorPDF->descargar($cotizacion);
    }

    private function validarCotizacion(Request $request, ?Cotizacion $cotizacion = null): array
    {
        return $request->validate([
            'titulo' => 'nullable|string|max:180',
            'cargo' => 'required|string|max:180',
            'cliente_id' => 'required|exists:comercial_clientes,id',
            'centro_costo_id' => 'required|exists:comercial_centros_costo,id',
            'modalidad_id' => 'required|exists:comercial_modalidades,id',
            'fecha_vigencia_desde' => 'nullable|date',
            'fecha_vigencia_hasta' => 'nullable|date|after_or_equal:fecha_vigencia_desde',
            'observaciones' => 'nullable|string|max:3000',
            'remuneraciones' => 'required|array|min:1',
            'remuneraciones.*.concepto' => 'required|string|max:160',
            'remuneraciones.*.valor' => 'required|numeric|min:0',
            'uniformes' => 'nullable|array',
            'uniformes.*.descripcion' => 'nullable|string|max:160',
            'uniformes.*.cantidad' => 'nullable|integer|min:0',
            'uniformes.*.precio_unitario' => 'nullable|numeric|min:0',
            'asignacion_movilizacion' => 'nullable|numeric|min:0',
            'asignacion_colacion' => 'nullable|numeric|min:0',
            'servicios_casino' => 'nullable|numeric|min:0',
            'seguro_accidentes' => 'nullable|numeric|min:0',
            'otros_gastos' => 'nullable|numeric|min:0',
            'otros_beneficios' => 'nullable|numeric|min:0',
        ]);
    }

    private function datosParaCalculo(array $validated, ?Cotizacion $cotizacion = null): array
    {
        return array_merge($validated, [
            'usuario_id' => auth()->id(),
        ]);
    }

    private function registrarAuditoria(Cotizacion $cotizacion, string $accion, string $descripcion, array $cambios = []): void
    {
        $cotizacion->auditorias()->create([
            'usuario_id' => auth()->id(),
            'accion' => $accion,
            'descripcion' => $descripcion,
            'cambios' => $cambios ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);
    }
}
