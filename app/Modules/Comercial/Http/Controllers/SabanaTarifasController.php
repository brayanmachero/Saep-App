<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\TarifaFuente;
use App\Modules\Comercial\Models\TarifaReferencia;
use App\Modules\Comercial\Services\SabanaTarifasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SabanaTarifasController
{
    private function validar(Request $request, array $reglas): array
    {
        return $request->validate($reglas, [
            'required' => 'Completa el campo :attribute.',
            'required_if' => 'Para confirmar la tarifa, completa el campo :attribute.',
            'string' => 'El campo :attribute debe contener texto.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'numeric' => 'El campo :attribute debe ser un número válido.',
            'array' => 'La selección de :attribute no es válida.',
            'in' => 'Selecciona una opción válida para :attribute.',
            'exists' => 'La referencia seleccionada ya no está disponible.',
            'distinct' => 'Selecciona tarifas distintas para comparar.',
            'date_format' => 'Indica una fecha válida para :attribute.',
            'after_or_equal' => 'La fecha de término no puede ser anterior al inicio de vigencia.',
            'min.array' => 'Selecciona al menos :min tarifas para comparar.',
            'max.array' => 'Puedes comparar hasta :max tarifas.',
            'min.numeric' => 'El campo :attribute debe ser mayor o igual a :min.',
            'max.numeric' => 'El campo :attribute debe ser menor o igual a :max.',
            'max.string' => 'El campo :attribute admite hasta :max caracteres.',
        ], [
            'costo_total' => 'costo total del servicio', 'q' => 'búsqueda', 'cliente' => 'cliente', 'centro' => 'centro de costo',
            'cargo' => 'cargo o servicio', 'categoria' => 'categoría', 'modalidad' => 'modalidad',
            'unidad' => 'unidad de cobro', 'fecha' => 'fecha de consulta', 'anio' => 'año',
            'vista' => 'vista', 'ids' => 'tarifas', 'base' => 'tarifa de origen',
            'variante' => 'condiciones', 'moneda' => 'moneda', 'precio' => 'precio',
            'fecha_referencia' => 'fecha del documento', 'observaciones' => 'respaldo y condiciones',
            'estado' => 'revisión', 'vigente_desde' => 'inicio de vigencia',
            'vigente_hasta' => 'término de vigencia', 'motivo' => 'motivo de revisión',
        ]);
    }

    private function filtros(Request $request): array
    {
        return $this->validar($request, [
            'q' => 'nullable|string|max:255', 'cliente' => 'nullable|string|max:255',
            'centro' => 'nullable|string|max:255', 'cargo' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:255', 'modalidad' => 'nullable|string|max:255',
            'unidad' => 'nullable|string|max:255', 'fecha' => 'nullable|date_format:Y-m-d',
            'vista' => 'nullable|in:ultimas,referencias,vigentes,pendientes,historico,cotizaciones',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);
    }

    public function index(Request $request, SabanaTarifasService $service)
    {
        $filtros = $this->filtros($request);
        $opciones = [];
        $contexto = TarifaReferencia::query();
        foreach (['cliente', 'centro', 'cargo'] as $campo) {
            $opciones[$campo] = (clone $contexto)->whereNotNull($campo)->where($campo, '!=', '')
                ->distinct()->orderBy($campo)->pluck($campo);
            if (! empty($filtros[$campo])) {
                if ($campo !== 'cliente' && ! (clone $contexto)->where($campo, $filtros[$campo])->exists()) {
                    $limpiar = $campo === 'centro' ? ['centro', 'cargo'] : ['cargo'];
                    return redirect()->route('comercial.tarifas.index', $request->except(array_merge($limpiar, ['categoria', 'modalidad', 'unidad', 'page'])))
                        ->with('info', 'Se ajustaron los filtros para mostrar las opciones del cliente y centro seleccionados.');
                }
                $contexto->where($campo, $filtros[$campo]);
            }
        }
        foreach (['categoria', 'modalidad', 'unidad'] as $campo) {
            $opciones[$campo] = TarifaReferencia::whereNotNull($campo)->where($campo, '!=', '')->distinct()->orderBy($campo)->pluck($campo);
        }
        $tarifas = $service->consulta($filtros)->paginate(40)->withQueryString();
        $resumen = [
            'fuentes' => TarifaFuente::count(), 'referencias' => TarifaReferencia::count(),
            'pendientes' => TarifaReferencia::where('estado', 'pendiente')->whereNull('cotizacion_id')->count(),
            'vigentes' => $service->consulta(['vista' => 'vigentes'])->count(),
        ];
        $fuentes = TarifaFuente::withCount('tarifas')->orderBy('carpeta')->orderBy('archivo')->get();

        $vigentesHoy = $service->consulta(['vista' => 'vigentes'])->whereIn('id', $tarifas->modelKeys())->pluck('id')->all();
        $cotizaciones = \App\Modules\Comercial\Models\Cotizacion::query();
        $conciliacion = ['total' => (clone $cotizaciones)->count(),
            'vinculadas' => (clone $cotizaciones)->whereHas('tarifaReferencia')->count(),
            'pendientes' => (clone $cotizaciones)->whereDoesntHave('tarifaReferencia')->orderBy('id')->limit(10)->get(['id', 'numero'])];
        return view('comercial::tarifas.index', compact('tarifas', 'opciones', 'filtros', 'resumen', 'fuentes', 'vigentesHoy', 'conciliacion'));
    }

    public function comparar(Request $request, \App\Modules\Comercial\Services\TarifaRevisionService $revision)
    {
        $datos = $this->validar($request, ['ids'=>'required|array|min:2|max:4', 'ids.*'=>'required|integer|distinct|exists:comercial_tarifas_referencia,id']);
        $porId = TarifaReferencia::with(['fuente', 'cotizacion'])->whereIn('id', $datos['ids'])->get()->keyBy('id');
        $tarifas = collect($datos['ids'])->map(fn ($id) => $porId[$id]);
        $base = $tarifas->first();
        $diferencias = $tarifas->mapWithKeys(fn ($t) => [$t->id => $revision->diferencia($base, $t)]);
        $vigentesHoy = app(SabanaTarifasService::class)->consulta(['vista'=>'vigentes'])->whereIn('id', $datos['ids'])->pluck('id')->all();
        return view('comercial::tarifas.comparar', compact('tarifas', 'base', 'diferencias', 'vigentesHoy'));
    }

    public function validacion(Request $request, \App\Modules\Comercial\Services\TarifaRevisionService $revision)
    {
        $filtros = $this->validar($request, ['cliente'=>'nullable|string|max:255', 'centro'=>'nullable|string|max:255', 'motivo'=>'nullable|in:cliente,unidad,modalidad,categoria,fecha_referencia,vigente_desde,precio']);
        $query = TarifaReferencia::where('estado', 'pendiente')->whereNull('cotizacion_id');
        $clientes = (clone $query)->distinct()->orderBy('cliente')->pluck('cliente');
        if (!empty($filtros['cliente'])) $query->where('cliente', $filtros['cliente']);
        $centros = (clone $query)->whereNotNull('centro')->distinct()->orderBy('centro')->pluck('centro');
        if (!empty($filtros['centro'])) $query->where('centro', $filtros['centro']);
        $motivo = $filtros['motivo'] ?? null;
        if ($motivo === 'cliente') $query->where(fn ($q) => $q->whereNull('cliente')->orWhere('cliente', '')->orWhere('cliente', 'Por homologar'));
        elseif ($motivo) {
            $query->where(function ($q) use ($motivo) {
                $q->whereNull($motivo);
                if (in_array($motivo, ['unidad','modalidad','categoria'])) $q->orWhere($motivo, '');
            });
        }
        $tarifas = $query->orderBy('cliente')->orderBy('centro')->orderBy('cargo')->orderBy('id')->paginate(40)->withQueryString();
        $pendientes = $tarifas->getCollection()->mapWithKeys(fn ($t) => [$t->id => $revision->pendientes($t)]);
        return view('comercial::tarifas.validacion', compact('tarifas','clientes','centros','pendientes'));
    }

    public function show(TarifaReferencia $tarifa)
    {
        $tarifa->load('fuente', 'revisiones', 'cotizacion');
        $versiones = TarifaReferencia::with('cotizacion')->where('grupo', $tarifa->grupo)->orderByDesc('fecha_referencia')->get();

        $grupos = app(\App\Modules\Comercial\Services\TarifaPresentacionService::class)->grupos($tarifa->conceptos ?? []);
        $vigentesHoy = app(SabanaTarifasService::class)->consulta(['vista' => 'vigentes'])
            ->whereIn('id', $versiones->modelKeys())->pluck('id')->all();
        $vigenteHoy = in_array($tarifa->id, $vigentesHoy, true);
        $revisionMargen = app(\App\Modules\Comercial\Services\TarifaMargenService::class)->tarifa($tarifa);
        return view('comercial::tarifas.show', compact('tarifa', 'versiones', 'grupos', 'vigenteHoy', 'vigentesHoy', 'revisionMargen'));
    }

    public function archivo(TarifaReferencia $tarifa)
    {
        $fuente = $tarifa->fuente;
        abort_unless($fuente && preg_match('/^[a-f0-9]{64}$/', $fuente->hash_sha256), 404, 'Archivo de respaldo no disponible.');
        foreach (['2026.zip', 'IDL.zip', 'complementarios.zip'] as $archivo) {
            $zip = new \ZipArchive;
            $path = storage_path('app/private/cotizador-evaluacion-20261013/'.$archivo);
            if (! is_file($path) || $zip->open($path) !== true) continue;
            try {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $info = $zip->statIndex($i);
                    if (! $info || $info['size'] > 25 * 1024 * 1024 || basename(str_replace('\\', '/', $info['name'])) !== $fuente->archivo) continue;
                    $contenido = $zip->getFromIndex($i);
                    if ($contenido === false || ! hash_equals($fuente->hash_sha256, hash('sha256', $contenido))) continue;
                    return response()->streamDownload(fn () => print($contenido), basename($fuente->archivo), [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'Cache-Control' => 'private, no-store',
                        'X-Content-Type-Options' => 'nosniff',
                    ]);
                }
            } finally {
                $zip->close();
            }
        }
        abort(404, 'Archivo de respaldo no disponible. Solicítalo al equipo Comercial.');
    }

    public function create(Request $request)
    {
        $this->validar($request, ['base' => 'nullable|integer|exists:comercial_tarifas_referencia,id']);
        $base = $request->filled('base') ? TarifaReferencia::findOrFail($request->integer('base')) : new TarifaReferencia;
        if ($base->cotizacion_id) return redirect()->route('comercial.cotizaciones.show', $base->cotizacion_id);

        return view('comercial::tarifas.create', compact('base'));
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request, [
            'cliente' => 'required|string|max:255', 'centro' => 'nullable|string|max:255',
            'cargo' => 'required|string|max:255', 'categoria' => 'nullable|string|max:255',
            'modalidad' => 'nullable|string|max:255', 'unidad' => 'required|string|max:100',
            'variante' => 'nullable|string|max:255', 'moneda' => 'required|in:CLP,UF,USD',
            'precio' => 'required|numeric|min:0|max:999999999999',
            'fecha_referencia' => 'required|date_format:Y-m-d',
            'observaciones' => 'required|string|max:4000',
            'costo_total' => 'nullable|numeric|min:0.000001|max:999999999999',
        ]);
        $costo = $datos['costo_total'] ?? null;
        unset($datos['costo_total']);
        if ($costo !== null) $datos['conceptos'] = [['concepto' => 'Costo total del servicio', 'valor' => $costo]];
        $tarifa = DB::transaction(function () use ($datos, $request) {
            $identidad = hash('sha256', (string) Str::uuid());
            $fuente = TarifaFuente::create([
                'identidad' => $identidad, 'hash_sha256' => hash('sha256', json_encode($datos)),
                'archivo' => 'Registro manual — '.$request->user()->name, 'carpeta' => 'Comercial / Registro manual',
                'estado' => 'leida', 'observaciones' => $datos['observaciones'],
            ]);
            $tarifa = TarifaReferencia::create($datos + [
                'identidad' => $identidad, 'grupo' => TarifaReferencia::grupoPara($datos),
                'fuente_id' => $fuente->id, 'estado' => 'pendiente',
            ]);
            $tarifa->revisiones()->create(['usuario_id' => $request->user()->id, 'antes' => [], 'despues' => $tarifa->toArray()]);

            return $tarifa;
        });

        return redirect()->route('comercial.tarifas.show', $tarifa)->with('success', 'Referencia registrada. Confirma su vigencia después de revisar el origen.');
    }

    public function revisar(Request $request, TarifaReferencia $tarifa)
    {
        abort_if($tarifa->cotizacion_id, 409, 'Esta tarifa está vinculada al cotizador. Gestiona la cotización de origen.');
        $datos = $this->validar($request, [
            'categoria' => 'nullable|string|max:255', 'modalidad' => 'nullable|required_if:estado,confirmada|string|max:100',
            'cliente' => 'sometimes|required|string|max:255', 'centro' => 'sometimes|nullable|string|max:255',
            'cargo' => 'sometimes|required|string|max:255', 'variante' => 'sometimes|nullable|string|max:255',
            'unidad' => 'nullable|required_if:estado,confirmada|string|max:100',
            'estado' => ['required', Rule::in(['pendiente', 'confirmada', 'descartada'])],
            'fecha_referencia' => 'nullable|date_format:Y-m-d',
            'vigente_desde' => 'nullable|required_if:estado,confirmada|date_format:Y-m-d',
            'vigente_hasta' => 'nullable|date_format:Y-m-d|after_or_equal:vigente_desde',
            'motivo' => 'required|string|max:2000',
        ]);
        abort_if($datos['estado'] === 'confirmada' && $tarifa->precio === null, 422, 'No se puede confirmar una tarifa sin precio.');
        if ($datos['estado'] === 'confirmada') {
            abort_if(($datos['cliente'] ?? $tarifa->cliente) === 'Por homologar', 422, 'Identifica el cliente antes de confirmar.');
            abort_if(collect($tarifa->conceptos ?? [])->contains(fn ($c) => is_string($c['valor'] ?? null) && str_starts_with($c['valor'], '#')), 422, 'El desglose contiene errores de Excel. Registra una versión corregida.');
        }
        DB::transaction(function () use ($datos, $request, $tarifa) {
            $registro = TarifaReferencia::lockForUpdate()->findOrFail($tarifa->id);
            abort_if($registro->cotizacion_id, 409, 'Esta tarifa está vinculada al cotizador.');
            $antes = $registro->toArray();
            $registro->fill(collect($datos)->except('motivo')->all());
            $revisionMargen = app(\App\Modules\Comercial\Services\TarifaMargenService::class)->tarifa($registro);
            if ($datos['estado'] === 'confirmada') app(\App\Modules\Comercial\Services\TarifaMargenService::class)->exigir($revisionMargen);
            $registro->grupo = TarifaReferencia::grupoPara($registro->toArray());
            $registro->revisado_por = $request->user()->id;
            $registro->revisado_at = now();
            $registro->save();
            $registro->revisiones()->create([
                'usuario_id' => $request->user()->id, 'antes' => $antes,
                'despues' => $registro->toArray() + ['motivo_revision' => $datos['motivo'], 'revision_margen' => $revisionMargen],
            ]);
        });

        return back()->with('success', 'Revisión guardada con historial.');
    }

    public function export(Request $request, SabanaTarifasService $service)
    {
        $query = $service->consulta($this->filtros($request));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Cliente', 'Centro', 'Cargo', 'Categoría', 'Modalidad', 'Unidad', 'Variante', 'Moneda', 'Tarifa', 'Período origen', 'Fecha referencia', 'Estado revisión', 'Vigente desde', 'Vigente hasta', 'Archivo', 'Hoja', 'Celda', 'Observaciones', 'Cotización', 'Estado cotización'], ';');
            foreach ($query->lazy(200) as $tarifa) {
                $fila = [$tarifa->cliente, $tarifa->centro, $tarifa->cargo, $tarifa->categoria, $tarifa->modalidad, $tarifa->unidad, $tarifa->variante, $tarifa->moneda,
                    $tarifa->precio, $tarifa->periodo, $tarifa->fecha_referencia?->format('Y-m-d'), $tarifa->estado,
                    $tarifa->vigente_desde?->format('Y-m-d'), $tarifa->vigente_hasta?->format('Y-m-d'),
                    $tarifa->fuente?->archivo, $tarifa->hoja, $tarifa->celda, $tarifa->observaciones,
                    $tarifa->cotizacion?->numero, $tarifa->cotizacion ? ($tarifa->cotizacion->trashed() ? 'Eliminada' : \App\Modules\Comercial\Models\Cotizacion::etiquetaEstado($tarifa->cotizacion->estado)) : null];
                fputcsv($out, array_map(fn ($valor) => is_string($valor) && preg_match('/^[\s]*[=+@-]/u', $valor) ? "'".$valor : $valor, $fila), ';');
            }
            fclose($out);
        }, 'sabana-tarifas-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
