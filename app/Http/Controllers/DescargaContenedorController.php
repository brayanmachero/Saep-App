<?php

namespace App\Http\Controllers;

use App\Models\CentroCosto;
use App\Models\ArchivoAdjunto;
use App\Models\DescargaContenedor;
use App\Models\DescargaContenedorCarga;
use App\Models\DescargaContenedorTarifa;
use App\Models\TalanaTrabajador;
use App\Services\ContenedoresIaInterpreter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DescargaContenedorController extends Controller
{
    public function index(Request $request)
    {
        $puedeGestionarCostos = $this->puedeGestionarCostos();
        $puedeEditarContenedores = auth()->user()?->tieneAcceso('descarga_contenedores', 'puede_editar') ?? false;
        $this->associateMissingUniqueTarifas();
        $query = DescargaContenedor::with(['carga', 'centroCosto', 'participantes', 'creadoPor', 'tarifa'])
            ->withCount('participantes');

        if ($request->filled('buscar')) {
            $term = trim($request->input('buscar'));
            $rutTerm = $this->normalizeRutSearch($term);
            $query->where(function ($q) use ($term, $rutTerm) {
                $q->where('contenedor', 'like', "%{$term}%")
                    ->orWhere('bodega', 'like', "%{$term}%")
                    ->orWhere('equipo_descarga', 'like', "%{$term}%")
                    ->orWhere('producto', 'like', "%{$term}%")
                    ->orWhere('fact_codigo', 'like', "%{$term}%")
                    ->orWhereHas('carga', function ($carga) use ($term) {
                        $carga->where('nombre', 'like', "%{$term}%");
                    })
                    ->orWhereHas('participantes', function ($p) use ($term) {
                        $p->where('nombre_snapshot', 'like', "%{$term}%")
                            ->orWhere('rut_snapshot', 'like', "%{$term}%");
                    });

                if ($rutTerm) {
                    $q->orWhereHas('participantes', function ($p) use ($rutTerm) {
                        $p->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut_snapshot, '.', ''), '-', ''), ' ', '')) LIKE ?", ["%{$rutTerm}%"]);
                    });
                }
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->input('centro_costo_id'));
        }

        if ($puedeGestionarCostos && $request->input('tarifa_estado') === 'revision') {
            $query->where('requiere_revision_tarifa', true);
        } elseif ($puedeGestionarCostos && $request->input('tarifa_estado') === 'sin_tarifa') {
            $query->whereNull('tarifa_id');
        } elseif ($puedeGestionarCostos && $request->input('tarifa_estado') === 'con_tarifa') {
            $query->whereNotNull('tarifa_id');
        }

        if ($request->input('equipo_estado') === 'sin_equipo') {
            $query->doesntHave('participantes');
        } elseif ($request->input('equipo_estado') === 'con_equipo') {
            $query->has('participantes');
        }

        if ($request->input('validacion_estado') === 'listos') {
            $this->applyReadyForValidationFilter($query);
        } elseif ($request->input('validacion_estado') === 'pendientes') {
            $this->applyPendingValidationFilter($query);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->input('fecha_hasta'));
        }

        $perPage = $this->listPerPage($request);
        $descargas = $query->orderByRaw('fecha IS NULL')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $centros = $this->centrosOperacion();
        $stats = [
            'validadas' => DescargaContenedor::where('estado', 'validado')->count(),
            'liquidadas' => DescargaContenedor::where('estado', 'liquidado')->count(),
            'revision_tarifa' => $puedeGestionarCostos
                ? DescargaContenedor::where('requiere_revision_tarifa', true)->count()
                : 0,
            'listos_validar' => $this->readyForValidationQuery()->count(),
            'pendientes_validar' => $this->pendingValidationQuery()->count(),
        ];

        $filterKeys = ['buscar', 'centro_costo_id', 'estado', 'validacion_estado', 'tarifa_estado', 'equipo_estado', 'fecha_desde', 'fecha_hasta'];
        $showValidationQueue = $puedeEditarContenedores
            && !collect($filterKeys)->contains(fn (string $key) => $request->filled($key));

        $validationQueue = $showValidationQueue
            ? $this->validationQueue()
            : ['ready' => collect(), 'pending' => collect()];

        $form = $this->formData();

        return view('descarga_contenedores.index', [
            'descargas' => $descargas,
            'centros' => $centros,
            'stats' => $stats,
            'validationQueue' => $validationQueue,
            'showValidationQueue' => $showValidationQueue,
            'tarifas' => $form['tarifas'],
            'trabajadores' => $form['trabajadores'],
            'perPage' => $perPage,
        ]);
    }

    public function create()
    {
        return view('descarga_contenedores.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $descarga = DB::transaction(function () use ($request, $data) {
            $data['origen'] = 'manual';
            $data['estado'] = 'borrador';
            $this->stampSupervisorFromLogin($data);
            $this->applyTarifaSnapshot($data);
            $data['creado_por'] = auth()->id();
            $this->stampValidation($data);

            $descarga = DescargaContenedor::create($data);
            $this->syncParticipantes($descarga, $this->extractParticipantesFromRequest($request));
            $this->storeEvidencias($descarga, $request);

            return $descarga;
        });

        return redirect()
            ->route('descarga-contenedores.index', [
                'abrir' => $descarga->id,
                'modo' => 'edit',
            ])
            ->with('success', 'Descarga registrada correctamente.');
    }

    public function show(DescargaContenedor $descarga)
    {
        abort_unless(auth()->user()?->tieneAcceso('descarga_contenedores'), 403);

        return $this->redirectToListPanel($descarga, 'detail');
    }

    public function edit(DescargaContenedor $descarga)
    {
        $this->ensureEditable($descarga);

        return $this->redirectToListPanel($descarga, 'edit');
    }

    public function update(Request $request, DescargaContenedor $descarga)
    {
        $this->ensureEditable($descarga);

        $data = $this->validatedData($request);

        DB::transaction(function () use ($request, $descarga, $data) {
            if (!$descarga->supervisor_id) {
                $this->stampSupervisorFromLogin($data);
            }

            $this->applyTarifaSnapshot($data, $descarga);
            $descarga->update($data);
            $descarga->refresh();
            $this->syncOrRecalculateParticipantes($descarga, $request);
            $this->storeEvidencias($descarga, $request);
        });

        return $this->redirectToListPanel($descarga, 'detail')
            ->with('success', 'Descarga actualizada correctamente.');
    }

    public function quickPanel(DescargaContenedor $descarga)
    {
        abort_unless(auth()->user()?->tieneAcceso('descarga_contenedores'), 403);

        $this->associateUniqueTarifaIfMissing($descarga);

        $descarga->load([
            'participantes',
            'tarifa.centroCosto',
            'centroCosto',
            'supervisor',
            'validadoPor',
            'liquidadoPor',
            'evidencias',
        ]);

        $canEdit = $this->canQuickEdit($descarga);
        $canViewCosts = $this->puedeGestionarCostos();
        $badge = $descarga->estadoBadge;

        return response()->json([
            'can_edit' => $canEdit,
            'can_view_costs' => $canViewCosts,
            'descarga' => [
                'id' => $descarga->id,
                'contenedor' => $descarga->contenedor,
                'fecha' => optional($descarga->fecha)->format('d/m/Y'),
                'bodega' => $descarga->bodega ?: ($descarga->centroCosto->nombre ?? ''),
                'operacion' => $descarga->operacion,
                'centro_costo_id' => $descarga->centro_costo_id,
                'centro' => $descarga->centroCosto->nombre ?? '',
                'tarifa_id' => $descarga->tarifa_id,
                'fact_codigo' => $descarga->fact_codigo,
                'estado' => $descarga->estado,
                'estado_label' => $badge['label'],
                'estado_class' => $badge['class'],
                'validado_por' => $descarga->validadoPor?->nombre_completo ?: ($descarga->validadoPor?->name ?: ''),
                'validado_at' => optional($descarga->validado_at)->format('d/m/Y H:i'),
                'liquidado_por' => $descarga->liquidadoPor?->nombre_completo ?: ($descarga->liquidadoPor?->name ?: ''),
                'liquidado_at' => optional($descarga->liquidado_at)->format('d/m/Y H:i'),
                'supervisor' => $descarga->supervisor?->nombre_completo ?: '',
                'supervisor_nombre' => $descarga->supervisor_nombre,
                'facturacion_mes' => $descarga->facturacion_mes,
                'equipo_descarga' => $descarga->equipo_descarga,
                'tarifa_cliente' => $descarga->tarifa_cliente_snapshot,
                'tarifa_proceso' => $descarga->tarifa_proceso_snapshot,
                'costo_unitario' => $canViewCosts ? $descarga->costo_unitario_snapshot : null,
                'pago' => $descarga->pago_colaborador_snapshot,
                'requiere_revision_tarifa' => (bool) $descarga->requiere_revision_tarifa,
                'hora_cita' => $this->formatHoraCorta($descarga->hora_cita),
                'hora_inicio' => $this->formatHoraCorta($descarga->hora_inicio_descarga),
                'hora_termino' => $this->formatHoraCorta($descarga->hora_termino_descarga),
                'item' => $descarga->item,
                'cajas' => $descarga->cajas,
                'pallets' => $descarga->pallets,
                'producto' => $descarga->producto,
                'origen' => $descarga->origen,
                'observacion' => $descarga->observacion,
                'fecha_iso' => optional($descarga->fecha)->format('Y-m-d'),
                'can_delete' => auth()->user()?->tieneAcceso('descarga_contenedores', 'puede_eliminar')
                    && $descarga->estado !== 'liquidado',
                'blockers' => $this->visibleBlockers($descarga)->all(),
                'participantes' => $descarga->participantes
                    ->map(fn ($p) => [
                        'id' => $p->talana_trabajador_id,
                        'porcentaje' => $p->porcentaje_participacion,
                        'nombre' => $p->nombre_snapshot,
                        'rut' => $p->rut_snapshot,
                        'cargo' => $p->cargo_snapshot,
                        'centro' => $p->centro_costo_snapshot,
                        'monto' => $p->monto_calculado,
                    ])
                    ->values(),
                'evidencias' => $descarga->evidencias
                    ->map(fn ($archivo) => [
                        'id' => $archivo->id,
                        'nombre' => $archivo->nombre_original,
                        'url' => route('descarga-contenedores.evidencias.ver', $archivo),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function quickSave(Request $request, DescargaContenedor $descarga)
    {
        if (!$this->canQuickEdit($descarga)) {
            return response()->json(['message' => 'No puedes editar este registro.'], 403);
        }

        $data = $this->validatedData($request);
        foreach (['operacion', 'centro_costo_id', 'tarifa_id', 'fact_codigo'] as $field) {
            if (!array_key_exists($field, $data)) {
                $data[$field] = $descarga->{$field};
            }
        }

        DB::transaction(function () use ($request, $descarga, $data) {
            if (!$descarga->supervisor_id) {
                $this->stampSupervisorFromLogin($data);
            }

            $this->applyTarifaSnapshot($data, $descarga);
            $descarga->update($data);
            $descarga->refresh();
            $this->syncOrRecalculateParticipantes($descarga, $request);
            $this->storeEvidencias($descarga, $request);
        });

        $fresh = $descarga->fresh(['participantes', 'tarifa', 'centroCosto', 'evidencias', 'supervisor']);

        return response()->json($this->listRowPayload($fresh, 'Registro actualizado.'));
    }

    public function assignCrewBulk(Request $request)
    {
        abort_unless(auth()->user()?->tieneAcceso('descarga_contenedores'), 403);

        $data = $request->validate([
            'descargas' => ['required', 'array', 'min:1', 'max:200'],
            'descargas.*' => ['integer', 'exists:descarga_contenedores,id'],
            'participantes_json' => ['required', 'string'],
        ], [
            'descargas.max' => 'Puedes seleccionar hasta 200 contenedores por tanda.',
            'descargas.min' => 'Selecciona al menos un contenedor.',
            'descargas.required' => 'Selecciona al menos un contenedor.',
        ]);

        $payload = $this->extractParticipantesFromRequest($request);
        if ($payload === []) {
            return response()->json(['message' => 'Selecciona al menos un trabajador.'], 422);
        }

        $rows = [];
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $payload, &$rows, &$updated, &$skipped) {
            foreach ($data['descargas'] as $id) {
                $descarga = DescargaContenedor::find($id);
                if (!$descarga || !$this->canQuickEdit($descarga)) {
                    $skipped++;
                    continue;
                }

                $this->syncParticipantes($descarga, $payload);
                $updated++;
                $rows[] = $this->listRowPayload(
                    $descarga->fresh(['participantes', 'tarifa', 'centroCosto']),
                    ''
                )['row'];
            }
        });

        if ($updated === 0) {
            return response()->json([
                'message' => 'Ningún contenedor pudo actualizarse. Revisa permisos o estado.',
            ], 422);
        }

        $message = "Equipo asignado a {$updated} contenedor(es).";
        if ($skipped > 0) {
            $message .= " {$skipped} omitido(s).";
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'updated' => $updated,
            'skipped' => $skipped,
            'rows' => $rows,
        ]);
    }

    public function destroyBulk(Request $request)
    {
        abort_unless(auth()->user()?->tieneAcceso('descarga_contenedores', 'puede_eliminar'), 403);

        $data = $request->validate([
            'descargas' => ['required', 'array', 'min:1', 'max:200'],
            'descargas.*' => ['integer', 'exists:descarga_contenedores,id'],
        ], [
            'descargas.max' => 'Puedes seleccionar hasta 200 contenedores por tanda.',
            'descargas.min' => 'Selecciona al menos un contenedor.',
            'descargas.required' => 'Selecciona al menos un contenedor.',
        ]);

        $deleted = [];
        $skipped = 0;

        DB::transaction(function () use ($data, &$deleted, &$skipped) {
            foreach ($data['descargas'] as $id) {
                $descarga = DescargaContenedor::find($id);
                if (!$descarga || $descarga->estado === 'liquidado') {
                    $skipped++;
                    continue;
                }

                $descarga->delete();
                $deleted[] = (int) $id;
            }
        });

        if ($deleted === []) {
            return response()->json([
                'message' => 'Ningún contenedor pudo eliminarse. Los liquidados quedan bloqueados.',
            ], 422);
        }

        $message = count($deleted) === 1
            ? 'Contenedor eliminado.'
            : count($deleted).' contenedores eliminados.';
        if ($skipped > 0) {
            $message .= " {$skipped} omitido(s) por estado o permiso.";
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'deleted' => $deleted,
            'skipped' => $skipped,
        ]);
    }

    public function validar(DescargaContenedor $descarga)
    {
        if ($descarga->estado !== 'borrador') {
            return back()->with('error', 'Solo se pueden validar registros en borrador.');
        }

        $this->recalcularMontosParticipantes($descarga);
        $blockers = $descarga->validationBlockers();
        if ($blockers->isNotEmpty()) {
            return back()->with('error', 'No se puede validar: ' . $blockers->implode(', ') . '.');
        }

        $descarga->update([
            'estado' => 'validado',
            'validado_por' => auth()->id(),
            'validado_at' => now(),
            'liquidado_por' => null,
            'liquidado_at' => null,
        ]);

        return back()->with('success', 'Registro validado correctamente.');
    }

    public function volverBorrador(DescargaContenedor $descarga)
    {
        if ($descarga->estado !== 'validado') {
            return back()->with('error', 'Solo se pueden devolver a borrador registros validados.');
        }

        $descarga->update([
            'estado' => 'borrador',
            'validado_por' => null,
            'validado_at' => null,
            'liquidado_por' => null,
            'liquidado_at' => null,
        ]);

        return back()->with('success', 'Registro devuelto a borrador.');
    }

    public function liquidar(DescargaContenedor $descarga)
    {
        $this->authorizeCostManagement();

        if ($descarga->estado !== 'validado') {
            return back()->with('error', 'Solo se pueden liquidar registros validados.');
        }

        $this->recalcularMontosParticipantes($descarga);
        $blockers = $descarga->validationBlockers();
        if ($blockers->isNotEmpty()) {
            return back()->with('error', 'No se puede liquidar: ' . $blockers->implode(', ') . '.');
        }

        $descarga->update([
            'estado' => 'liquidado',
            'liquidado_por' => auth()->id(),
            'liquidado_at' => now(),
        ]);

        return back()->with('success', 'Registro marcado como liquidado.');
    }

    public function volverValidado(DescargaContenedor $descarga)
    {
        $this->authorizeCostManagement();

        if ($descarga->estado !== 'liquidado') {
            return back()->with('error', 'Solo se pueden reabrir registros liquidados.');
        }

        $descarga->update([
            'estado' => 'validado',
            'liquidado_por' => null,
            'liquidado_at' => null,
        ]);

        return back()->with('success', 'Registro reabierto como validado.');
    }

    public function destroy(DescargaContenedor $descarga)
    {
        if ($descarga->estado === 'liquidado') {
            return back()->with('error', 'No se puede eliminar un registro liquidado. Reábrelo primero.');
        }

        $descarga->delete();

        return redirect()
            ->route('descarga-contenedores.index')
            ->with('success', 'Descarga eliminada.');
    }

    public function verEvidencia(ArchivoAdjunto $archivo)
    {
        $this->ensureContainerEvidence($archivo);

        if (!Storage::disk('local')->exists($archivo->ruta)) {
            abort(404);
        }

        $nombre = $archivo->nombre_original ?: $archivo->nombre_archivo;
        $nombreAscii = Str::ascii($nombre);
        $nombreAscii = preg_replace('/[^A-Za-z0-9._ -]/', '_', $nombreAscii) ?: 'evidencia';

        return response()->file(Storage::disk('local')->path($archivo->ruta), [
            'Content-Type' => $archivo->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                str_replace(['/', '\\', '%'], '_', $nombre),
                $nombreAscii
            ),
        ]);
    }

    public function destroyEvidencia(DescargaContenedor $descarga, ArchivoAdjunto $archivo)
    {
        $this->ensureEditable($descarga);
        $this->ensureContainerEvidence($archivo, $descarga);

        Storage::disk('local')->delete($archivo->ruta);
        $archivo->delete();

        return back()->with('success', 'Evidencia eliminada.');
    }

    public function cargaRapida(ContenedoresIaInterpreter $interprete)
    {
        return view('descarga_contenedores.carga_rapida', array_merge($this->formData(), [
            'existingContainerDates' => $this->existingContainerDateKeys(),
            'iaDisponible' => $interprete->enabled(),
        ]));
    }

    public function interpretarConIa(Request $request, ContenedoresIaInterpreter $interprete)
    {
        $maxChars = (int) config('descarga_contenedores.ia.max_chars', 20000);
        $request->validate(['texto' => ['required', 'string', 'max:' . $maxChars]]);

        if (! $interprete->enabled()) {
            return response()->json(['message' => 'La interpretación con IA no está configurada.'], 503);
        }

        try {
            $filas = $interprete->interpret((string) $request->input('texto'));
        } catch (\Throwable $e) {
            Log::warning('Contenedores: interpretación con IA falló', ['error' => $e->getMessage(), 'user' => auth()->id()]);

            return response()->json(['message' => 'No pude interpretar la programación con IA. Intenta de nuevo o ajusta la tabla.'], 502);
        }

        Log::info('Contenedores: programación interpretada con IA', [
            'user' => auth()->id(),
            'provider' => $interprete->provider(),
            'filas' => count($filas),
        ]);

        return response()->json(['filas' => $filas]);
    }

    public function storeBulk(Request $request)
    {
        $payload = json_decode((string) $request->input('registros_json'), true);

        if (!is_array($payload) || count($payload) === 0) {
            return back()
                ->withInput()
                ->with('error', 'No hay filas para guardar. Pega una tabla y genera la vista previa.');
        }

        if (count($payload) > 200) {
            return back()
                ->withInput()
                ->with('error', 'La carga rápida permite hasta 200 filas por tanda.');
        }

        $omitDuplicates = $request->boolean('omitir_duplicados');

        $resultado = DB::transaction(function () use ($request, $payload, $omitDuplicates) {
            $carga = DescargaContenedorCarga::create([
                'nombre' => $request->input('nombre') ?: 'Carga rápida ' . now()->format('d/m/Y H:i'),
                'origen' => $request->boolean('interpretado_ia') ? 'pegado_ia' : 'pegado',
                'filas_detectadas' => count($payload),
                'filas_creadas' => 0,
                'filas_con_alertas' => 0,
                'raw_payload' => $payload,
                'creado_por' => auth()->id(),
            ]);

            $creadas = 0;
            $alertas = 0;
            $omitidas = 0;
            $contenedoresTanda = collect($payload)
                ->map(fn ($row) => is_array($row) ? $this->cleanUpper($row['contenedor'] ?? null) : null)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $seenKeys = $this->existingContainerDateKeys($contenedoresTanda);
            $centrosValidos = CentroCosto::pluck('id')->map(fn ($id) => (int) $id)->flip();
            $tarifasValidas = DescargaContenedorTarifa::pluck('id')->map(fn ($id) => (int) $id)->flip();

            foreach ($payload as $row) {
                if (!is_array($row) || $this->rowIsEmpty($row)) {
                    continue;
                }

                $data = $this->sanitizeBulkRow($this->normalizeBulkRow($row), $centrosValidos, $tarifasValidas);
                $duplicateKey = $this->containerDateKey($data['contenedor'] ?? null, $data['fecha'] ?? null);
                if ($omitDuplicates && $duplicateKey && isset($seenKeys[$duplicateKey])) {
                    $omitidas++;
                    continue;
                }

                $data['carga_id'] = $carga->id;
                $data['origen'] = 'pegado';
                $data['estado'] = 'borrador';
                $this->stampSupervisorFromLogin($data);
                $this->applyTarifaSnapshot($data);
                $data['raw_row'] = $row;
                $data['creado_por'] = auth()->id();
                $this->stampValidation($data);

                $descarga = DescargaContenedor::create($data);
                $this->syncParticipantes($descarga, $this->extractParticipantesFromRow($row));
                if ($descarga->fresh()->validationBlockers()->isNotEmpty()) {
                    $alertas++;
                }
                $creadas++;
                if ($duplicateKey) {
                    $seenKeys[$duplicateKey] = true;
                }
            }

            $carga->update([
                'filas_creadas' => $creadas,
                'filas_con_alertas' => $alertas,
            ]);

            return [$carga, $creadas, $alertas, $omitidas];
        });

        [$carga, $creadas, $alertas, $omitidas] = $resultado;
        $mensaje = "Carga rápida guardada: {$creadas} registros creados, {$alertas} con datos pendientes.";
        if ($omitidas > 0) {
            $mensaje .= " {$omitidas} duplicado" . ($omitidas === 1 ? '' : 's') . " omitido" . ($omitidas === 1 ? '' : 's') . '.';
        }

        return redirect()
            ->route('descarga-contenedores.index', ['buscar' => $carga->nombre])
            ->with('success', $mensaje);
    }

    public function cargas(Request $request)
    {
        $query = DescargaContenedorCarga::with('creadoPor')
            ->withCount('descargas');

        if ($request->filled('buscar')) {
            $term = trim($request->input('buscar'));
            $query->where(function ($q) use ($term) {
                $q->where('nombre', 'like', "%{$term}%")
                    ->orWhere('origen', 'like', "%{$term}%");
            });
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
        }

        $statsQuery = clone $query;
        $stats = [
            'tandas' => (clone $statsQuery)->count(),
            'registros' => (clone $statsQuery)->sum('filas_creadas'),
            'alertas' => (clone $statsQuery)->sum('filas_con_alertas'),
            'ultima' => (clone $statsQuery)->max('created_at'),
        ];

        $cargas = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('descarga_contenedores.cargas', compact('cargas', 'stats'));
    }

    public function dotacion(Request $request)
    {
        $this->authorizeCostManagement();

        $query = TalanaTrabajador::with(['cargo', 'centroCosto', 'centroOperativo']);
        $this->applyTrabajadoresDotacionFilter($query);

        if ($request->filled('buscar')) {
            $term = trim($request->input('buscar'));
            $rutTerm = $this->normalizeRutSearch($term);
            $query->where(function ($q) use ($term, $rutTerm) {
                $q->where('nombre', 'like', "%{$term}%")
                    ->orWhere('apellido_paterno', 'like', "%{$term}%")
                    ->orWhere('apellido_materno', 'like', "%{$term}%")
                    ->orWhere('rut', 'like', "%{$term}%")
                    ->orWhere('cargo_nombre', 'like', "%{$term}%")
                    ->orWhere('centro_costo_nombre', 'like', "%{$term}%")
                    ->orWhere('centro_operativo_nombre', 'like', "%{$term}%")
                    ->orWhereHas('centroOperativo', function ($centro) use ($term) {
                        $centro->where('nombre', 'like', "%{$term}%");
                    });

                if ($rutTerm) {
                    $q->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) LIKE ?", ["%{$rutTerm}%"]);
                }
            });
        }

        if ($request->filled('centro_costo_id')) {
            $this->applyTrabajadorCentroFilter($query, (int) $request->input('centro_costo_id'));
        }

        if ($request->filled('cargo')) {
            $cargo = trim($request->input('cargo'));
            $query->where(function ($q) use ($cargo) {
                $q->where('cargo_nombre', $cargo)
                    ->orWhereHas('cargo', function ($cargoQuery) use ($cargo) {
                        $cargoQuery->where('nombre', $cargo);
                    });
            });
        }

        if ($request->input('estado') === 'activos') {
            $query->where('activo', true);
        } elseif ($request->input('estado') === 'inactivos') {
            $query->where('activo', false);
        }

        $trabajadores = $query->orderBy('nombre')
            ->orderBy('apellido_paterno')
            ->paginate(30)
            ->withQueryString();

        $ids = $trabajadores->pluck('id')->all();
        $participacion = collect();

        if ($ids) {
            $participacion = DB::table('descarga_contenedor_participantes as p')
                ->join('descarga_contenedores as d', 'd.id', '=', 'p.descarga_contenedor_id')
                ->whereNull('d.deleted_at')
                ->whereIn('p.talana_trabajador_id', $ids)
                ->select('p.talana_trabajador_id')
                ->selectRaw('COUNT(*) as participaciones')
                ->selectRaw('COUNT(DISTINCT d.id) as descargas')
                ->selectRaw('SUM(COALESCE(p.monto_calculado, 0)) as monto_total')
                ->selectRaw('MAX(d.fecha) as ultima_descarga')
                ->groupBy('p.talana_trabajador_id')
                ->get()
                ->keyBy('talana_trabajador_id');
        }

        $centros = $this->centrosDotacion();
        $cargosQuery = TalanaTrabajador::query()
            ->whereNotNull('cargo_nombre')
            ->select('cargo_nombre');
        $this->applyTrabajadoresDotacionFilter($cargosQuery);
        if ($request->filled('centro_costo_id')) {
            $this->applyTrabajadorCentroFilter($cargosQuery, (int) $request->input('centro_costo_id'));
        }
        $cargos = $cargosQuery->distinct()
            ->orderBy('cargo_nombre')
            ->pluck('cargo_nombre');

        $trabajadoresBase = TalanaTrabajador::query();
        $this->applyTrabajadoresDotacionFilter($trabajadoresBase);
        $trabajadoresActivos = clone $trabajadoresBase;
        $trabajadoresInactivos = clone $trabajadoresBase;
        $trabajadoresCentros = clone $trabajadoresBase;
        $trabajadoresCargos = clone $trabajadoresBase;
        $trabajadoresAjustados = clone $trabajadoresBase;

        $stats = [
            'activos' => $trabajadoresActivos->where('activo', true)->count(),
            'inactivos' => $trabajadoresInactivos->where('activo', false)->count(),
            'centros' => $trabajadoresCentros->whereNotNull('centro_costo_id')->distinct()->count('centro_costo_id'),
            'cargos' => $trabajadoresCargos->whereNotNull('cargo_nombre')->distinct()->count('cargo_nombre'),
            'ajustes_reales' => $trabajadoresAjustados->whereNotNull('centro_operativo_id')->count(),
            'participantes' => DB::table('descarga_contenedor_participantes')->whereNotNull('talana_trabajador_id')->distinct()->count('talana_trabajador_id'),
        ];

        return view('descarga_contenedores.dotacion', compact('trabajadores', 'participacion', 'centros', 'cargos', 'stats'));
    }

    public function storeTrabajadorOperacion(Request $request)
    {
        $this->authorizeCostManagement();

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'apellido_paterno' => ['nullable', 'string', 'max:120'],
            'apellido_materno' => ['nullable', 'string', 'max:120'],
            'rut' => ['nullable', 'string', 'max:30'],
            'cargo_nombre' => ['required', 'string', 'max:200'],
            'centro_costo_id' => ['nullable', 'exists:centros_costo,id'],
            'centro_operativo_id' => ['required', 'exists:centros_costo,id'],
        ]);

        $rut = $this->normalizeRutSearch($data['rut'] ?? null);
        if ($rut && TalanaTrabajador::where('rut', $rut)->exists()) {
            return back()
                ->withInput()
                ->with('error', 'Ya existe un trabajador con ese RUT en la dotación.');
        }

        $centroTalana = !empty($data['centro_costo_id'])
            ? CentroCosto::find($data['centro_costo_id'])
            : null;
        $centroOperativo = CentroCosto::findOrFail($data['centro_operativo_id']);

        TalanaTrabajador::create([
            'talana_id' => null,
            'rut' => $rut,
            'nombre' => $this->cleanText($data['nombre']),
            'apellido_paterno' => $this->cleanText($data['apellido_paterno'] ?? null),
            'apellido_materno' => $this->cleanText($data['apellido_materno'] ?? null),
            'cargo_nombre' => $this->cleanText($data['cargo_nombre']),
            'centro_costo_id' => $centroTalana?->id,
            'centro_costo_nombre' => $centroTalana?->nombre,
            'centro_operativo_id' => $centroOperativo->id,
            'centro_operativo_nombre' => $centroOperativo->nombre,
            'activo' => true,
            'origen' => 'manual_contenedores',
            'raw_payload' => [
                'source' => 'descarga_contenedores_dotacion',
                'created_by' => auth()->id(),
            ],
        ]);

        return back()->with('success', 'Trabajador agregado a la dotación de Contenedores.');
    }

    public function updateTrabajadorOperacion(Request $request, TalanaTrabajador $trabajador)
    {
        $this->authorizeCostManagement();

        $data = $request->validate([
            'centro_operativo_id' => ['nullable', 'exists:centros_costo,id'],
        ]);

        $centroOperativo = !empty($data['centro_operativo_id'])
            ? CentroCosto::find($data['centro_operativo_id'])
            : null;

        $trabajador->update([
            'centro_operativo_id' => $centroOperativo?->id,
            'centro_operativo_nombre' => $centroOperativo?->nombre,
        ]);

        return back()->with('success', 'Centro costo real actualizado.');
    }

    public function updateTrabajadoresOperacionBulk(Request $request)
    {
        $this->authorizeCostManagement();

        $data = $request->validate([
            'trabajadores' => ['required', 'array', 'min:1', 'max:200'],
            'trabajadores.*' => ['integer', 'distinct', 'exists:talana_trabajadores,id'],
            'centro_operativo_id' => ['nullable', 'exists:centros_costo,id'],
        ]);

        $centroOperativo = !empty($data['centro_operativo_id'])
            ? CentroCosto::find($data['centro_operativo_id'])
            : null;
        $trabajadorIds = collect($data['trabajadores'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        TalanaTrabajador::whereIn('id', $trabajadorIds)->update([
            'centro_operativo_id' => $centroOperativo?->id,
            'centro_operativo_nombre' => $centroOperativo?->nombre,
        ]);

        $accion = $centroOperativo
            ? "asignados a {$centroOperativo->nombre}"
            : 'configurados para usar el centro Talana';

        return back()->with('success', "{$trabajadorIds->count()} trabajadores {$accion}.");
    }

    public function liquidacion(Request $request)
    {
        $this->authorizeCostManagement();

        $base = $this->liquidacionQuery($request);
        $stats = [
            'trabajadores' => (clone $base)->whereNotNull('p.talana_trabajador_id')->distinct()->count('p.talana_trabajador_id'),
            'descargas' => (clone $base)->distinct()->count('d.id'),
            'participaciones' => (clone $base)->count(),
            'monto' => (clone $base)->sum('p.monto_calculado'),
        ];

        $liquidaciones = $this->liquidacionAgrupadaQuery($request)
            ->orderByDesc('monto_total')
            ->paginate(30)
            ->withQueryString();

        $centros = $this->centrosOperacion();
        $estadoSeleccionado = $this->selectedLiquidacionEstado($request);

        return view('descarga_contenedores.liquidacion', compact('liquidaciones', 'centros', 'stats', 'estadoSeleccionado'));
    }

    public function exportLiquidacion(Request $request)
    {
        $this->authorizeCostManagement();

        $rows = $this->liquidacionAgrupadaQuery($request)
            ->orderByDesc('monto_total')
            ->get();
        $filename = 'liquidacion-contenedores-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Trabajador',
                'RUT',
                'Cargo',
                'Centro',
                'Fecha desde',
                'Fecha hasta',
                'Descargas',
                'Participaciones',
                'Porcentaje total',
                'Monto total',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->nombre_snapshot,
                    $row->rut_snapshot,
                    $row->cargo_snapshot,
                    $row->centro_costo_snapshot,
                    $row->fecha_desde,
                    $row->fecha_hasta,
                    $row->descargas_count,
                    $row->participaciones_count,
                    number_format((float) $row->porcentaje_total, 2, ',', ''),
                    number_format((float) $row->monto_total, 2, ',', ''),
                ], ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reportes(Request $request)
    {
        $this->authorizeCostManagement();

        $query = DescargaContenedor::query();
        $this->applyReporteFilters($query, $request);

        $stats = [
            'registros' => (clone $query)->count(),
            'validadas' => (clone $query)->where('estado', 'validado')->count(),
            'cajas' => (clone $query)->sum('cajas'),
            'pallets' => (clone $query)->sum('pallets'),
            'costo' => (clone $query)->sum('costo_unitario_snapshot'),
            'pago' => (clone $query)->sum('pago_colaborador_snapshot'),
        ];

        $porOperacion = (clone $query)
            ->selectRaw("COALESCE(operacion, 'Sin operación') as nombre")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(COALESCE(cajas, 0)) as cajas')
            ->selectRaw('SUM(COALESCE(pallets, 0)) as pallets')
            ->selectRaw('SUM(COALESCE(costo_unitario_snapshot, 0)) as costo_total')
            ->selectRaw('SUM(COALESCE(pago_colaborador_snapshot, 0)) as pago_total')
            ->groupBy('operacion')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $porCentro = (clone $query)
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'descarga_contenedores.centro_costo_id')
            ->selectRaw("COALESCE(cc.nombre, descarga_contenedores.bodega, 'Sin centro') as nombre")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(COALESCE(descarga_contenedores.cajas, 0)) as cajas')
            ->selectRaw('SUM(COALESCE(descarga_contenedores.pago_colaborador_snapshot, 0)) as pago_total')
            ->groupBy('cc.nombre', 'descarga_contenedores.bodega')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $porFact = (clone $query)
            ->selectRaw("COALESCE(fact_codigo, 'Sin FACT') as codigo")
            ->selectRaw("COALESCE(tarifa_proceso_snapshot, 'Sin proceso') as proceso")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(COALESCE(cajas, 0)) as cajas')
            ->selectRaw('SUM(COALESCE(pago_colaborador_snapshot, 0)) as pago_total')
            ->groupBy('fact_codigo', 'tarifa_proceso_snapshot')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $porPeriodo = (clone $query)
            ->selectRaw("COALESCE(facturacion_mes, 'Sin periodo') as nombre")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(COALESCE(pago_colaborador_snapshot, 0)) as pago_total')
            ->groupBy('facturacion_mes')
            ->orderByDesc('total')
            ->limit(18)
            ->get();

        $centros = $this->centrosOperacion();
        $operaciones = DescargaContenedor::whereNotNull('operacion')
            ->distinct()
            ->orderBy('operacion')
            ->pluck('operacion');

        return view('descarga_contenedores.reportes', compact(
            'stats',
            'porOperacion',
            'porCentro',
            'porFact',
            'porPeriodo',
            'centros',
            'operaciones'
        ));
    }

    public function tarifas(Request $request)
    {
        $this->authorizeCostManagement();

        $query = DescargaContenedorTarifa::with('centroCosto');

        if ($request->filled('buscar')) {
            $term = trim($request->input('buscar'));
            $query->where(function ($q) use ($term) {
                $q->where('cliente', 'like', "%{$term}%")
                    ->orWhere('codigo', 'like', "%{$term}%")
                    ->orWhere('proceso', 'like', "%{$term}%")
                    ->orWhereHas('centroCosto', function ($centro) use ($term) {
                        $centro->where('nombre', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->input('centro_costo_id'));
        }

        if ($request->input('estado') === 'activos') {
            $query->where('activo', true);
        } elseif ($request->input('estado') === 'inactivos') {
            $query->where('activo', false);
        }

        $tarifas = $query->orderBy('cliente')
            ->orderBy('centro_costo_id')
            ->orderBy('codigo')
            ->orderBy('proceso')
            ->paginate(30)
            ->withQueryString();

        $centros = $this->centrosOperacion();

        return view('descarga_contenedores.tarifas', compact('tarifas', 'centros'));
    }

    public function storeTarifa(Request $request)
    {
        $this->authorizeCostManagement();

        $data = $this->validatedTarifa($request);
        DescargaContenedorTarifa::create($data);

        return redirect()
            ->route('descarga-contenedores.tarifas')
            ->with('success', 'Código FACT creado correctamente.');
    }

    public function updateTarifa(Request $request, DescargaContenedorTarifa $tarifa)
    {
        $this->authorizeCostManagement();

        $data = $this->validatedTarifa($request);
        $tarifa->update($data);

        return redirect()
            ->route('descarga-contenedores.tarifas', request()->only(['buscar', 'estado', 'page']))
            ->with('success', 'Código FACT actualizado correctamente.');
    }

    private function liquidacionQuery(Request $request)
    {
        $query = DB::table('descarga_contenedor_participantes as p')
            ->join('descarga_contenedores as d', 'd.id', '=', 'p.descarga_contenedor_id')
            ->whereNull('d.deleted_at');

        if ($request->filled('buscar')) {
            $term = trim($request->input('buscar'));
            $rutTerm = $this->normalizeRutSearch($term);
            $query->where(function ($q) use ($term, $rutTerm) {
                $q->where('p.nombre_snapshot', 'like', "%{$term}%")
                    ->orWhere('p.rut_snapshot', 'like', "%{$term}%")
                    ->orWhere('p.cargo_snapshot', 'like', "%{$term}%")
                    ->orWhere('d.contenedor', 'like', "%{$term}%")
                    ->orWhere('d.fact_codigo', 'like', "%{$term}%");

                if ($rutTerm) {
                    $q->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(p.rut_snapshot, '.', ''), '-', ''), ' ', '')) LIKE ?", ["%{$rutTerm}%"]);
                }
            });
        }

        if ($request->filled('centro_costo_id')) {
            $centroId = $request->input('centro_costo_id');
            $query->where(function ($q) use ($centroId) {
                $q->where('d.centro_costo_id', $centroId)
                    ->orWhere('p.centro_costo_id_snapshot', $centroId);
            });
        }

        $estado = $this->selectedLiquidacionEstado($request);
        if ($estado !== 'todos') {
            $query->where('d.estado', $estado);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('d.fecha', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('d.fecha', '<=', $request->input('fecha_hasta'));
        }

        return $query;
    }

    private function liquidacionAgrupadaQuery(Request $request)
    {
        return $this->liquidacionQuery($request)
            ->select([
                'p.talana_trabajador_id',
                'p.rut_snapshot',
                'p.nombre_snapshot',
                'p.cargo_snapshot',
                'p.centro_costo_snapshot',
            ])
            ->selectRaw('COUNT(DISTINCT d.id) as descargas_count')
            ->selectRaw('COUNT(*) as participaciones_count')
            ->selectRaw('SUM(COALESCE(p.porcentaje_participacion, 0)) as porcentaje_total')
            ->selectRaw('SUM(COALESCE(p.monto_calculado, 0)) as monto_total')
            ->selectRaw('MIN(d.fecha) as fecha_desde')
            ->selectRaw('MAX(d.fecha) as fecha_hasta')
            ->groupBy(
                'p.talana_trabajador_id',
                'p.rut_snapshot',
                'p.nombre_snapshot',
                'p.cargo_snapshot',
                'p.centro_costo_snapshot'
            );
    }

    private function selectedLiquidacionEstado(Request $request): string
    {
        $estado = $request->query('estado');

        return $estado === null || $estado === '' ? 'validado' : (string) $estado;
    }

    private function readyForValidationQuery()
    {
        $query = DescargaContenedor::query();
        $this->applyReadyForValidationFilter($query);

        return $query;
    }

    private function validationQueue(): array
    {
        $baseRelations = ['centroCosto', 'participantes'];
        $order = fn ($query) => $query
            ->orderByRaw('fecha IS NULL')
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        return [
            'ready' => $order($this->readyForValidationQuery()->with($baseRelations)->withCount('participantes'))
                ->limit(5)
                ->get(),
            'pending' => $order($this->pendingValidationQuery()->with($baseRelations)->withCount('participantes'))
                ->limit(5)
                ->get(),
        ];
    }

    private function pendingValidationQuery()
    {
        $query = DescargaContenedor::query();
        $this->applyPendingValidationFilter($query);

        return $query;
    }

    private function applyReadyForValidationFilter($query): void
    {
        $query->where('estado', 'borrador')
            ->whereNotNull('fecha')
            ->whereNotNull('contenedor')
            ->where('contenedor', '<>', '')
            ->where(function ($q) {
                $q->whereNotNull('centro_costo_id')
                    ->orWhere(function ($bodega) {
                        $bodega->whereNotNull('bodega')
                            ->where('bodega', '<>', '');
                    });
            })
            ->whereNotNull('fact_codigo')
            ->where('fact_codigo', '<>', '')
            ->whereNotNull('tarifa_id')
            ->whereNotNull('pago_colaborador_snapshot')
            ->where(function ($q) {
                $q->where('requiere_revision_tarifa', false)
                    ->orWhereNull('requiere_revision_tarifa');
            })
            ->has('participantes')
            ->whereRaw('(select ROUND(COALESCE(SUM(porcentaje_participacion), 0), 2) from descarga_contenedor_participantes where descarga_contenedor_id = descarga_contenedores.id) = 100');
    }

    private function applyPendingValidationFilter($query): void
    {
        $query->where('estado', 'borrador')
            ->where(function ($q) {
                $q->whereNull('fecha')
                    ->orWhereNull('contenedor')
                    ->orWhere('contenedor', '')
                    ->orWhere(function ($centro) {
                        $centro->whereNull('centro_costo_id')
                            ->where(function ($bodega) {
                                $bodega->whereNull('bodega')
                                    ->orWhere('bodega', '');
                            });
                    })
                    ->orWhereNull('fact_codigo')
                    ->orWhere('fact_codigo', '')
                    ->orWhereNull('tarifa_id')
                    ->orWhereNull('pago_colaborador_snapshot')
                    ->orWhere('requiere_revision_tarifa', true)
                    ->orWhereDoesntHave('participantes')
                    ->orWhereRaw('(select ROUND(COALESCE(SUM(porcentaje_participacion), 0), 2) from descarga_contenedor_participantes where descarga_contenedor_id = descarga_contenedores.id) <> 100');
            });
    }

    private function applyReporteFilters($query, Request $request): void
    {
        if ($request->filled('buscar')) {
            $term = trim($request->input('buscar'));
            $query->where(function ($q) use ($term) {
                $q->where('contenedor', 'like', "%{$term}%")
                    ->orWhere('bodega', 'like', "%{$term}%")
                    ->orWhere('producto', 'like', "%{$term}%")
                    ->orWhere('fact_codigo', 'like', "%{$term}%")
                    ->orWhere('tarifa_proceso_snapshot', 'like', "%{$term}%");
            });
        }

        if ($request->filled('operacion')) {
            $query->where('operacion', $request->input('operacion'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->input('centro_costo_id'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->input('fecha_hasta'));
        }
    }

    private function puedeGestionarCostos(): bool
    {
        return auth()->user()?->puedeGestionarCostosDescargaContenedores() ?? false;
    }

    private function authorizeCostManagement(): void
    {
        abort_unless($this->puedeGestionarCostos(), 403);
    }

    private function ensureEditable(DescargaContenedor $descarga): void
    {
        abort_unless(auth()->user()?->puedeEditarDescargaContenedor($descarga), 403);
        abort_if($descarga->estado === 'liquidado', 403, 'No se puede editar un registro liquidado.');
    }

    private function listPerPage(Request $request): int
    {
        $allowed = [20, 50, 100, 200];
        $value = (int) $request->input('per_page', 20);

        return in_array($value, $allowed, true) ? $value : 20;
    }

    private function redirectToListPanel(DescargaContenedor $descarga, string $mode = 'detail')
    {
        return redirect()->route('descarga-contenedores.index', [
            'abrir' => $descarga->id,
            'modo' => $mode === 'edit' ? 'edit' : 'detail',
        ]);
    }

    private function canQuickEdit(DescargaContenedor $descarga): bool
    {
        return (bool) auth()->user()?->puedeEditarDescargaContenedor($descarga)
            && $descarga->estado !== 'liquidado';
    }

    private function visibleBlockers(DescargaContenedor $descarga)
    {
        $blockers = $descarga->validationBlockers();
        if ($this->puedeGestionarCostos()) {
            return $blockers->values();
        }

        return $blockers
            ->map(fn ($blocker) => match ($blocker) {
                'falta pago colaborador' => 'tarifa FACT pendiente',
                'tarifa pendiente de revisión' => 'tarifa FACT por revisar',
                default => $blocker,
            })
            ->values();
    }

    private function listRowPayload(DescargaContenedor $descarga, string $message): array
    {
        $descarga->loadMissing(['participantes', 'tarifa.centroCosto', 'centroCosto']);
        $blockers = $descarga->validationBlockers();

        return [
            'ok' => true,
            'message' => $message,
            'row' => [
                'id' => $descarga->id,
                'participantes_count' => $descarga->participantes->count(),
                'fact_codigo' => $descarga->fact_codigo,
                'tarifa_cliente' => $descarga->tarifa_cliente_snapshot,
                'tarifa_proceso' => $descarga->tarifa_proceso_snapshot,
                'pago' => $descarga->pago_colaborador_snapshot,
                'requiere_revision_tarifa' => (bool) $descarga->requiere_revision_tarifa,
                'blockers' => $this->visibleBlockers($descarga)->all(),
                'can_validate' => $descarga->estado === 'borrador' && $blockers->isEmpty(),
                'estado' => $descarga->estado,
                'fecha' => optional($descarga->fecha)->format('d/m/Y'),
                'contenedor' => $descarga->contenedor,
                'operacion' => $descarga->operacion,
                'bodega' => $descarga->bodega ?: ($descarga->centroCosto->nombre ?? ''),
                'producto' => $descarga->producto,
                'equipo_descarga' => $descarga->equipo_descarga,
                'cajas' => $descarga->cajas,
            ],
        ];
    }

    private function ensureContainerEvidence(ArchivoAdjunto $archivo, ?DescargaContenedor $descarga = null): void
    {
        abort_unless($archivo->entidad_tipo === 'descarga_contenedor', 404);

        if ($descarga) {
            abort_unless((int) $archivo->entidad_id === (int) $descarga->id, 404);
        }
    }

    private function centrosOperacion()
    {
        $centros = $this->centrosByKeywords($this->descargaOperacionCenterKeywords());

        return $centros->isNotEmpty()
            ? $centros
            : CentroCosto::where('activo', true)->orderBy('nombre')->get();
    }

    private function centrosDotacion()
    {
        $centros = $this->centrosByKeywords($this->descargaDotacionCenterKeywords());

        return $centros->isNotEmpty()
            ? $centros
            : CentroCosto::where('activo', true)->orderBy('nombre')->get();
    }

    private function centrosByKeywords(array $keywords)
    {
        $codigos = collect(config('descarga_contenedores.centros_talana', []))
            ->map(fn ($codigo) => strtoupper(trim((string) $codigo)))
            ->filter()
            ->values()
            ->all();

        $query = CentroCosto::where('activo', true)
            ->where(function ($q) use ($keywords, $codigos) {
                if ($codigos) {
                    // Código oficial Talana (preferido) o código local igual al de Talana.
                    $q->whereIn(DB::raw('UPPER(codigo)'), $codigos);
                    if ($this->centrosTienenVinculoTalana()) {
                        $q->orWhereIn('codigo_talana', $codigos);
                    }
                }
                $q->orWhere(fn ($nombre) => $this->applyKeywordFilter($nombre, 'nombre', $keywords));
            });

        return $query->orderBy('nombre')->get();
    }

    private function centrosTienenVinculoTalana(): bool
    {
        static $tiene = null;

        return $tiene ??= Schema::hasColumn('centros_costo', 'codigo_talana');
    }

    private function applyTrabajadoresDotacionFilter($query): void
    {
        $centros = $this->centrosByKeywords($this->descargaDotacionCenterKeywords());
        $hasDotacionByName = TalanaTrabajador::query()
            ->where(function ($q) {
                $this->applyKeywordFilter($q, 'centro_costo_nombre', $this->descargaDotacionCenterKeywords());
                $q->orWhere(function ($operativo) {
                    $this->applyKeywordFilter($operativo, 'centro_operativo_nombre', $this->descargaDotacionCenterKeywords());
                });
            })
            ->exists();

        if ($centros->isEmpty() && !$hasDotacionByName) {
            return;
        }

        $query->where(function ($q) use ($centros) {
            if ($centros->isNotEmpty()) {
                $q->whereIn('centro_costo_id', $centros->pluck('id'))
                    ->orWhereIn('centro_operativo_id', $centros->pluck('id'));
            }

            $q->orWhere(function ($nameQuery) {
                $this->applyKeywordFilter($nameQuery, 'centro_costo_nombre', $this->descargaDotacionCenterKeywords());
            })->orWhere(function ($nameQuery) {
                $this->applyKeywordFilter($nameQuery, 'centro_operativo_nombre', $this->descargaDotacionCenterKeywords());
            });
        })->where(function ($cargoQuery) {
            $this->applyKeywordFilter($cargoQuery, 'cargo_nombre', $this->descargaDotacionCargoKeywords());

            $cargoQuery->orWhereHas('cargo', function ($cargo) {
                $this->applyKeywordFilter($cargo, 'nombre', $this->descargaDotacionCargoKeywords());
            });
        });
    }

    private function applyTrabajadorCentroFilter($query, int $centroId): void
    {
        $query->where(function ($q) use ($centroId) {
            $q->where('centro_operativo_id', $centroId)
                ->orWhere(function ($talana) use ($centroId) {
                    $talana->whereNull('centro_operativo_id')
                        ->where('centro_costo_id', $centroId);
                });
        });
    }

    private function normalizeRutSearch($value): ?string
    {
        $value = $this->cleanText($value);
        if (!$value) {
            return null;
        }

        $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $value));

        return strlen($clean) >= 2 ? $clean : null;
    }

    private function applyKeywordFilter($query, string $column, array $keywords): void
    {
        $query->where(function ($q) use ($column, $keywords) {
            foreach ($keywords as $keyword) {
                $q->orWhere($column, 'like', '%' . $keyword . '%');
            }
        });
    }

    private function descargaDotacionCenterKeywords(): array
    {
        return (array) config('descarga_contenedores.centros', []);
    }

    private function descargaDotacionCargoKeywords(): array
    {
        return (array) config('descarga_contenedores.cargos', []);
    }

    private function descargaOperacionCenterKeywords(): array
    {
        return array_values(array_unique(array_merge(
            $this->descargaDotacionCenterKeywords(),
            (array) config('descarga_contenedores.centros_operacion_extra', [])
        )));
    }

    private function formData(?DescargaContenedor $descarga = null): array
    {
        $centros = $this->centrosOperacion();
        if ($descarga?->centro_costo_id && !$centros->contains('id', $descarga->centro_costo_id)) {
            $centroActual = CentroCosto::find($descarga->centro_costo_id);
            if ($centroActual) {
                $centros = $centros->push($centroActual)->unique('id')->sortBy('nombre')->values();
            }
        }

        $tarifas = DescargaContenedorTarifa::with('centroCosto')
            ->where('activo', true)
            ->orderBy('cliente')
            ->orderBy('centro_costo_id')
            ->orderBy('codigo')
            ->get();

        if ($descarga?->tarifa_id && !$tarifas->contains('id', $descarga->tarifa_id)) {
            $tarifaActual = DescargaContenedorTarifa::with('centroCosto')->find($descarga->tarifa_id);
            if ($tarifaActual) {
                $tarifas = $tarifas->push($tarifaActual)->unique('id')->sortBy([
                    ['cliente', 'asc'],
                    ['centro_costo_id', 'asc'],
                    ['codigo', 'asc'],
                ])->values();
            }
        }

        return [
            'centros' => $centros,
            'tarifas' => $tarifas,
            'trabajadores' => $this->trabajadoresSelector($descarga),
            'supervisorSistema' => auth()->user()?->loadMissing(['cargo', 'centroCosto']),
        ];
    }

    private function trabajadoresSelector(?DescargaContenedor $descarga = null)
    {
        $selectedIds = $descarga?->participantes
            ?->pluck('talana_trabajador_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all() ?? [];

        $query = TalanaTrabajador::with(['cargo', 'centroCosto', 'centroOperativo'])
            ->where(function ($q) use ($selectedIds) {
                $q->where(function ($base) {
                    $base->where('activo', true);
                    $this->applyTrabajadoresDotacionFilter($base);
                });

                if ($selectedIds) {
                    $q->orWhereIn('id', $selectedIds);
                }
            });

        return $query->orderBy('nombre')
            ->orderBy('apellido_paterno')
            ->get()
            ->map(fn (TalanaTrabajador $trabajador) => [
                'id' => $trabajador->id,
                'label' => $trabajador->nombre_completo ?: $trabajador->nombre,
                'rut' => $trabajador->rut,
                'cargo_id' => $trabajador->cargo_id,
                'cargo' => $trabajador->cargo?->nombre ?: $trabajador->cargo_nombre,
                'centro' => $trabajador->centroDescargaNombre(),
                'centro_costo_id' => $trabajador->centroDescargaId(),
                'centro_talana' => $trabajador->centroCosto?->nombre ?: $trabajador->centro_costo_nombre,
                'centro_talana_id' => $trabajador->centro_costo_id,
                'centro_operativo' => $trabajador->centroOperativo?->nombre ?: $trabajador->centro_operativo_nombre,
                'centro_operativo_id' => $trabajador->centro_operativo_id,
                'origen' => $trabajador->origen,
            ])
            ->values();
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'operacion' => ['nullable', 'string', 'max:120'],
            'centro_costo_id' => ['nullable', 'exists:centros_costo,id'],
            'bodega' => ['nullable', 'string', 'max:160'],
            'supervisor_nombre' => ['nullable', 'string', 'max:200'],
            'facturacion_mes' => ['nullable', 'string', 'max:60'],
            'fecha' => ['nullable', 'date'],
            'contenedor' => ['nullable', 'string', 'max:120'],
            'equipo_descarga' => ['nullable', 'string', 'max:120'],
            'hora_cita' => ['nullable'],
            'hora_inicio_descarga' => ['nullable'],
            'hora_termino_descarga' => ['nullable'],
            'item' => ['nullable', 'integer', 'min:0'],
            'cajas' => ['nullable', 'integer', 'min:0'],
            'pallets' => ['nullable', 'numeric', 'min:0'],
            'producto' => ['nullable', 'string', 'max:260'],
            'tarifa_id' => ['nullable', 'exists:descarga_contenedor_tarifas,id'],
            'fact_codigo' => ['nullable', 'string', 'max:40'],
            'observacion' => ['nullable', 'string'],
            'evidencias' => ['nullable', 'array', 'max:8'],
            'evidencias.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        unset($data['evidencias']);

        foreach (['hora_cita', 'hora_inicio_descarga', 'hora_termino_descarga'] as $field) {
            $data[$field] = $this->normalizeTime($data[$field] ?? null);
        }

        $data['fact_codigo'] = $this->cleanUpper($data['fact_codigo'] ?? null);

        return $data;
    }

    private function storeEvidencias(DescargaContenedor $descarga, Request $request): void
    {
        if (!$request->hasFile('evidencias')) {
            return;
        }

        foreach ((array) $request->file('evidencias') as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
            $filename = (string) Str::uuid() . '.' . $extension;
            $directory = 'descarga_contenedores/' . $descarga->id . '/evidencias';
            $path = $file->storeAs($directory, $filename, 'local');

            $descarga->evidencias()->create([
                'entidad_tipo' => 'descarga_contenedor',
                'nombre_original' => $file->getClientOriginalName(),
                'nombre_archivo' => $filename,
                'ruta' => $path,
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'tamanio' => $file->getSize() ?: 0,
                'campo_formulario' => 'evidencias',
                'subido_por' => auth()->id(),
            ]);
        }
    }

    private function validatedTarifa(Request $request): array
    {
        $data = $request->validate([
            'cliente' => ['required', 'string', 'max:80'],
            'centro_costo_id' => ['nullable', 'exists:centros_costo,id'],
            'codigo' => ['required', 'string', 'max:40'],
            'proceso' => ['required', 'string', 'max:180'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'pago_colaborador' => ['nullable', 'numeric', 'min:0'],
            'requiere_revision' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:400'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['cliente'] = mb_strtoupper(trim($data['cliente']));
        $data['centro_costo_id'] = $this->nullableInt($data['centro_costo_id'] ?? null);
        $data['codigo'] = $this->cleanUpper($data['codigo']);
        $data['proceso'] = trim($data['proceso']);
        $data['requiere_revision'] = (bool) ($data['requiere_revision'] ?? false);
        $data['activo'] = (bool) ($data['activo'] ?? false);

        return $data;
    }

    /**
     * Resuelve la tarifa del registro y copia sus valores (snapshot).
     *
     * $current: registro existente. Si ya salió de borrador y mantiene la misma
     * tarifa, se conserva el snapshot congelado al validar en vez de tomar los
     * valores vigentes de la tarifa.
     * $tarifasActivas: catálogo precargado para evitar una consulta por registro.
     */
    private function applyTarifaSnapshot(array &$data, ?DescargaContenedor $current = null, $tarifasActivas = null): void
    {
        $tarifa = null;

        if (!empty($data['tarifa_id'])) {
            $tarifa = $tarifasActivas?->firstWhere('id', (int) $data['tarifa_id'])
                ?? DescargaContenedorTarifa::find($data['tarifa_id']);
        }

        if (!$tarifa && !empty($data['fact_codigo'])) {
            $tarifasCoincidentes = $tarifasActivas !== null
                ? $tarifasActivas
                    ->filter(fn (DescargaContenedorTarifa $item) => (string) $item->codigo === (string) $data['fact_codigo'])
                    ->sortBy([['cliente', 'asc'], ['centro_costo_id', 'asc'], ['id', 'asc']])
                    ->values()
                : DescargaContenedorTarifa::where('activo', true)
                    ->where('codigo', $data['fact_codigo'])
                    ->orderBy('cliente')
                    ->orderBy('centro_costo_id')
                    ->orderBy('id')
                    ->get();

            $cliente = $this->clienteFromOperacion($data['operacion'] ?? null)
                ?? $this->clienteFromOperacion($data['bodega'] ?? null)
                ?? $this->clienteFromOperacion(!empty($data['centro_costo_id']) ? CentroCosto::whereKey($data['centro_costo_id'])->value('nombre') : null);
            if ($cliente) {
                $porCliente = $tarifasCoincidentes
                    ->filter(fn (DescargaContenedorTarifa $item) => strtoupper((string) $item->cliente) === $cliente)
                    ->values();
                if ($porCliente->isNotEmpty()) {
                    $tarifasCoincidentes = $porCliente;
                }
            }

            $centroId = $this->nullableInt($data['centro_costo_id'] ?? null);
            if ($centroId) {
                $tarifasDelCentro = $tarifasCoincidentes
                    ->where('centro_costo_id', $centroId)
                    ->values();

                if ($tarifasDelCentro->count() === 1) {
                    $tarifa = $tarifasDelCentro->first();
                }
            }

            if (!$tarifa) {
                $tarifasGenerales = $tarifasCoincidentes
                    ->whereNull('centro_costo_id')
                    ->values();

                if ($tarifasGenerales->count() === 1) {
                    $tarifa = $tarifasGenerales->first();
                } elseif ($tarifasCoincidentes->count() === 1) {
                    $tarifa = $tarifasCoincidentes->first();
                }
            }
        }

        if (!$tarifa) {
            $data['tarifa_id'] = null;
            $data['tarifa_cliente_snapshot'] = null;
            $data['tarifa_proceso_snapshot'] = null;
            $data['costo_unitario_snapshot'] = null;
            $data['pago_colaborador_snapshot'] = null;
            $data['requiere_revision_tarifa'] = false;
            return;
        }

        if ($current
            && $current->estado !== 'borrador'
            && (int) $current->tarifa_id === (int) $tarifa->id
        ) {
            $data['tarifa_id'] = $current->tarifa_id;
            $data['fact_codigo'] = $current->fact_codigo ?: $tarifa->codigo;
            $data['tarifa_cliente_snapshot'] = $current->tarifa_cliente_snapshot;
            $data['tarifa_proceso_snapshot'] = $current->tarifa_proceso_snapshot;
            $data['costo_unitario_snapshot'] = $current->costo_unitario_snapshot;
            $data['pago_colaborador_snapshot'] = $current->pago_colaborador_snapshot;
            $data['requiere_revision_tarifa'] = (bool) $current->requiere_revision_tarifa;
            return;
        }

        $data['tarifa_id'] = $tarifa->id;
        $data['fact_codigo'] = $tarifa->codigo;
        $data['tarifa_cliente_snapshot'] = $tarifa->cliente;
        $data['tarifa_proceso_snapshot'] = $tarifa->proceso;
        $data['costo_unitario_snapshot'] = $tarifa->costo_unitario;
        $data['pago_colaborador_snapshot'] = $tarifa->pago_colaborador;
        $data['requiere_revision_tarifa'] = $tarifa->requiere_revision;
    }

    private function associateMissingUniqueTarifas(): void
    {
        $pendientes = DescargaContenedor::query()
            ->where('estado', 'borrador')
            ->whereNull('tarifa_id')
            ->whereNotNull('fact_codigo')
            ->where('fact_codigo', '<>', '')
            ->orderBy('id')
            ->limit(200)
            ->get();

        if ($pendientes->isEmpty()) {
            return;
        }

        $tarifasActivas = DescargaContenedorTarifa::where('activo', true)
            ->whereIn('codigo', $pendientes->pluck('fact_codigo')->unique()->values())
            ->get();

        if ($tarifasActivas->isEmpty()) {
            return;
        }

        $pendientes->each(fn (DescargaContenedor $descarga) => $this->associateUniqueTarifaIfMissing($descarga, $tarifasActivas));
    }

    private function associateUniqueTarifaIfMissing(DescargaContenedor $descarga, $tarifasActivas = null): bool
    {
        if ($descarga->tarifa_id || $descarga->estado === 'liquidado' || blank($descarga->fact_codigo)) {
            return false;
        }

        $data = [
            'tarifa_id' => null,
            'fact_codigo' => $descarga->fact_codigo,
            'operacion' => $descarga->operacion,
            'centro_costo_id' => $descarga->centro_costo_id,
        ];
        $this->applyTarifaSnapshot($data, null, $tarifasActivas);

        if (empty($data['tarifa_id'])) {
            return false;
        }

        $descarga->forceFill([
            'tarifa_id' => $data['tarifa_id'],
            'fact_codigo' => $data['fact_codigo'],
            'tarifa_cliente_snapshot' => $data['tarifa_cliente_snapshot'],
            'tarifa_proceso_snapshot' => $data['tarifa_proceso_snapshot'],
            'costo_unitario_snapshot' => $data['costo_unitario_snapshot'],
            'pago_colaborador_snapshot' => $data['pago_colaborador_snapshot'],
            'requiere_revision_tarifa' => $data['requiere_revision_tarifa'],
        ])->save();

        // El equipo pudo asignarse antes de existir la tarifa: sus montos quedaron en null.
        $this->recalcularMontosParticipantes($descarga);

        return true;
    }

    private function syncOrRecalculateParticipantes(DescargaContenedor $descarga, Request $request): void
    {
        if ($request->exists('participantes_json')) {
            $this->syncParticipantes($descarga, $this->extractParticipantesFromRequest($request));
            return;
        }

        $this->recalcularMontosParticipantes($descarga);
    }

    /**
     * Recalcula monto_calculado de cada participante desde el pago colaborador
     * congelado en el registro y su porcentaje de participación.
     */
    private function recalcularMontosParticipantes(DescargaContenedor $descarga): void
    {
        $pagoTotal = $this->pagoTotalColaboradores($descarga);

        $descarga->participantes()->get()->each(function ($participante) use ($pagoTotal) {
            $monto = $pagoTotal !== null
                ? round($pagoTotal * (float) $participante->porcentaje_participacion / 100, 2)
                : null;

            $actual = $participante->monto_calculado;
            $cambio = $monto === null
                ? $actual !== null
                : $actual === null || abs((float) $actual - $monto) > 0.004;

            if ($cambio) {
                $participante->update(['monto_calculado' => $monto]);
            }
        });

        $descarga->unsetRelation('participantes');
    }

    private function normalizeBulkRow(array $row): array
    {
        return [
            'operacion' => $this->cleanText($row['operacion'] ?? null),
            'centro_costo_id' => $this->nullableInt($row['centro_costo_id'] ?? null),
            'bodega' => $this->cleanText($row['bodega'] ?? null),
            'supervisor_nombre' => $this->cleanText($row['supervisor_nombre'] ?? null),
            'facturacion_mes' => $this->cleanText($row['facturacion_mes'] ?? null),
            'fecha' => $this->normalizeDate($row['fecha'] ?? null),
            'contenedor' => $this->cleanText($row['contenedor'] ?? null),
            'equipo_descarga' => $this->cleanText($row['equipo_descarga'] ?? null),
            'hora_cita' => $this->normalizeTime($row['hora_cita'] ?? null),
            'hora_inicio_descarga' => $this->normalizeTime($row['hora_inicio_descarga'] ?? null),
            'hora_termino_descarga' => $this->normalizeTime($row['hora_termino_descarga'] ?? null),
            'item' => $this->nullableInt($row['item'] ?? null),
            'cajas' => $this->nullableInt($row['cajas'] ?? null),
            'pallets' => $this->nullableDecimal($row['pallets'] ?? null),
            'producto' => $this->cleanText($row['producto'] ?? null),
            'tarifa_id' => $this->nullableInt($row['tarifa_id'] ?? null),
            'fact_codigo' => $this->cleanUpper($row['fact_codigo'] ?? null),
            'observacion' => $this->cleanText($row['observacion'] ?? null),
        ];
    }

    /**
     * Evita que una fila con datos fuera de rango haga fallar toda la tanda:
     * IDs inexistentes quedan en null y los textos se recortan al largo de la columna.
     */
    private function sanitizeBulkRow(array $data, $centrosValidos, $tarifasValidas): array
    {
        if ($data['centro_costo_id'] !== null && !$centrosValidos->has($data['centro_costo_id'])) {
            $data['centro_costo_id'] = null;
        }

        if ($data['tarifa_id'] !== null && !$tarifasValidas->has($data['tarifa_id'])) {
            $data['tarifa_id'] = null;
        }

        $limites = [
            'operacion' => 120,
            'bodega' => 160,
            'supervisor_nombre' => 200,
            'facturacion_mes' => 60,
            'contenedor' => 120,
            'equipo_descarga' => 120,
            'producto' => 260,
            'fact_codigo' => 40,
        ];

        foreach ($limites as $campo => $max) {
            if ($data[$campo] !== null) {
                $data[$campo] = mb_substr($data[$campo], 0, $max);
            }
        }

        foreach (['item', 'cajas'] as $campo) {
            if ($data[$campo] !== null && ($data[$campo] < 0 || $data[$campo] > 2147483647)) {
                $data[$campo] = null;
            }
        }

        if ($data['pallets'] !== null && ($data['pallets'] < 0 || $data['pallets'] >= 100000000)) {
            $data['pallets'] = null;
        }

        return $data;
    }

    private function syncParticipantes(DescargaContenedor $descarga, array $participantesPayload): void
    {
        $participantes = $this->normalizeParticipantesPayload($participantesPayload);
        $trabajadorIds = $participantes->pluck('id')
            ->values();
        $existingWorkerIds = $descarga->participantes()
            ->whereNotNull('talana_trabajador_id')
            ->pluck('talana_trabajador_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $descarga->participantes()->delete();

        if ($trabajadorIds->isEmpty()) {
            return;
        }

        $trabajadoresQuery = TalanaTrabajador::with(['cargo', 'centroCosto', 'centroOperativo'])
            ->whereIn('id', $trabajadorIds)
            ->where(function ($q) use ($existingWorkerIds) {
                $q->where(function ($allowed) {
                    $this->applyTrabajadoresDotacionFilter($allowed);
                });

                if ($existingWorkerIds) {
                    $q->orWhereIn('id', $existingWorkerIds);
                }
            });
        $trabajadores = $trabajadoresQuery->get()->keyBy('id');

        $pagoTotal = $this->pagoTotalColaboradores($descarga);

        foreach ($participantes as $participanteData) {
            $trabajadorId = $participanteData['id'];
            $porcentaje = $participanteData['porcentaje'];
            $trabajador = $trabajadores->get($trabajadorId);
            if (!$trabajador) {
                continue;
            }

            $monto = $pagoTotal !== null
                ? round($pagoTotal * $porcentaje / 100, 2)
                : null;

            $descarga->participantes()->create([
                'talana_trabajador_id' => $trabajador->id,
                'user_id' => null,
                'nombre_snapshot' => $trabajador->nombre_completo ?: $trabajador->nombre,
                'rut_snapshot' => $trabajador->rut,
                'cargo_snapshot' => $trabajador->cargo?->nombre ?: $trabajador->cargo_nombre,
                'centro_costo_id_snapshot' => $trabajador->centroDescargaId(),
                'centro_costo_snapshot' => $trabajador->centroDescargaNombre(),
                'rol_en_descarga' => 'descargador',
                'porcentaje_participacion' => $porcentaje,
                'monto_calculado' => $monto,
            ]);
        }
    }

    private function extractParticipantesFromRequest(Request $request): array
    {
        $json = json_decode((string) $request->input('participantes_json'), true);

        return is_array($json) ? $json : [];
    }

    private function extractParticipantesFromRow(array $row): array
    {
        $ids = $row['participantes'] ?? [];

        if (is_string($ids)) {
            $ids = json_decode($ids, true);
        }

        return is_array($ids) ? $ids : [];
    }

    private function normalizeParticipantesPayload(array $payload)
    {
        $items = collect($payload)
            ->map(function ($item) {
                if (is_array($item)) {
                    $id = $item['id'] ?? $item['trabajador_id'] ?? $item['talana_trabajador_id'] ?? null;
                    $porcentaje = $item['porcentaje'] ?? $item['porcentaje_participacion'] ?? null;
                } else {
                    $id = $item;
                    $porcentaje = null;
                }

                $id = (int) $id;
                if ($id <= 0) {
                    return null;
                }

                return [
                    'id' => $id,
                    'porcentaje' => $porcentaje !== null && $porcentaje !== ''
                        ? max(0, (float) str_replace(',', '.', (string) $porcentaje))
                        : null,
                    'porcentaje_informado' => $porcentaje !== null && $porcentaje !== '',
                ];
            })
            ->filter()
            ->unique('id')
            ->values();

        if ($items->isEmpty()) {
            return $items;
        }

        $hasExplicitPercentages = $items->contains(fn ($item) => $item['porcentaje_informado']);
        if (!$hasExplicitPercentages) {
            return $this->distribuirIgual($items->pluck('id')->all());
        }

        return $items->map(function ($item) {
            return [
                'id' => $item['id'],
                'porcentaje' => round((float) ($item['porcentaje'] ?? 0), 2),
            ];
        });
    }

    private function distribuirIgual(array $ids)
    {
        $ids = collect($ids)->filter()->unique()->values();
        $count = $ids->count();

        if ($count === 0) {
            return collect();
        }

        $base = round(100 / $count, 2);
        $assigned = 0.0;

        return $ids->map(function ($id, $index) use ($count, $base, &$assigned) {
            if ($index === $count - 1) {
                $porcentaje = round(100 - $assigned, 2);
            } else {
                $porcentaje = $base;
                $assigned += $porcentaje;
            }

            return [
                'id' => (int) $id,
                'porcentaje' => $porcentaje,
            ];
        });
    }

    private function pagoTotalColaboradores(DescargaContenedor $descarga): ?float
    {
        if ($descarga->requiere_revision_tarifa) {
            return null;
        }

        return $descarga->pago_colaborador_snapshot !== null
            ? (float) $descarga->pago_colaborador_snapshot
            : null;
    }

    private function stampSupervisorFromLogin(array &$data): void
    {
        $user = auth()->user();
        $data['supervisor_id'] = $user?->id;

        if (blank($data['supervisor_nombre'] ?? null) && $user) {
            $data['supervisor_nombre'] = $user->nombre_completo ?: $user->name;
        }
    }

    private function stampValidation(array &$data): void
    {
        if (($data['estado'] ?? null) === 'validado' && empty($data['validado_at'])) {
            $data['validado_por'] = auth()->id();
            $data['validado_at'] = now();
        }

        if (($data['estado'] ?? null) !== 'validado') {
            $data['validado_por'] = null;
            $data['validado_at'] = null;
        }

        if (($data['estado'] ?? null) !== 'liquidado') {
            $data['liquidado_por'] = null;
            $data['liquidado_at'] = null;
        }
    }

    private function rowIsEmpty(array $row): bool
    {
        $fields = [
            'operacion', 'bodega', 'supervisor_nombre', 'facturacion_mes', 'fecha',
            'contenedor', 'equipo_descarga', 'hora_cita', 'hora_inicio_descarga',
            'hora_termino_descarga', 'item', 'cajas', 'pallets', 'producto',
            'observacion', 'fact_codigo',
        ];

        foreach ($fields as $field) {
            if (filled($row[$field] ?? null)) {
                return false;
            }
        }

        return empty($this->extractParticipantesFromRow($row));
    }

    private function normalizeDate($value): ?string
    {
        $value = $this->cleanText($value);
        if (!$value) {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d', 'd.m.Y', 'Y.m.d'];

        foreach ($formats as $format) {
            $date = $this->strictDate($format, $value);
            if ($date) {
                return $date;
            }
        }

        // Formatos libres ("10 nov 2026"); se descartan fechas numéricas imposibles.
        if (preg_match('/^\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4}$/', $value)) {
            return null;
        }

        // Una hora suelta ("8:30:00") o un número no son fechas: Carbon los tomaría como "hoy".
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?(\s*[ap]\.?\s?m\.?)?$/i', $value) || preg_match('/^\d+([.,]\d+)?$/', $value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * createFromFormat sin desbordes: "31/02/2026" no se convierte en 3 de marzo.
     */
    private function strictDate(string $format, string $value): ?string
    {
        $date = \DateTime::createFromFormat('!' . $format, $value);
        $errors = \DateTime::getLastErrors();

        if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function normalizeEstado($value): string
    {
        $value = $this->cleanText($value) ?: 'borrador';

        return in_array($value, ['borrador', 'validado', 'cerrado', 'liquidado'], true)
            ? $value
            : 'borrador';
    }

    private function normalizeTime($value): ?string
    {
        $value = $this->cleanText($value);
        if (!$value) {
            return null;
        }

        $value = str_replace('.', ':', $value);
        $formats = ['H:i:s', 'H:i', 'G:i', 'Hi'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('H:i:s');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatHoraCorta($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return substr($value, 0, 5);
    }

    private function nullableInt($value): ?int
    {
        $value = $this->cleanText($value);
        if ($value === null || $value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9-]/', '', $value);

        return $value === '' ? null : (int) $value;
    }

    private function nullableDecimal($value): ?float
    {
        $value = $this->cleanText($value);
        if ($value === null || $value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        $value = preg_replace('/[^0-9.-]/', '', $value);

        return $value === '' ? null : (float) $value;
    }

    private function clienteFromOperacion($operacion): ?string
    {
        $value = mb_strtoupper($this->cleanText($operacion) ?? '');
        if ($value === '') {
            return null;
        }

        $value = strtr($value, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);

        if (str_contains($value, 'SMU') || str_contains($value, 'UNIMARC')) {
            return 'SMU';
        }

        if (str_contains($value, 'WALMART') || $value === 'WM' || str_starts_with($value, 'WM ')) {
            return 'WM';
        }

        if (str_contains($value, 'MAERSK')) {
            return 'MAERSK';
        }

        return null;
    }

    private function containerDateKey($contenedor, $fecha): ?string
    {
        $contenedor = $this->cleanUpper($contenedor);
        if (!$contenedor || !$fecha) {
            return null;
        }

        return $contenedor.'|'.$fecha;
    }

    private function existingContainerDateKeys(?array $contenedores = null): array
    {
        if ($contenedores === []) {
            return [];
        }

        return DescargaContenedor::query()
            ->whereNotNull('contenedor')
            ->where('contenedor', '<>', '')
            ->whereNotNull('fecha')
            ->when($contenedores !== null, fn ($q) => $q->whereIn(DB::raw('UPPER(contenedor)'), $contenedores))
            ->get(['contenedor', 'fecha'])
            ->reduce(function (array $keys, DescargaContenedor $descarga) {
                $key = $this->containerDateKey($descarga->contenedor, optional($descarga->fecha)->format('Y-m-d'));
                if ($key) {
                    $keys[$key] = true;
                }

                return $keys;
            }, []);
    }

    private function cleanUpper($value): ?string
    {
        $value = $this->cleanText($value);

        return $value ? mb_strtoupper($value) : null;
    }

    private function cleanText($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
