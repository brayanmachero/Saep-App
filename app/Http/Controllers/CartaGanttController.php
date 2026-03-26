<?php

namespace App\Http\Controllers;

use App\Models\ProgramaSst;
use App\Models\SstCategoria;
use App\Models\SstActividad;
use App\Models\SstSeguimiento;
use App\Models\SstPlanAccion;
use App\Models\CentroCosto;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Mail\SstActividadAlertaMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CartaGanttController extends Controller
{
    // =====================================================
    // PROGRAMA SST (CRUD)
    // =====================================================

    public function index(Request $request)
    {
        $query = ProgramaSst::with(['creador', 'centroCosto', 'responsable']);

        if ($request->filled('anio')) {
            $query->where('anio', $request->anio);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->centro_costo_id);
        }

        $programas = $query->orderByDesc('anio')->orderByDesc('created_at')->get();

        $stats = [
            'total'   => ProgramaSst::count(),
            'activos' => ProgramaSst::where('estado', 'ACTIVO')->count(),
            'vencidas' => SstActividad::where('fecha_fin', '<', now())
                            ->where('estado', '!=', 'COMPLETADA')
                            ->where('estado', '!=', 'CANCELADA')->count(),
        ];

        $centros = CentroCosto::orderBy('nombre')->get();
        $anios   = ProgramaSst::distinct()->orderByDesc('anio')->pluck('anio');

        return view('carta_gantt.index', compact('programas', 'stats', 'centros', 'anios'));
    }

    public function create()
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.create', compact('centros', 'usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'anio'            => 'required|integer|min:2020|max:2099',
            'nombre'          => 'required|string|max:300',
            'descripcion'     => 'nullable|string',
            'estado'          => 'required|string|in:BORRADOR,ACTIVO,CERRADO',
            'centro_costo_id' => 'nullable|exists:centros_costo,id',
            'responsable_id'  => 'nullable|exists:users,id',
        ]);

        $programa = ProgramaSst::create([
            'anio'            => $request->anio,
            'titulo'          => $request->nombre,
            'descripcion'     => $request->descripcion,
            'estado'          => $request->estado,
            'centro_costo_id' => $request->centro_costo_id,
            'responsable_id'  => $request->responsable_id,
            'creado_por'      => auth()->id(),
        ]);

        return redirect()->route('carta-gantt.show', $programa)
            ->with('success', "Programa SST creado — Código: {$programa->codigo}");
    }

    public function show(ProgramaSst $cartaGantt)
    {
        $cartaGantt->load([
            'categorias.actividades.seguimiento',
            'categorias.actividades.responsableUser',
            'categorias.actividades.planesAccion',
            'centroCosto', 'responsable', 'creador',
        ]);
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.show', compact('cartaGantt', 'usuarios'));
    }

    public function edit(ProgramaSst $cartaGantt)
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.edit', compact('cartaGantt', 'centros', 'usuarios'));
    }

    public function update(Request $request, ProgramaSst $cartaGantt)
    {
        $request->validate([
            'nombre'          => 'required|string|max:300',
            'anio'            => 'required|integer|min:2020|max:2099',
            'estado'          => 'required|string|in:BORRADOR,ACTIVO,CERRADO',
            'centro_costo_id' => 'nullable|exists:centros_costo,id',
            'responsable_id'  => 'nullable|exists:users,id',
        ]);

        $cartaGantt->update([
            'titulo'          => $request->nombre,
            'anio'            => $request->anio,
            'descripcion'     => $request->descripcion,
            'estado'          => $request->estado,
            'centro_costo_id' => $request->centro_costo_id,
            'responsable_id'  => $request->responsable_id,
        ]);

        return redirect()->route('carta-gantt.show', $cartaGantt)
            ->with('success', 'Programa actualizado.');
    }

    public function destroy(ProgramaSst $cartaGantt)
    {
        $cartaGantt->update(['estado' => 'CERRADO']);
        return redirect()->route('carta-gantt.index')
            ->with('success', 'Programa cerrado correctamente.');
    }

    // =====================================================
    // CATEGORÍAS
    // =====================================================

    public function storeCategoria(Request $request, ProgramaSst $cartaGantt)
    {
        $request->validate(['nombre' => 'required|string|max:200']);
        $cartaGantt->categorias()->create([
            'nombre' => $request->nombre,
            'orden'  => $cartaGantt->categorias()->max('orden') + 1,
        ]);
        return back()->with('success', 'Categoría agregada.');
    }

    public function destroyCategoria(SstCategoria $categoria)
    {
        $categoria->delete();
        return back()->with('success', 'Categoría eliminada.');
    }

    // =====================================================
    // ACTIVIDADES
    // =====================================================

    public function storeActividad(Request $request, SstCategoria $categoria)
    {
        $request->validate([
            'nombre'         => 'required|string|max:300',
            'responsable_id' => 'nullable|exists:users,id',
            'fecha_inicio'   => 'nullable|date',
            'fecha_fin'      => 'nullable|date|after_or_equal:fecha_inicio',
            'prioridad'      => 'nullable|string|in:ALTA,MEDIA,BAJA',
            'periodicidad'   => 'nullable|string|in:' . implode(',', array_keys(SstActividad::periodicidadesMap())),
            'meses_prog'     => 'nullable|array',
            'meses_prog.*'   => 'integer|min:1|max:12',
        ]);

        $actividad = $categoria->actividades()->create([
            'nombre'         => $request->nombre,
            'descripcion'    => $request->descripcion,
            'responsable'    => $request->responsable_id
                ? User::find($request->responsable_id)?->nombre_completo
                : $request->responsable_nombre,
            'responsable_id' => $request->responsable_id,
            'fecha_inicio'   => $request->fecha_inicio,
            'fecha_fin'      => $request->fecha_fin,
            'prioridad'      => $request->prioridad ?? 'MEDIA',
            'periodicidad'   => $request->periodicidad,
            'orden'          => $categoria->actividades()->max('orden') + 1,
        ]);

        // Crear seguimiento para meses programados
        foreach ($request->get('meses_prog', []) as $mes) {
            $actividad->seguimiento()->updateOrCreate(
                ['mes' => (int) $mes],
                ['programado' => true]
            );
        }

        // Notificar al responsable si tiene email
        if ($actividad->responsable_id) {
            $user = User::find($actividad->responsable_id);
            if ($user?->email) {
                try {
                    Mail::to($user->email)->send(new SstActividadAlertaMail($actividad, 'asignacion'));
                } catch (\Exception $e) {
                    // SMTP no configurado — no bloquear la operación
                }
            }
        }

        return back()->with('success', 'Actividad agregada.');
    }

    public function updateActividad(Request $request, SstActividad $actividad)
    {
        $request->validate([
            'nombre'         => 'required|string|max:300',
            'responsable_id' => 'nullable|exists:users,id',
            'fecha_inicio'   => 'nullable|date',
            'fecha_fin'      => 'nullable|date|after_or_equal:fecha_inicio',
            'prioridad'      => 'nullable|string|in:ALTA,MEDIA,BAJA',
            'estado'         => 'nullable|string|in:' . implode(',', array_keys(SstActividad::estadosMap())),
            'periodicidad'   => 'nullable|string|in:' . implode(',', array_keys(SstActividad::periodicidadesMap())),
        ]);

        $actividad->update([
            'nombre'         => $request->nombre,
            'descripcion'    => $request->descripcion,
            'responsable'    => $request->responsable_id
                ? User::find($request->responsable_id)?->nombre_completo
                : ($request->responsable_nombre ?? $actividad->responsable),
            'responsable_id' => $request->responsable_id,
            'fecha_inicio'   => $request->fecha_inicio,
            'fecha_fin'      => $request->fecha_fin,
            'prioridad'      => $request->prioridad ?? $actividad->prioridad,
            'estado'         => $request->estado ?? $actividad->estado,
            'periodicidad'   => $request->periodicidad,
        ]);

        return back()->with('success', 'Actividad actualizada.');
    }

    public function destroyActividad(SstActividad $actividad)
    {
        $actividad->delete();
        return back()->with('success', 'Actividad eliminada.');
    }

    // =====================================================
    // SEGUIMIENTO (AJAX)
    // =====================================================

    public function updateSeguimiento(Request $request, SstActividad $actividad)
    {
        $request->validate([
            'mes'         => 'required|integer|min:1|max:12',
            'realizado'   => 'required|boolean',
            'observacion' => 'nullable|string|max:1000',
        ]);

        $actividad->seguimiento()->updateOrCreate(
            ['mes' => $request->mes],
            [
                'realizado'           => $request->realizado,
                'observacion'         => $request->observacion,
                'actualizado_por'     => auth()->id(),
                'fecha_actualizacion' => now(),
            ]
        );

        $this->recalcularEstadoActividad($actividad);

        return response()->json(['success' => true]);
    }

    // =====================================================
    // PLAN DE ACCIÓN
    // =====================================================

    public function storePlanAccion(Request $request, SstActividad $actividad)
    {
        $request->validate([
            'accion'            => 'required|string|max:500',
            'responsable'       => 'nullable|string|max:200',
            'fecha_compromiso'  => 'nullable|date',
        ]);

        $actividad->planesAccion()->create([
            'accion'           => $request->accion,
            'responsable'      => $request->responsable,
            'fecha_compromiso' => $request->fecha_compromiso,
            'estado'           => 'PENDIENTE',
            'observacion'      => $request->observacion,
            'creado_por'       => auth()->id(),
        ]);

        return back()->with('success', 'Plan de acción creado.');
    }

    public function updatePlanAccion(Request $request, SstPlanAccion $plan)
    {
        $request->validate([
            'estado'      => 'required|string|in:' . implode(',', array_keys(SstPlanAccion::estadosMap())),
            'observacion' => 'nullable|string',
        ]);

        $plan->update([
            'estado'      => $request->estado,
            'observacion' => $request->observacion,
        ]);

        return back()->with('success', 'Plan de acción actualizado.');
    }

    public function destroyPlanAccion(SstPlanAccion $plan)
    {
        $plan->delete();
        return back()->with('success', 'Plan de acción eliminado.');
    }

    // =====================================================
    // HELPERS
    // =====================================================

    private function recalcularEstadoActividad(SstActividad $actividad): void
    {
        $actividad->load('seguimiento');
        $programados = $actividad->seguimiento->where('programado', true)->count();
        $realizados  = $actividad->seguimiento->where('realizado', true)->count();

        if ($programados > 0 && $realizados >= $programados) {
            $actividad->update(['estado' => 'COMPLETADA']);
        } elseif ($realizados > 0) {
            $actividad->update(['estado' => 'EN_PROGRESO']);
        }
    }
}
