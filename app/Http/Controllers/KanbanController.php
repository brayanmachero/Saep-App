<?php

namespace App\Http\Controllers;

use App\Models\KanbanTablero;
use App\Models\KanbanColumna;
use App\Models\KanbanTarea;
use App\Models\KanbanEtiqueta;
use App\Models\CentroCosto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KanbanController extends Controller
{
    // =====================================================
    // TABLEROS
    // =====================================================

    public function index()
    {
        $tableros = KanbanTablero::with(['creador', 'centroCosto'])
            ->withCount('tareas')
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->get();

        return view('kanban.index', compact('tableros'));
    }

    public function create()
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('kanban.create', compact('centros', 'usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'          => 'required|string|max:200',
            'descripcion'     => 'nullable|string',
            'centro_costo_id' => 'nullable|exists:centros_costo,id',
        ]);

        $tablero = KanbanTablero::create([
            'nombre'          => $request->nombre,
            'descripcion'     => $request->descripcion,
            'centro_costo_id' => $request->centro_costo_id,
            'creado_por'      => auth()->id(),
        ]);

        $tablero->crearColumnasDefault();

        return redirect()->route('kanban.show', $tablero)
            ->with('success', "Tablero «{$tablero->nombre}» creado con columnas por defecto.");
    }

    public function show(KanbanTablero $kanban)
    {
        $kanban->load([
            'columnas.tareas.asignado',
            'columnas.tareas.etiquetas',
            'etiquetas',
            'centroCosto',
            'creador',
        ]);

        $usuarios = User::orderBy('name')->get();
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $vista    = request('vista', 'kanban'); // kanban | lista | calendario

        return view('kanban.show', compact('kanban', 'usuarios', 'centros', 'vista'));
    }

    public function edit(KanbanTablero $kanban)
    {
        $centros = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        return view('kanban.edit', compact('kanban', 'centros'));
    }

    public function update(Request $request, KanbanTablero $kanban)
    {
        $request->validate([
            'nombre'          => 'required|string|max:200',
            'descripcion'     => 'nullable|string',
            'centro_costo_id' => 'nullable|exists:centros_costo,id',
        ]);

        $kanban->update($request->only('nombre', 'descripcion', 'centro_costo_id'));

        return redirect()->route('kanban.show', $kanban)
            ->with('success', 'Tablero actualizado.');
    }

    public function destroy(KanbanTablero $kanban)
    {
        $kanban->update(['activo' => false]);
        return redirect()->route('kanban.index')
            ->with('success', 'Tablero archivado correctamente.');
    }

    // =====================================================
    // COLUMNAS (AJAX)
    // =====================================================

    public function storeColumna(Request $request, KanbanTablero $kanban)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'color'  => 'nullable|string|max:7',
        ]);

        $columna = $kanban->columnas()->create([
            'nombre' => $request->nombre,
            'color'  => $request->color ?? '#6b7280',
            'orden'  => $kanban->columnas()->max('orden') + 1,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'columna' => $columna]);
        }
        return back()->with('success', 'Columna agregada.');
    }

    public function updateColumna(Request $request, KanbanColumna $columna)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'color'  => 'nullable|string|max:7',
        ]);

        $columna->update($request->only('nombre', 'color'));

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Columna actualizada.');
    }

    public function destroyColumna(KanbanColumna $columna)
    {
        if ($columna->tareas()->count() > 0) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Mueva las tareas antes de eliminar la columna.'], 422);
            }
            return back()->with('error', 'No se puede eliminar una columna con tareas. Mueva las tareas primero.');
        }

        $columna->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Columna eliminada.');
    }

    // =====================================================
    // TAREAS
    // =====================================================

    public function storeTarea(Request $request, KanbanTablero $kanban)
    {
        $request->validate([
            'titulo'            => 'required|string|max:300',
            'descripcion'       => 'nullable|string',
            'columna_id'        => 'required|exists:kanban_columnas,id',
            'prioridad'         => 'nullable|string|in:ALTA,MEDIA,BAJA',
            'asignado_a'        => 'nullable|exists:users,id',
            'centro_costo_id'   => 'nullable|exists:centros_costo,id',
            'fecha_inicio'      => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_inicio',
            'etiquetas'         => 'nullable|array',
            'etiquetas.*'       => 'exists:kanban_etiquetas,id',
        ]);

        $tarea = $kanban->tareas()->create([
            'columna_id'        => $request->columna_id,
            'titulo'            => $request->titulo,
            'descripcion'       => $request->descripcion,
            'prioridad'         => $request->prioridad ?? 'MEDIA',
            'asignado_a'        => $request->asignado_a,
            'creado_por'        => auth()->id(),
            'centro_costo_id'   => $request->centro_costo_id,
            'fecha_inicio'      => $request->fecha_inicio,
            'fecha_vencimiento' => $request->fecha_vencimiento,
            'orden'             => KanbanTarea::where('columna_id', $request->columna_id)->max('orden') + 1,
        ]);

        if ($request->etiquetas) {
            $tarea->etiquetas()->sync($request->etiquetas);
        }

        if ($request->wantsJson()) {
            $tarea->load(['asignado', 'etiquetas']);
            return response()->json(['success' => true, 'tarea' => $tarea]);
        }
        return back()->with('success', 'Tarea creada.');
    }

    public function updateTarea(Request $request, KanbanTarea $tarea)
    {
        $request->validate([
            'titulo'            => 'required|string|max:300',
            'descripcion'       => 'nullable|string',
            'prioridad'         => 'nullable|string|in:ALTA,MEDIA,BAJA',
            'asignado_a'        => 'nullable|exists:users,id',
            'centro_costo_id'   => 'nullable|exists:centros_costo,id',
            'fecha_inicio'      => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_inicio',
            'etiquetas'         => 'nullable|array',
            'etiquetas.*'       => 'exists:kanban_etiquetas,id',
        ]);

        $tarea->update($request->only(
            'titulo', 'descripcion', 'prioridad', 'asignado_a',
            'centro_costo_id', 'fecha_inicio', 'fecha_vencimiento'
        ));

        if ($request->has('etiquetas')) {
            $tarea->etiquetas()->sync($request->etiquetas ?? []);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Tarea actualizada.');
    }

    public function destroyTarea(KanbanTarea $tarea)
    {
        $tarea->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Tarea eliminada.');
    }

    // =====================================================
    // DRAG & DROP (AJAX)
    // =====================================================

    /**
     * Mover tarea a otra columna y/o cambiar orden.
     */
    public function moverTarea(Request $request, KanbanTarea $tarea)
    {
        $request->validate([
            'columna_id' => 'required|exists:kanban_columnas,id',
            'orden'      => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $tarea) {
            $newColumnaId = (int) $request->columna_id;
            $newOrden     = (int) $request->orden;

            // Si cambió de columna, reordenar la columna anterior
            if ($tarea->columna_id !== $newColumnaId) {
                KanbanTarea::where('columna_id', $tarea->columna_id)
                    ->where('orden', '>', $tarea->orden)
                    ->decrement('orden');
            }

            // Hacer espacio en la columna destino
            KanbanTarea::where('columna_id', $newColumnaId)
                ->where('orden', '>=', $newOrden)
                ->where('id', '!=', $tarea->id)
                ->increment('orden');

            $tarea->update([
                'columna_id' => $newColumnaId,
                'orden'      => $newOrden,
            ]);
        });

        return response()->json(['success' => true, 'columna' => $tarea->fresh()->columna->nombre]);
    }

    // =====================================================
    // ETIQUETAS
    // =====================================================

    public function storeEtiqueta(Request $request, KanbanTablero $kanban)
    {
        $request->validate([
            'nombre' => 'required|string|max:50',
            'color'  => 'nullable|string|max:7',
        ]);

        $etiqueta = $kanban->etiquetas()->create([
            'nombre' => $request->nombre,
            'color'  => $request->color ?? '#3b82f6',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'etiqueta' => $etiqueta]);
        }
        return back()->with('success', 'Etiqueta creada.');
    }

    public function destroyEtiqueta(KanbanEtiqueta $etiqueta)
    {
        $etiqueta->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Etiqueta eliminada.');
    }

    // =====================================================
    // API: CALENDARIO
    // =====================================================

    public function calendarData(KanbanTablero $kanban)
    {
        $tareas = $kanban->tareas()
            ->whereNotNull('fecha_vencimiento')
            ->with('columna')
            ->get();

        $events = $tareas->map(fn ($t) => [
            'id'              => $t->id,
            'title'           => $t->titulo,
            'start'           => $t->fecha_inicio?->toDateString() ?? $t->fecha_vencimiento->toDateString(),
            'end'             => $t->fecha_vencimiento->toDateString(),
            'backgroundColor' => $t->columna->color,
            'borderColor'     => $t->columna->color,
            'extendedProps'   => [
                'prioridad' => $t->prioridad,
                'columna'   => $t->columna->nombre,
            ],
        ]);

        return response()->json($events);
    }
}
