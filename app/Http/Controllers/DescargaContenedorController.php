<?php

namespace App\Http\Controllers;

use App\Models\CentroCosto;
use App\Models\DescargaContenedor;
use App\Models\DescargaContenedorCarga;
use App\Models\DescargaContenedorTarifa;
use App\Models\TalanaTrabajador;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DescargaContenedorController extends Controller
{
    public function index(Request $request)
    {
        $puedeGestionarCostos = $this->puedeGestionarCostos();
        $query = DescargaContenedor::with(['carga', 'centroCosto', 'participantes', 'creadoPor', 'tarifa'])
            ->withCount('participantes');

        if ($request->filled('buscar')) {
            $term = trim($request->input('buscar'));
            $query->where(function ($q) use ($term) {
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

        $descargas = $query->orderByRaw('fecha IS NULL')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $centros = $this->centrosOperacion();
        $stats = [
            'total' => DescargaContenedor::count(),
            'borradores' => DescargaContenedor::where('estado', 'borrador')->count(),
            'validadas' => DescargaContenedor::where('estado', 'validado')->count(),
            'liquidadas' => DescargaContenedor::where('estado', 'liquidado')->count(),
            'participantes' => DB::table('descarga_contenedor_participantes')->count(),
            'revision_tarifa' => DescargaContenedor::where('requiere_revision_tarifa', true)->count(),
            'sin_equipo' => DescargaContenedor::doesntHave('participantes')->count(),
            'listos_validar' => $this->readyForValidationQuery()->count(),
            'pendientes_validar' => $this->pendingValidationQuery()->count(),
            'pago_total' => DescargaContenedor::whereNotNull('pago_colaborador_snapshot')->sum('pago_colaborador_snapshot'),
        ];

        return view('descarga_contenedores.index', compact('descargas', 'centros', 'stats'));
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

            return $descarga;
        });

        return redirect()
            ->route('descarga-contenedores.show', $descarga)
            ->with('success', 'Descarga registrada correctamente.');
    }

    public function show(DescargaContenedor $descarga)
    {
        $descarga = $descarga->load([
            'centroCosto',
            'tarifa',
            'supervisor',
            'creadoPor',
            'validadoPor',
            'liquidadoPor',
            'participantes.talanaTrabajador.centroCosto',
            'participantes.talanaTrabajador.cargo',
            'participantes.user.centroCosto',
            'participantes.user.cargo',
        ]);

        $tarifas = $this->puedeGestionarCostos()
            ? DescargaContenedorTarifa::where('activo', true)
                ->where('codigo', $descarga->fact_codigo)
                ->orderBy('cliente')
                ->get()
            : collect();

        return view('descarga_contenedores.show', compact('descarga', 'tarifas'));
    }

    public function edit(DescargaContenedor $descarga)
    {
        $this->ensureEditable($descarga);

        $descarga = $descarga->load('participantes');

        return view('descarga_contenedores.edit', array_merge(
            $this->formData(),
            compact('descarga')
        ));
    }

    public function update(Request $request, DescargaContenedor $descarga)
    {
        $this->ensureEditable($descarga);

        $data = $this->validatedData($request);

        DB::transaction(function () use ($request, $descarga, $data) {
            if (!$descarga->supervisor_id) {
                $this->stampSupervisorFromLogin($data);
            }

            $this->applyTarifaSnapshot($data);
            $descarga->update($data);
            $descarga->refresh();
            $this->syncParticipantes($descarga, $this->extractParticipantesFromRequest($request));
        });

        return redirect()
            ->route('descarga-contenedores.show', $descarga)
            ->with('success', 'Descarga actualizada correctamente.');
    }

    public function validar(DescargaContenedor $descarga)
    {
        if ($descarga->estado !== 'borrador') {
            return back()->with('error', 'Solo se pueden validar registros en borrador.');
        }

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

    public function cargaRapida()
    {
        return view('descarga_contenedores.carga_rapida', $this->formData());
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

        $resultado = DB::transaction(function () use ($request, $payload) {
            $carga = DescargaContenedorCarga::create([
                'nombre' => $request->input('nombre') ?: 'Carga rápida ' . now()->format('d/m/Y H:i'),
                'origen' => 'pegado',
                'filas_detectadas' => count($payload),
                'filas_creadas' => 0,
                'filas_con_alertas' => 0,
                'raw_payload' => $payload,
                'creado_por' => auth()->id(),
            ]);

            $creadas = 0;
            $alertas = 0;

            foreach ($payload as $row) {
                if (!is_array($row) || $this->rowIsEmpty($row)) {
                    continue;
                }

                $data = $this->normalizeBulkRow($row);
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
            }

            $carga->update([
                'filas_creadas' => $creadas,
                'filas_con_alertas' => $alertas,
            ]);

            return [$carga, $creadas, $alertas];
        });

        [$carga, $creadas, $alertas] = $resultado;

        return redirect()
            ->route('descarga-contenedores.index', ['buscar' => $carga->nombre])
            ->with('success', "Carga rápida guardada: {$creadas} registros creados, {$alertas} con datos pendientes.");
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

        $query = TalanaTrabajador::with(['cargo', 'centroCosto']);
        $this->applyTrabajadoresDotacionFilter($query);

        if ($request->filled('buscar')) {
            $term = trim($request->input('buscar'));
            $query->where(function ($q) use ($term) {
                $q->where('nombre', 'like', "%{$term}%")
                    ->orWhere('apellido_paterno', 'like', "%{$term}%")
                    ->orWhere('apellido_materno', 'like', "%{$term}%")
                    ->orWhere('rut', 'like', "%{$term}%")
                    ->orWhere('cargo_nombre', 'like', "%{$term}%")
                    ->orWhere('centro_costo_nombre', 'like', "%{$term}%");
            });
        }

        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->input('centro_costo_id'));
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
        $cargos = $cargosQuery->distinct()
            ->orderBy('cargo_nombre')
            ->pluck('cargo_nombre');

        $trabajadoresBase = TalanaTrabajador::query();
        $this->applyTrabajadoresDotacionFilter($trabajadoresBase);
        $trabajadoresActivos = clone $trabajadoresBase;
        $trabajadoresInactivos = clone $trabajadoresBase;
        $trabajadoresCentros = clone $trabajadoresBase;
        $trabajadoresCargos = clone $trabajadoresBase;

        $stats = [
            'activos' => $trabajadoresActivos->where('activo', true)->count(),
            'inactivos' => $trabajadoresInactivos->where('activo', false)->count(),
            'centros' => $trabajadoresCentros->whereNotNull('centro_costo_id')->distinct()->count('centro_costo_id'),
            'cargos' => $trabajadoresCargos->whereNotNull('cargo_nombre')->distinct()->count('cargo_nombre'),
            'participantes' => DB::table('descarga_contenedor_participantes')->whereNotNull('talana_trabajador_id')->distinct()->count('talana_trabajador_id'),
        ];

        return view('descarga_contenedores.dotacion', compact('trabajadores', 'participacion', 'centros', 'cargos', 'stats'));
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

        $query = DescargaContenedorTarifa::query();

        if ($request->filled('buscar')) {
            $term = trim($request->input('buscar'));
            $query->where(function ($q) use ($term) {
                $q->where('cliente', 'like', "%{$term}%")
                    ->orWhere('codigo', 'like', "%{$term}%")
                    ->orWhere('proceso', 'like', "%{$term}%");
            });
        }

        if ($request->input('estado') === 'activos') {
            $query->where('activo', true);
        } elseif ($request->input('estado') === 'inactivos') {
            $query->where('activo', false);
        }

        $tarifas = $query->orderBy('cliente')
            ->orderBy('codigo')
            ->orderBy('proceso')
            ->paginate(30)
            ->withQueryString();

        return view('descarga_contenedores.tarifas', compact('tarifas'));
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
            $query->where(function ($q) use ($term) {
                $q->where('p.nombre_snapshot', 'like', "%{$term}%")
                    ->orWhere('p.rut_snapshot', 'like', "%{$term}%")
                    ->orWhere('p.cargo_snapshot', 'like', "%{$term}%")
                    ->orWhere('d.contenedor', 'like', "%{$term}%")
                    ->orWhere('d.fact_codigo', 'like', "%{$term}%");
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
        abort_if($descarga->estado === 'liquidado', 403, 'No se puede editar un registro liquidado.');
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
        $query = CentroCosto::where('activo', true);
        $this->applyKeywordFilter($query, 'nombre', $keywords);

        return $query->orderBy('nombre')->get();
    }

    private function applyTrabajadoresDotacionFilter($query): void
    {
        $centros = $this->centrosByKeywords($this->descargaDotacionCenterKeywords());
        $hasDotacionByName = TalanaTrabajador::query()
            ->where(function ($q) {
                $this->applyKeywordFilter($q, 'centro_costo_nombre', $this->descargaDotacionCenterKeywords());
            })
            ->exists();

        if ($centros->isEmpty() && !$hasDotacionByName) {
            return;
        }

        $query->where(function ($q) use ($centros) {
            if ($centros->isNotEmpty()) {
                $q->whereIn('centro_costo_id', $centros->pluck('id'));
            }

            $q->orWhere(function ($nameQuery) {
                $this->applyKeywordFilter($nameQuery, 'centro_costo_nombre', $this->descargaDotacionCenterKeywords());
            });
        })->where(function ($cargoQuery) {
            $this->applyKeywordFilter($cargoQuery, 'cargo_nombre', $this->descargaDotacionCargoKeywords());

            $cargoQuery->orWhereHas('cargo', function ($cargo) {
                $this->applyKeywordFilter($cargo, 'nombre', $this->descargaDotacionCargoKeywords());
            });
        });
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
        return [
            'CAMPOS DE CHILE',
            'LTS CAMPOS DE CHILE',
            'LTS PEÑON',
            'LTS PEÑÓN',
            'LTS PENON',
            'LTS QUILICURA',
            'MAERSK PUMA',
            'MAERSK LA VARA',
            'MAERSK MATTEL',
        ];
    }

    private function descargaDotacionCargoKeywords(): array
    {
        return [
            'DESCARGA',
            'ESTIBA',
            'DESCARGADOR',
            'ENCARGADO DE TURNO',
            'SUPERVISOR DE OPERACIONES',
        ];
    }

    private function descargaOperacionCenterKeywords(): array
    {
        return array_values(array_unique(array_merge($this->descargaDotacionCenterKeywords(), [
            'DHL',
            'ECOMMERCE',
            'E-COMMERCE',
            'TRANSPORTE',
            'BRAZO',
        ])));
    }

    private function formData(): array
    {
        return [
            'centros' => $this->centrosOperacion(),
            'tarifas' => DescargaContenedorTarifa::where('activo', true)->orderBy('cliente')->orderBy('codigo')->get(),
            'trabajadores' => $this->trabajadoresSelector(),
            'supervisorSistema' => auth()->user()?->loadMissing(['cargo', 'centroCosto']),
        ];
    }

    private function trabajadoresSelector()
    {
        $query = TalanaTrabajador::with(['cargo', 'centroCosto'])
            ->where('activo', true);
        $this->applyTrabajadoresDotacionFilter($query);

        return $query->orderBy('nombre')
            ->orderBy('apellido_paterno')
            ->get()
            ->map(fn (TalanaTrabajador $trabajador) => [
                'id' => $trabajador->id,
                'label' => $trabajador->nombre_completo ?: $trabajador->nombre,
                'rut' => $trabajador->rut,
                'cargo_id' => $trabajador->cargo_id,
                'cargo' => $trabajador->cargo?->nombre ?: $trabajador->cargo_nombre,
                'centro' => $trabajador->centroCosto?->nombre ?: $trabajador->centro_costo_nombre,
                'centro_costo_id' => $trabajador->centro_costo_id,
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
        ]);

        foreach (['hora_cita', 'hora_inicio_descarga', 'hora_termino_descarga'] as $field) {
            $data[$field] = $this->normalizeTime($data[$field] ?? null);
        }

        $data['fact_codigo'] = $this->cleanUpper($data['fact_codigo'] ?? null);

        return $data;
    }

    private function validatedTarifa(Request $request): array
    {
        $data = $request->validate([
            'cliente' => ['required', 'string', 'max:80'],
            'codigo' => ['required', 'string', 'max:40'],
            'proceso' => ['required', 'string', 'max:180'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'pago_colaborador' => ['nullable', 'numeric', 'min:0'],
            'requiere_revision' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:400'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['cliente'] = mb_strtoupper(trim($data['cliente']));
        $data['codigo'] = $this->cleanUpper($data['codigo']);
        $data['proceso'] = trim($data['proceso']);
        $data['requiere_revision'] = (bool) ($data['requiere_revision'] ?? false);
        $data['activo'] = (bool) ($data['activo'] ?? false);

        return $data;
    }

    private function applyTarifaSnapshot(array &$data): void
    {
        $tarifa = null;

        if (!empty($data['tarifa_id'])) {
            $tarifa = DescargaContenedorTarifa::find($data['tarifa_id']);
        }

        if (!$tarifa && !empty($data['fact_codigo'])) {
            $tarifasCoincidentes = DescargaContenedorTarifa::where('activo', true)
                ->where('codigo', $data['fact_codigo'])
                ->orderBy('cliente')
                ->orderBy('id')
                ->limit(2)
                ->get();

            if ($tarifasCoincidentes->count() === 1) {
                $tarifa = $tarifasCoincidentes->first();
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

        $data['tarifa_id'] = $tarifa->id;
        $data['fact_codigo'] = $tarifa->codigo;
        $data['tarifa_cliente_snapshot'] = $tarifa->cliente;
        $data['tarifa_proceso_snapshot'] = $tarifa->proceso;
        $data['costo_unitario_snapshot'] = $tarifa->costo_unitario;
        $data['pago_colaborador_snapshot'] = $tarifa->pago_colaborador;
        $data['requiere_revision_tarifa'] = $tarifa->requiere_revision;
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

    private function syncParticipantes(DescargaContenedor $descarga, array $participantesPayload): void
    {
        $participantes = $this->normalizeParticipantesPayload($participantesPayload);
        $trabajadorIds = $participantes->pluck('id')
            ->values();

        $descarga->participantes()->delete();

        if ($trabajadorIds->isEmpty()) {
            return;
        }

        $trabajadoresQuery = TalanaTrabajador::with(['cargo', 'centroCosto'])
            ->whereIn('id', $trabajadorIds);
        $this->applyTrabajadoresDotacionFilter($trabajadoresQuery);
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
                'centro_costo_id_snapshot' => $trabajador->centro_costo_id,
                'centro_costo_snapshot' => $trabajador->centroCosto?->nombre ?: $trabajador->centro_costo_nombre,
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
                ];
            })
            ->filter()
            ->unique('id')
            ->values();

        if ($items->isEmpty()) {
            return $items;
        }

        $sum = (float) $items->sum(fn ($item) => $item['porcentaje'] ?? 0);
        if ($sum <= 0) {
            return $this->distribuirIgual($items->pluck('id')->all());
        }

        $assigned = 0.0;
        $lastIndex = $items->count() - 1;

        return $items->map(function ($item, $index) use ($sum, $lastIndex, &$assigned) {
            if ($index === $lastIndex) {
                $porcentaje = round(100 - $assigned, 2);
            } else {
                $porcentaje = round(((float) ($item['porcentaje'] ?? 0)) * 100 / $sum, 2);
                $assigned += $porcentaje;
            }

            return [
                'id' => $item['id'],
                'porcentaje' => $porcentaje,
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

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d', 'd.m.Y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
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
