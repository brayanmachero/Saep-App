<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\CentroCosto;
use App\Modules\Comercial\Models\Cliente;
use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Models\Modalidad;
use App\Modules\Comercial\Models\Parametro;
use App\Modules\Comercial\Services\CalculadoraCotizacionService;
use App\Modules\Comercial\Services\GeneradorPDFService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
        $baseQuery = Cotizacion::with(['cliente', 'centroCosto', 'modalidad', 'usuario']);
        $this->aplicarFiltrosIndex($baseQuery, $request);

        $vigentes = (clone $baseQuery)
            ->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))
            ->latest('fecha_vigencia')
            ->latest('id')
            ->paginate(8, ['*'], 'vigentes_page')
            ->withQueryString();

        $enGestion = (clone $baseQuery)
            ->where('estado', Cotizacion::ESTADO_EN_COTIZACION)
            ->latest('fecha_cotizacion')
            ->latest('id')
            ->paginate(8, ['*'], 'gestion_page')
            ->withQueryString();

        $historicas = (clone $baseQuery)
            ->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_NO_VIGENTE))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(8, ['*'], 'historicas_page')
            ->withQueryString();

        $resumenQuery = Cotizacion::query();
        $this->aplicarFiltrosIndex($resumenQuery, $request);

        $resumenEstados = [
            'vigentes' => (clone $resumenQuery)->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))->count(),
            'gestion' => (clone $resumenQuery)->where('estado', Cotizacion::ESTADO_EN_COTIZACION)->count(),
            'pendientes_aprobar' => (clone $resumenQuery)->where('estado', Cotizacion::ESTADO_EN_COTIZACION)->count(),
            'vigentes_por_vencer' => (clone $resumenQuery)
                ->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))
                ->whereNotNull('fecha_vigencia_hasta')
                ->whereBetween('fecha_vigencia_hasta', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
                ->count(),
            'historicas' => (clone $resumenQuery)->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_NO_VIGENTE))->count(),
            'precio_vigente' => (clone $resumenQuery)->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))->sum('precio_venta'),
        ];

        $agrupadasQuery = Cotizacion::with(['cliente', 'centroCosto', 'modalidad'])
            ->select([
                'cliente_id',
                'centro_costo_id',
                'modalidad_id',
                'cargo',
            ])
            ->selectRaw('COUNT(*) as total_cotizaciones')
            ->selectRaw("SUM(CASE WHEN estado IN ('vigente', 'aprobada') THEN 1 ELSE 0 END) as total_vigentes")
            ->selectRaw("SUM(CASE WHEN estado IN ('no_vigente', 'rechazada', 'cancelada') THEN 1 ELSE 0 END) as total_historicas")
            ->selectRaw("MAX(CASE WHEN estado IN ('vigente', 'aprobada') THEN precio_venta ELSE NULL END) as precio_vigente")
            ->selectRaw('MAX(COALESCE(fecha_vigencia, fecha_aprobacion, fecha_cotizacion)) as ultima_actividad')
            ->groupBy('cliente_id', 'centro_costo_id', 'modalidad_id', 'cargo')
            ->orderByDesc('ultima_actividad');

        $this->aplicarFiltrosIndex($agrupadasQuery, $request);

        $agrupadas = $agrupadasQuery
            ->paginate(10, ['*'], 'agrupadas_page')
            ->withQueryString();

        $clientes = Cliente::activos()->orderBy('nombre')->get();
        $centrosCosto = CentroCosto::with('cliente')
            ->activos()
            ->orderBy('nombre')
            ->get(['id', 'cliente_id', 'nombre', 'codigo']);

        return view('comercial::cotizador.index', compact(
            'vigentes',
            'enGestion',
            'historicas',
            'agrupadas',
            'clientes',
            'centrosCosto',
            'resumenEstados',
        ));
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
        $uniformesCatalogo = Parametro::editables()
            ->porCategoria('UNIFORMES')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Parametro $parametro) => [
                'id' => $parametro->id,
                'clave' => $parametro->clave,
                'nombre' => $parametro->nombre,
                'valor' => (float) $parametro->valor_actual,
                'valor_visual' => $parametro->formatearValorVisual(),
            ])
            ->values();
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
            'uniformesCatalogo',
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
                    'estado' => Cotizacion::ESTADO_EN_COTIZACION,
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

    public function duplicar(Cotizacion $cotizacion)
    {
        $cotizacion->load(['detalles', 'uniformes']);

        try {
            $nuevaCotizacion = DB::transaction(function () use ($cotizacion) {
                $datosEntrada = $this->datosDesdeCotizacion($cotizacion);
                $datosCalculo = $this->calculador->calcular($datosEntrada);

                $nueva = new Cotizacion([
                    'titulo' => $cotizacion->titulo,
                    'cargo' => $cotizacion->cargo,
                    'cliente_id' => $cotizacion->cliente_id,
                    'centro_costo_id' => $cotizacion->centro_costo_id,
                    'modalidad_id' => $cotizacion->modalidad_id,
                    'usuario_id' => auth()->id(),
                    'estado' => Cotizacion::ESTADO_EN_COTIZACION,
                    'version' => ((int) $cotizacion->version) + 1,
                    'cotizacion_anterior_id' => $cotizacion->id,
                    'fecha_cotizacion' => now(),
                    'fecha_vigencia_desde' => now(),
                    'fecha_vigencia_hasta' => now()->addDays(config('comercial.quotation.default_validity_days', 30)),
                    'observaciones' => $cotizacion->observaciones,
                    'total_remuneraciones' => $datosCalculo['total_remuneraciones'],
                    'total_cotizaciones' => $datosCalculo['total_cotizaciones'],
                    'total_provisiones' => $datosCalculo['total_provisiones'],
                    'total_gastos' => $datosCalculo['total_gastos'],
                    'subtotal' => $datosCalculo['subtotal'],
                    'margen' => $datosCalculo['margen'],
                    'precio_venta' => $datosCalculo['precio_venta'],
                ]);

                $nueva = $this->calculador->guardar($nueva, $datosCalculo);
                $this->registrarAuditoria($nueva, 'duplicada', "Cotización duplicada desde {$cotizacion->numero}", [
                    'cotizacion_origen_id' => $cotizacion->id,
                    'cotizacion_origen_numero' => $cotizacion->numero,
                    'precio_origen' => $cotizacion->precio_venta,
                    'precio_nuevo' => $nueva->precio_venta,
                ]);

                return $nueva;
            });

            return redirect()->route('comercial.cotizaciones.edit', $nuevaCotizacion)
                ->with('success', "Cotización duplicada como borrador {$nuevaCotizacion->numero}. Ajusta los datos antes de aprobarla.");
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No fue posible duplicar la cotización: '.$e->getMessage());
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
            'resumen_excel' => $calculo['resumen_excel'] ?? [],
            'detalles' => $calculo['detalles'] ?? [],
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

        $relacionadasQuery = Cotizacion::with(['cliente', 'centroCosto', 'modalidad'])
            ->where('id', '!=', $cotizacion->id)
            ->where('cliente_id', $cotizacion->cliente_id)
            ->where('centro_costo_id', $cotizacion->centro_costo_id)
            ->where('modalidad_id', $cotizacion->modalidad_id)
            ->where('cargo', $cotizacion->cargo);

        $vigenteComercialActual = (clone $relacionadasQuery)
            ->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))
            ->latest('fecha_vigencia')
            ->latest('id')
            ->first();

        $historicasComerciales = (clone $relacionadasQuery)
            ->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_NO_VIGENTE))
            ->latest('updated_at')
            ->latest('id')
            ->limit(6)
            ->get();

        $relacionadasCount = (clone $relacionadasQuery)->count();

        return view('comercial::cotizador.show', compact(
            'cotizacion',
            'vigenteComercialActual',
            'historicasComerciales',
            'relacionadasCount',
        ));
    }

    public function edit(Cotizacion $cotizacion)
    {
        if ($cotizacion->estadoOperativo() !== Cotizacion::ESTADO_EN_COTIZACION) {
            return back()->with('error', 'Solo puedes editar cotizaciones en estado En Cotización.');
        }

        $cotizacion->load(['cliente', 'centroCosto', 'modalidad', 'detalles', 'uniformes']);
        $uniformesCatalogo = Parametro::editables()
            ->porCategoria('UNIFORMES')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Parametro $parametro) => [
                'id' => $parametro->id,
                'clave' => $parametro->clave,
                'nombre' => $parametro->nombre,
                'valor' => (float) $parametro->valor_actual,
                'valor_visual' => $parametro->formatearValorVisual(),
            ])
            ->values();

        return view('comercial::cotizador.edit', compact('cotizacion', 'uniformesCatalogo'));
    }

    public function update(Request $request, Cotizacion $cotizacion)
    {
        if ($cotizacion->estadoOperativo() !== Cotizacion::ESTADO_EN_COTIZACION) {
            return back()->with('error', 'Solo se puede actualizar una cotización en estado En cotización.');
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

    public function destroy(Request $request, Cotizacion $cotizacion)
    {
        if (! auth()->user()?->esAdminSistema()) {
            abort(403, 'Solo un administrador o super administrador puede eliminar cotizaciones.');
        }

        $validated = $request->validate([
            'motivo' => 'required|string|min:5|max:1000',
        ]);

        $this->registrarAuditoria($cotizacion, 'eliminada', 'Cotización eliminada', [
            'motivo' => $validated['motivo'],
        ]);
        $cotizacion->delete();

        return redirect()->route('comercial.cotizaciones.index')
            ->with('success', 'Cotización eliminada.');
    }

    public function aprobar(Cotizacion $cotizacion)
    {
        if ($cotizacion->estadoOperativo() !== Cotizacion::ESTADO_EN_COTIZACION) {
            return back()->with('error', 'Solo puedes aprobar cotizaciones en estado En Cotización.');
        }

        $this->activarCotizacion($cotizacion);

        return back()->with('success', 'Cotización aprobada y marcada como vigente.');
    }

    public function hacerVigente(Cotizacion $cotizacion)
    {
        if (! in_array($cotizacion->estado, [Cotizacion::ESTADO_EN_COTIZACION, 'aprobada'], true)) {
            return back()->with('error', 'Solo puedes dejar vigente una cotización en estado En cotización.');
        }

        $this->activarCotizacion($cotizacion);

        return back()->with('success', 'Cotización ahora es vigente y PDF final actualizado.');
    }

    public function rechazar(Request $request, Cotizacion $cotizacion)
    {
        if ($cotizacion->estadoOperativo() !== Cotizacion::ESTADO_EN_COTIZACION) {
            return back()->with('error', 'Solo puedes marcar como no vigente una cotización en estado En cotización.');
        }

        $validated = $request->validate([
            'motivo' => 'required|string|min:5|max:1000',
        ]);

        $this->marcarNoVigente($cotizacion, $validated['motivo'], 'Cotización marcada como no vigente desde En cotización');

        return back()->with('success', 'Cotización marcada como no vigente.');
    }

    public function cancelar(Request $request, Cotizacion $cotizacion)
    {
        if ($cotizacion->estadoOperativo() !== Cotizacion::ESTADO_VIGENTE) {
            return back()->with('error', 'Solo puedes marcar como no vigente una cotización vigente/aprobada.');
        }

        $validated = $request->validate([
            'motivo' => 'required|string|min:5|max:1000',
        ]);

        $this->marcarNoVigente($cotizacion, $validated['motivo'], 'Cotización vigente/aprobada marcada como no vigente');

        return back()->with('success', 'Cotización marcada como no vigente.');
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
            $pdf = $this->generadorPDF->contenidoPDF($cotizacion);

            Mail::send('emails.comercial_cotizacion', [
                'cotizacion' => $cotizacion->load(['cliente', 'centroCosto', 'modalidad']),
                'mensajeUsuario' => $validated['mensaje'] ?? null,
            ], function ($message) use ($validated, $cotizacion, $pdf) {
                $message->to($validated['destinatario'])
                    ->subject($validated['asunto']);

                if ($from = config('comercial.email.from')) {
                    $message->from($from, config('comercial.email.from_name'));
                }

                $message
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

    private function activarCotizacion(Cotizacion $cotizacion): void
    {
        DB::transaction(function () use ($cotizacion) {
            $vigentesReemplazadas = Cotizacion::where('id', '!=', $cotizacion->id)
                ->where('cliente_id', $cotizacion->cliente_id)
                ->where('centro_costo_id', $cotizacion->centro_costo_id)
                ->where('modalidad_id', $cotizacion->modalidad_id)
                ->where('cargo', $cotizacion->cargo)
                ->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))
                ->lockForUpdate()
                ->get();

            foreach ($vigentesReemplazadas as $reemplazada) {
                $reemplazada->update([
                    'estado' => Cotizacion::ESTADO_NO_VIGENTE,
                    'fecha_fin_vigencia_real' => now(),
                    'fecha_vigencia_hasta' => now(),
                ]);

                $this->registrarAuditoria($reemplazada, 'no_vigente', 'Cotización reemplazada por una nueva vigente/aprobada', [
                    'cotizacion_reemplazo_id' => $cotizacion->id,
                    'cotizacion_reemplazo_numero' => $cotizacion->numero,
                    'precio_reemplazo' => $cotizacion->precio_venta,
                ]);
            }

            $cotizacion->update([
                'estado' => Cotizacion::ESTADO_VIGENTE,
                'fecha_aprobacion' => $cotizacion->fecha_aprobacion ?? now(),
                'fecha_vigencia' => now(),
                'fecha_fin_vigencia_real' => null,
            ]);

            $this->registrarAuditoria($cotizacion, 'vigente', 'Cotización aprobada y marcada como vigente', [
                'cotizaciones_reemplazadas' => $vigentesReemplazadas
                    ->map(fn (Cotizacion $reemplazada) => [
                        'id' => $reemplazada->id,
                        'numero' => $reemplazada->numero,
                        'precio_venta' => $reemplazada->precio_venta,
                    ])
                    ->values()
                    ->all(),
            ]);

            $this->guardarPdfFinal($cotizacion, Cotizacion::ESTADO_VIGENTE);
        });
    }

    private function marcarNoVigente(Cotizacion $cotizacion, string $motivo, string $descripcion): void
    {
        $cotizacion->update([
            'estado' => Cotizacion::ESTADO_NO_VIGENTE,
            'fecha_cancelacion' => now(),
            'fecha_fin_vigencia_real' => now(),
            'fecha_vigencia_hasta' => now(),
        ]);

        $this->registrarAuditoria($cotizacion, 'no_vigente', $descripcion, [
            'motivo' => $motivo,
        ]);
    }

    private function validarCotizacion(Request $request, ?Cotizacion $cotizacion = null): array
    {
        $validated = $request->validate([
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

        $centroValido = CentroCosto::whereKey($validated['centro_costo_id'])
            ->where('cliente_id', $validated['cliente_id'])
            ->exists();

        if (! $centroValido) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'centro_costo_id' => 'El centro de costo seleccionado no pertenece al cliente indicado.',
            ]);
        }

        return $validated;
    }

    private function datosParaCalculo(array $validated, ?Cotizacion $cotizacion = null): array
    {
        return array_merge($validated, [
            'usuario_id' => auth()->id(),
        ]);
    }

    private function datosDesdeCotizacion(Cotizacion $cotizacion): array
    {
        $detallePor = fn (string $concepto) => $cotizacion->detalles
            ->first(fn ($detalle) => str_contains(mb_strtolower((string) $detalle->concepto), mb_strtolower($concepto)));

        $remuneraciones = $cotizacion->detalles
            ->where('tipo', 'remuneracion')
            ->reject(function ($detalle) {
                $concepto = mb_strtolower((string) $detalle->concepto);

                return str_contains($concepto, 'gratific')
                    || str_contains($concepto, 'moviliz')
                    || str_contains($concepto, 'colaci')
                    || str_contains($concepto, 'colacion');
            })
            ->map(fn ($detalle) => [
                'concepto' => $detalle->concepto,
                'valor' => (float) $detalle->valor_base,
            ])
            ->filter(fn ($item) => $item['concepto'] !== '' && $item['valor'] >= 0)
            ->values()
            ->all();

        $valorDetalle = fn (string $concepto) => (float) (($detallePor($concepto)?->valor) ?? 0);

        return [
            'cliente_id' => $cotizacion->cliente_id,
            'centro_costo_id' => $cotizacion->centro_costo_id,
            'modalidad_id' => $cotizacion->modalidad_id,
            'usuario_id' => auth()->id(),
            'remuneraciones' => $remuneraciones,
            'uniformes' => $cotizacion->uniformes
                ->map(fn ($uniforme) => [
                    'descripcion' => $uniforme->descripcion,
                    'cantidad' => (int) $uniforme->cantidad,
                    'precio_unitario' => (float) $uniforme->precio_unitario,
                ])
                ->values()
                ->all(),
            'asignacion_movilizacion' => $valorDetalle('Asignación Movilización'),
            'asignacion_colacion' => $valorDetalle('Asignación Colación'),
            'servicios_casino' => $valorDetalle('Servicios de Casino'),
            'seguro_accidentes' => $valorDetalle('Seguro Accidentes'),
            'otros_gastos' => $valorDetalle('Otros Gastos'),
            'otros_beneficios' => $valorDetalle('Otros Beneficios'),
        ];
    }

    private function aplicarFiltrosIndex(Builder $query, Request $request): void
    {
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->integer('cliente_id'));
        }

        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->integer('centro_costo_id'));
        }

        if ($request->filled('cargo')) {
            $cargo = trim((string) $request->query('cargo'));
            $query->where('cargo', 'like', "%{$cargo}%");
        }

        if ($request->filled('estado')) {
            $estado = (string) $request->query('estado');

            match ($estado) {
                'gestion' => $query->where('estado', Cotizacion::ESTADO_EN_COTIZACION),
                'historico' => $query->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_NO_VIGENTE)),
                default => $query->whereIn('estado', Cotizacion::estadosParaFiltro($estado)),
            };
        }

        if ($request->filled('vence_hasta')) {
            $hastaVencimiento = Carbon::parse($request->query('vence_hasta'))->endOfDay();

            $query
                ->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))
                ->whereNotNull('fecha_vigencia_hasta')
                ->whereBetween('fecha_vigencia_hasta', [now()->startOfDay(), $hastaVencimiento]);
        }

        if ($request->filled('vigencia_desde') || $request->filled('vigencia_hasta')) {
            $desde = $request->filled('vigencia_desde')
                ? Carbon::parse($request->query('vigencia_desde'))->startOfDay()
                : now()->subYears(50)->startOfDay();
            $hasta = $request->filled('vigencia_hasta')
                ? Carbon::parse($request->query('vigencia_hasta'))->endOfDay()
                : now()->addYears(50)->endOfDay();

            $query
                ->where(function (Builder $builder) use ($hasta) {
                    $builder
                        ->where('fecha_vigencia', '<=', $hasta)
                        ->orWhere(function (Builder $fallback) use ($hasta) {
                            $fallback
                                ->whereNull('fecha_vigencia')
                                ->where('fecha_aprobacion', '<=', $hasta);
                        })
                        ->orWhere(function (Builder $fallback) use ($hasta) {
                            $fallback
                                ->whereNull('fecha_vigencia')
                                ->whereNull('fecha_aprobacion')
                                ->where('fecha_vigencia_desde', '<=', $hasta);
                        })
                        ->orWhere(function (Builder $fallback) use ($hasta) {
                            $fallback
                                ->whereNull('fecha_vigencia')
                                ->whereNull('fecha_aprobacion')
                                ->whereNull('fecha_vigencia_desde')
                                ->where('fecha_cotizacion', '<=', $hasta);
                        });
                })
                ->where(function (Builder $builder) use ($desde) {
                    $builder
                        ->whereNull('fecha_fin_vigencia_real')
                        ->orWhere('fecha_fin_vigencia_real', '>=', $desde);
                });
        }

        if ($request->filled('q')) {
            $termino = trim((string) $request->query('q'));

            $query->where(function (Builder $builder) use ($termino) {
                $builder
                    ->where('numero', 'like', "%{$termino}%")
                    ->orWhere('cargo', 'like', "%{$termino}%")
                    ->orWhere('titulo', 'like', "%{$termino}%")
                    ->orWhereHas('cliente', function (Builder $cliente) use ($termino) {
                        $cliente->where('nombre', 'like', "%{$termino}%")
                            ->orWhere('nombre_comercial', 'like', "%{$termino}%")
                            ->orWhere('rut', 'like', "%{$termino}%");
                    })
                    ->orWhereHas('centroCosto', function (Builder $centro) use ($termino) {
                        $centro->where('nombre', 'like', "%{$termino}%")
                            ->orWhere('codigo', 'like', "%{$termino}%");
                    });
            });
        }
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

    private function guardarPdfFinal(Cotizacion $cotizacion, string $estado): void
    {
        $cotizacion->refresh();

        $pdfFinal = $this->generadorPDF->guardarPDFFinal($cotizacion);

        $cotizacion->forceFill([
            'pdf_final_path' => $pdfFinal['path'],
            'pdf_final_hash' => $pdfFinal['hash'],
            'pdf_final_generado_at' => $pdfFinal['generado_at'],
        ])->save();

        $this->registrarAuditoria($cotizacion, 'pdf_final_guardado', "PDF final {$estado} guardado", [
            'estado' => $estado,
            'path' => $pdfFinal['path'],
            'hash' => $pdfFinal['hash'],
        ]);
    }
}
