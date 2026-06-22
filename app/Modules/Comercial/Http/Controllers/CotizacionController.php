<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Models\MailLog;
use App\Modules\Comercial\Models\CentroCosto;
use App\Modules\Comercial\Models\Cliente;
use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Models\Modalidad;
use App\Modules\Comercial\Models\Parametro;
use App\Modules\Comercial\Services\CalculadoraCotizacionService;
use App\Modules\Comercial\Services\GeneradorPDFService;
use App\Services\MailAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CotizacionController
{
    public function __construct(
        private readonly CalculadoraCotizacionService $calculador,
        private readonly GeneradorPDFService $generadorPDF
    ) {
    }

    public function index(Request $request)
    {
        $query = Cotizacion::with(['cliente', 'centroCosto', 'modalidad', 'usuario']);
        $this->aplicarFiltrosIndex($query, $request);

        $cotizaciones = $query
            ->latest('fecha_cotizacion')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
        $clientes = Cliente::activos()->orderBy('nombre')->get();
        $centrosCosto = CentroCosto::activos()
            ->with('cliente')
            ->orderBy('nombre')
            ->get();
        $modalidades = Modalidad::activas()->orderBy('codigo')->get();
        $resumenEstados = [
            'vigentes' => Cotizacion::whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))->count(),
            'no_vigentes' => Cotizacion::whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_NO_VIGENTE))->count(),
            'en_cotizacion' => Cotizacion::where('estado', Cotizacion::ESTADO_EN_COTIZACION)->count(),
            'por_vencer' => Cotizacion::whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))
                ->whereNotNull('fecha_vigencia_hasta')
                ->whereBetween('fecha_vigencia_hasta', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
                ->count(),
        ];

        return view('comercial::cotizador.index', compact(
            'cotizaciones',
            'clientes',
            'centrosCosto',
            'modalidades',
            'resumenEstados'
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

        $vigenteRelacionada = Cotizacion::with(['cliente', 'centroCosto', 'modalidad'])
            ->where('id', '!=', $cotizacion->id)
            ->where('cliente_id', $cotizacion->cliente_id)
            ->where('centro_costo_id', $cotizacion->centro_costo_id)
            ->where('modalidad_id', $cotizacion->modalidad_id)
            ->where('cargo', $cotizacion->cargo)
            ->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))
            ->latest('fecha_vigencia')
            ->latest('id')
            ->first();

        return view('comercial::cotizador.show', compact('cotizacion', 'vigenteRelacionada'));
    }

    public function edit(Cotizacion $cotizacion)
    {
        if ($cotizacion->estado !== 'en_cotizacion') {
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
        if ($cotizacion->estado !== 'en_cotizacion') {
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
                    'estado' => 'en_cotizacion',
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

                $guardada = $this->calculador->guardar($nueva, $datosCalculo);

                $this->registrarAuditoria($guardada, 'duplicada', 'Cotización duplicada como borrador editable', [
                    'cotizacion_origen_id' => $cotizacion->id,
                    'cotizacion_origen_numero' => $cotizacion->numero,
                    'precio_origen' => $cotizacion->precio_venta,
                    'precio_nuevo' => $guardada->precio_venta,
                ]);

                return $guardada;
            });

            return redirect()->route('comercial.cotizaciones.edit', $nuevaCotizacion)
                ->with('success', 'Se creó una copia editable como nueva versión borrador.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No fue posible duplicar la cotización: '.$e->getMessage());
        }
    }

    public function destroy(Request $request, Cotizacion $cotizacion)
    {
        if (! auth()->user()?->esAdminSistema()) {
            abort(403, 'Solo un administrador o super administrador puede eliminar cotizaciones.');
        }

        $validated = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->registrarAuditoria($cotizacion, 'eliminada', 'Cotización eliminada', [
            'motivo' => $validated['motivo'],
            'estado' => $cotizacion->estado,
            'precio_venta' => $cotizacion->precio_venta,
        ]);
        $cotizacion->delete();

        return redirect()->route('comercial.cotizaciones.index')
            ->with('success', 'Cotización eliminada.');
    }

    public function aprobar(Cotizacion $cotizacion)
    {
        if ($cotizacion->estado !== 'en_cotizacion') {
            return back()->with('error', 'Solo puedes aprobar cotizaciones en estado En Cotización.');
        }

        $this->activarCotizacion($cotizacion);

        return back()->with('success', 'Cotización aprobada y marcada como vigente.');
    }

    public function hacerVigente(Cotizacion $cotizacion)
    {
        if (! in_array($cotizacion->estado, ['en_cotizacion', 'aprobada'], true)) {
            return back()->with('error', 'Solo puedes dejar vigente una cotización en estado En cotización.');
        }

        $this->activarCotizacion($cotizacion);

        return back()->with('success', 'Cotización ahora es vigente.');
    }

    public function rechazar(Request $request, Cotizacion $cotizacion)
    {
        if ($cotizacion->estadoOperativo() !== Cotizacion::ESTADO_EN_COTIZACION) {
            return back()->with('error', 'Solo puedes marcar como no vigente una cotización en estado En cotización.');
        }

        $validated = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
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
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
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
            $pdf = $this->generadorPDF->generar($cotizacion)->output();

            $sentMessage = Mail::send('emails.comercial_cotizacion', [
                MailAutomationService::CUSTOM_MAIL_KEY => 'ComercialCotizacionMail',
                'cotizacion' => $cotizacion->load(['cliente', 'centroCosto', 'modalidad']),
                'mensajeUsuario' => $validated['mensaje'] ?? null,
            ], function ($message) use ($validated, $cotizacion, $pdf) {
                $message->to($validated['destinatario'])
                    ->subject($validated['asunto'])
                    ->attachData($pdf, "cotizacion-{$cotizacion->numero}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
            });

            if ($sentMessage === null) {
                return back()->with('error', 'El email no fue enviado porque la automatizacion de cotizaciones esta desactivada.');
            }

            $this->registrarAuditoria($cotizacion, 'email_enviado', 'Cotización enviada por email', [
                'destinatario' => $validated['destinatario'],
            ]);

            return back()->with('success', 'Cotización enviada por email.');
        } catch (\Throwable $e) {
            report($e);
            MailLog::recordFailed($validated['destinatario'], $validated['asunto'], $e->getMessage(), 'ComercialCotizacionMail');

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

            foreach ($vigentesReemplazadas as $vigente) {
                $vigente->update([
                    'estado' => Cotizacion::ESTADO_NO_VIGENTE,
                    'fecha_vigencia_hasta' => now(),
                    'updated_at' => now(),
                ]);

                $this->registrarAuditoria($vigente, 'no_vigente', 'Cotización reemplazada por una nueva vigente/aprobada', [
                    'cotizacion_reemplazo_id' => $cotizacion->id,
                    'cotizacion_reemplazo_numero' => $cotizacion->numero,
                    'precio_reemplazo' => $cotizacion->precio_venta,
                ]);
            }

            $cotizacion->update([
                'estado' => Cotizacion::ESTADO_VIGENTE,
                'fecha_aprobacion' => $cotizacion->fecha_aprobacion ?? now(),
                'fecha_vigencia' => now(),
            ]);

            $this->registrarAuditoria($cotizacion, 'vigente', 'Cotización aprobada y marcada como vigente', [
                'cotizaciones_reemplazadas' => $vigentesReemplazadas
                    ->map(fn (Cotizacion $vigente) => [
                        'id' => $vigente->id,
                        'numero' => $vigente->numero,
                        'precio_venta' => $vigente->precio_venta,
                    ])
                    ->values()
                    ->all(),
            ]);
        });
    }

    private function marcarNoVigente(Cotizacion $cotizacion, string $motivo, string $descripcion): void
    {
        $cotizacion->update([
            'estado' => Cotizacion::ESTADO_NO_VIGENTE,
            'fecha_cancelacion' => now(),
            'fecha_vigencia_hasta' => now(),
        ]);

        $this->registrarAuditoria($cotizacion, 'no_vigente', $descripcion, [
            'motivo' => $motivo,
        ]);
    }

    private function aplicarFiltrosIndex($query, Request $request): void
    {
        if ($request->filled('q')) {
            $busqueda = trim((string) $request->query('q'));

            $query->where(function ($subQuery) use ($busqueda) {
                $subQuery
                    ->where('numero', 'like', "%{$busqueda}%")
                    ->orWhere('titulo', 'like', "%{$busqueda}%")
                    ->orWhere('cargo', 'like', "%{$busqueda}%")
                    ->orWhereHas('cliente', function ($clienteQuery) use ($busqueda) {
                        $clienteQuery
                            ->where('nombre', 'like', "%{$busqueda}%")
                            ->orWhere('nombre_comercial', 'like', "%{$busqueda}%")
                            ->orWhere('rut', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('centroCosto', function ($centroQuery) use ($busqueda) {
                        $centroQuery
                            ->where('nombre', 'like', "%{$busqueda}%")
                            ->orWhere('codigo', 'like', "%{$busqueda}%");
                    });
            });
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->integer('cliente_id'));
        }

        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->integer('centro_costo_id'));
        }

        if ($request->filled('modalidad_id')) {
            $query->where('modalidad_id', $request->integer('modalidad_id'));
        }

        if ($request->filled('estado')) {
            $query->whereIn('estado', Cotizacion::estadosParaFiltro($request->input('estado')));
        }

        if ($request->filled('cargo')) {
            $query->where('cargo', 'like', '%'.trim((string) $request->query('cargo')).'%');
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_cotizacion', '>=', Carbon::parse($request->query('fecha_desde'))->toDateString());
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_cotizacion', '<=', Carbon::parse($request->query('fecha_hasta'))->toDateString());
        }

        if ($request->filled('vigencia_desde')) {
            $desde = Carbon::parse($request->query('vigencia_desde'))->startOfDay();

            $query
                ->whereNotNull('fecha_vigencia')
                ->where(function ($subQuery) use ($desde) {
                    $subQuery
                        ->whereNull('fecha_vigencia_hasta')
                        ->orWhereDate('fecha_vigencia_hasta', '>=', $desde->toDateString());
                });
        }

        if ($request->filled('vigencia_hasta')) {
            $hasta = Carbon::parse($request->query('vigencia_hasta'))->endOfDay();

            $query
                ->whereNotNull('fecha_vigencia')
                ->whereDate('fecha_vigencia', '<=', $hasta->toDateString());
        }

        if ($request->filled('vence_hasta')) {
            $hastaVencimiento = Carbon::parse($request->query('vence_hasta'))->endOfDay();

            $query
                ->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))
                ->whereNotNull('fecha_vigencia_hasta')
                ->whereBetween('fecha_vigencia_hasta', [now()->startOfDay(), $hastaVencimiento]);
        }
    }

    private function datosDesdeCotizacion(Cotizacion $cotizacion): array
    {
        return [
            'cliente_id' => $cotizacion->cliente_id,
            'centro_costo_id' => $cotizacion->centro_costo_id,
            'modalidad_id' => $cotizacion->modalidad_id,
            'usuario_id' => auth()->id(),
            'remuneraciones' => $cotizacion->detalles
                ->where('tipo', 'remuneracion')
                ->reject(fn ($detalle) => $this->esConceptoCalculado($detalle->concepto))
                ->map(fn ($detalle) => [
                    'concepto' => $detalle->concepto,
                    'valor' => (float) $detalle->valor_base,
                ])
                ->values()
                ->all(),
            'uniformes' => $cotizacion->uniformes
                ->map(fn ($uniforme) => [
                    'descripcion' => $uniforme->descripcion,
                    'cantidad' => (int) $uniforme->cantidad,
                    'precio_unitario' => (float) $uniforme->precio_unitario,
                ])
                ->values()
                ->all(),
            'asignacion_movilizacion' => $this->valorDetalle($cotizacion, 'Asignación Movilización'),
            'asignacion_colacion' => $this->valorDetalle($cotizacion, 'Asignación Colación'),
            'servicios_casino' => $this->valorDetalle($cotizacion, 'Servicios de Casino'),
            'seguro_accidentes' => $this->valorDetalle($cotizacion, 'Seguro Accidentes Personales'),
            'otros_gastos' => $this->valorDetalle($cotizacion, 'Otros Gastos'),
            'otros_beneficios' => $this->valorDetalle($cotizacion, 'Otros Beneficios'),
        ];
    }

    private function valorDetalle(Cotizacion $cotizacion, string $concepto): float
    {
        return (float) ($cotizacion->detalles->firstWhere('concepto', $concepto)?->valor ?? 0);
    }

    private function esConceptoCalculado(string $concepto): bool
    {
        $concepto = mb_strtolower($concepto, 'UTF-8');
        $concepto = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $concepto);

        return str_contains($concepto, 'gratific')
            || str_contains($concepto, 'moviliz')
            || str_contains($concepto, 'colaci');
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
            throw ValidationException::withMessages([
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
