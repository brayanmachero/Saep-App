<?php

namespace App\Http\Controllers;

use App\Models\KanbanTablero;
use App\Models\KanbanColumna;
use App\Models\KanbanTarea;
use App\Models\KanbanEtiqueta;
use App\Models\KanbanAdjunto;
use App\Models\KanbanChecklistItem;
use App\Models\KanbanComentario;
use App\Models\KanbanActividadLog;
use App\Models\CentroCosto;
use App\Models\User;
use App\Mail\KanbanTareaAsignadaMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPdf\Facade\Pdf;

class KanbanController extends Controller
{
    // =====================================================
    // TABLEROS
    // =====================================================

    public function index()
    {
        $tableros = KanbanTablero::with(['creador', 'centroCosto', 'miembros'])
            ->withCount('tareas')
            ->where('activo', true)
            ->visiblesParaUsuario()
            ->orderByDesc('updated_at')
            ->get();

        // Mis tareas (de todos los tableros visibles)
        $misTareasCount = KanbanTarea::whereHas('asignados', fn ($q) => $q->where('user_id', auth()->id()))
            ->whereHas('tablero', fn ($q) => $q->where('activo', true)->visiblesParaUsuario())
            ->count();

        return view('kanban.index', compact('tableros', 'misTareasCount'));
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

        // Agregar creador como admin del tablero
        $tablero->miembros()->attach(auth()->id(), ['rol' => 'admin']);

        KanbanActividadLog::registrar($tablero->id, null, 'created', "Tablero «{$tablero->nombre}» creado");

        return redirect()->route('kanban.show', $tablero)
            ->with('success', "Tablero «{$tablero->nombre}» creado con columnas por defecto.");
    }

    public function show(KanbanTablero $kanban)
    {
        $kanban->load([
            'columnas.tareas' => function ($q) {
                $q->where('archivada', false);
                // Aplicar filtros a las tareas eager-loaded
                if (request('filtro_asignado'))    $q->whereHas('asignados', fn ($a) => $a->where('user_id', request('filtro_asignado')));
                if (request('filtro_prioridad'))   $q->where('prioridad', request('filtro_prioridad'));
                if (request('filtro_etiqueta'))     $q->whereHas('etiquetas', fn ($e) => $e->where('kanban_etiquetas.id', request('filtro_etiqueta')));
                $q->with(['asignados', 'etiquetas', 'comentarios', 'adjuntos', 'checklistItems']);
            },
            'etiquetas',
            'centroCosto',
            'creador',
            'miembros',
        ]);

        $usuarios = User::orderBy('name')->get();
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $vista    = request('vista', 'kanban');
        $filtrosActivos = request()->only('filtro_asignado', 'filtro_prioridad', 'filtro_etiqueta');

        return view('kanban.show', compact('kanban', 'usuarios', 'centros', 'vista', 'filtrosActivos'));
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

    public function forceDestroy(KanbanTablero $kanban)
    {
        $nombre = $kanban->nombre;

        // Eliminar tareas y dependencias
        foreach ($kanban->columnas as $col) {
            foreach ($col->tareas as $tarea) {
                $tarea->checklistItems()->delete();
                $tarea->comentarios()->delete();
                $tarea->adjuntos()->delete();
                $tarea->etiquetas()->detach();
                $tarea->asignados()->detach();
                $tarea->delete();
            }
            $col->delete();
        }
        $kanban->etiquetas()->delete();
        $kanban->miembros()->detach();
        $kanban->delete();

        return redirect()->route('kanban.index')
            ->with('success', "Tablero «{$nombre}» eliminado definitivamente.");
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

    public function toggleCompletadaColumna(KanbanColumna $columna)
    {
        $columna->update(['es_completada' => !$columna->es_completada]);

        return response()->json([
            'success' => true,
            'es_completada' => $columna->es_completada,
        ]);
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
            'asignados'         => 'nullable|array',
            'asignados.*'       => 'exists:users,id',
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
            'creado_por'        => auth()->id(),
            'centro_costo_id'   => $request->centro_costo_id,
            'fecha_inicio'      => $request->fecha_inicio,
            'fecha_vencimiento' => $request->fecha_vencimiento,
            'orden'             => KanbanTarea::where('columna_id', $request->columna_id)->max('orden') + 1,
        ]);

        // Multi-assignee sync
        $asignadoIds = $request->asignados ?? [];
        if (!empty($asignadoIds)) {
            $tarea->asignados()->sync($asignadoIds);
        }

        if ($request->etiquetas) {
            $tarea->etiquetas()->sync($request->etiquetas);
        }

        KanbanActividadLog::registrar($kanban->id, $tarea->id, 'created', "Tarea «{$tarea->titulo}» creada");

        // Notificar a los asignados
        $tarea->load(['tablero', 'columna', 'asignados']);
        foreach ($tarea->asignados as $asignado) {
            KanbanActividadLog::registrar($kanban->id, $tarea->id, 'assigned',
                "Tarea asignada a {$asignado->name}");
            if ($asignado->email) {
                try {
                    Mail::to($asignado->email)
                        ->send(new KanbanTareaAsignadaMail($tarea, auth()->user()));
                } catch (\Throwable $e) {
                    \Log::warning('Kanban: no se pudo notificar asignación', ['error' => $e->getMessage()]);
                }
            }
        }

        if ($request->wantsJson()) {
            $tarea->load(['asignados', 'etiquetas']);
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
            'asignados'         => 'nullable|array',
            'asignados.*'       => 'exists:users,id',
            'centro_costo_id'   => 'nullable|exists:centros_costo,id',
            'fecha_inicio'      => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_inicio',
            'etiquetas'         => 'nullable|array',
            'etiquetas.*'       => 'exists:kanban_etiquetas,id',
        ]);

        $oldAsignadoIds = $tarea->asignados->pluck('id')->toArray();

        $tarea->update($request->only(
            'titulo', 'descripcion', 'prioridad',
            'centro_costo_id', 'fecha_inicio', 'fecha_vencimiento'
        ));

        // Multi-assignee sync
        $newAsignadoIds = $request->asignados ?? [];
        $tarea->asignados()->sync($newAsignadoIds);

        if ($request->has('etiquetas')) {
            $tarea->etiquetas()->sync($request->etiquetas ?? []);
        }

        KanbanActividadLog::registrar($tarea->tablero_id, $tarea->id, 'updated', "Tarea «{$tarea->titulo}» actualizada");

        // Notificar a nuevos asignados
        $nuevos = array_diff($newAsignadoIds, $oldAsignadoIds);
        if (!empty($nuevos)) {
            $tarea->load(['tablero', 'columna']);
            foreach (User::whereIn('id', $nuevos)->get() as $usuario) {
                KanbanActividadLog::registrar($tarea->tablero_id, $tarea->id, 'assigned',
                    "Tarea asignada a {$usuario->name}");
                if ($usuario->email) {
                    try {
                        Mail::to($usuario->email)
                            ->send(new KanbanTareaAsignadaMail($tarea, auth()->user()));
                    } catch (\Throwable $e) {
                        \Log::warning('Kanban: no se pudo notificar reasignación', ['error' => $e->getMessage()]);
                    }
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Tarea actualizada.');
    }

    public function destroyTarea(KanbanTarea $tarea)
    {
        $user = auth()->user();
        if (!$user->esSuperAdmin() && $tarea->creado_por !== $user->id) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Solo el creador o SuperAdmin puede eliminar esta tarea.'], 403);
            }
            return back()->with('error', 'No tienes permiso para eliminar esta tarea.');
        }

        $tableroId = $tarea->tablero_id;
        $titulo = $tarea->titulo;
        $tarea->delete();

        KanbanActividadLog::registrar($tableroId, null, 'deleted', "Tarea «{$titulo}» eliminada");

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

        $completada = false;
        DB::transaction(function () use ($request, $tarea, &$completada) {
            $newColumnaId = (int) $request->columna_id;
            $newOrden     = (int) $request->orden;
            $oldColumnaId = $tarea->columna_id;

            // Si cambió de columna, reordenar la columna anterior
            if ($oldColumnaId !== $newColumnaId) {
                KanbanTarea::where('columna_id', $oldColumnaId)
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

            if ($oldColumnaId !== $newColumnaId) {
                $colNueva = KanbanColumna::find($newColumnaId);
                $completada = $colNueva?->es_completada ?? false;
                KanbanActividadLog::registrar($tarea->tablero_id, $tarea->id, 'moved',
                    "Tarea «{$tarea->titulo}» movida a «{$colNueva->nombre}»");
            }
        });

        $fresh = $tarea->fresh()->load('columna');
        return response()->json([
            'success' => true,
            'columna' => $fresh->columna->nombre,
            'completada' => $fresh->columna->es_completada,
        ]);
    }

    // =====================================================
    // MIS TAREAS (cross-board)
    // =====================================================

    public function misTareas()
    {
        $tareas = KanbanTarea::with(['tablero', 'columna', 'etiquetas', 'asignados', 'checklistItems'])
            ->whereHas('asignados', fn ($q) => $q->where('user_id', auth()->id()))
            ->where('archivada', false)
            ->whereHas('tablero', fn ($q) => $q->where('activo', true)->visiblesParaUsuario())
            ->orderByRaw("FIELD(prioridad, 'ALTA', 'MEDIA', 'BAJA')")
            ->orderBy('fecha_vencimiento')
            ->get();

        return view('kanban.mis_tareas', compact('tareas'));
    }

    // =====================================================
    // MIEMBROS DEL TABLERO
    // =====================================================

    public function storeMiembro(Request $request, KanbanTablero $kanban)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'rol'     => 'nullable|string|in:admin,editor,viewer',
        ]);

        if ($kanban->miembros()->where('user_id', $request->user_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'El usuario ya es miembro.'], 422);
        }

        $kanban->miembros()->attach($request->user_id, [
            'rol' => $request->rol ?? 'editor',
        ]);

        $usuario = User::find($request->user_id);
        KanbanActividadLog::registrar($kanban->id, null, 'member_added',
            "{$usuario->name} agregado como " . ($request->rol ?? 'editor'));

        return response()->json([
            'success' => true,
            'miembro' => [
                'id'   => $usuario->id,
                'name' => $usuario->name,
                'rol'  => $request->rol ?? 'editor',
            ],
        ]);
    }

    public function destroyMiembro(KanbanTablero $kanban, User $user)
    {
        // No permitir remover al creador
        if ($kanban->creado_por === $user->id) {
            return response()->json(['success' => false, 'message' => 'No se puede remover al creador.'], 422);
        }

        $kanban->miembros()->detach($user->id);

        KanbanActividadLog::registrar($kanban->id, null, 'member_removed',
            "{$user->name} removido del tablero");

        return response()->json(['success' => true]);
    }

    // =====================================================
    // ACTIVIDAD LOG
    // =====================================================

    public function actividad(KanbanTablero $kanban)
    {
        $actividades = $kanban->actividadLog()
            ->with(['usuario', 'tarea'])
            ->take(50)
            ->get();

        return response()->json(['actividades' => $actividades]);
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
    // DETALLE TAREA (AJAX — JSON)
    // =====================================================

    public function showTarea(KanbanTarea $tarea)
    {
        $tarea->load([
            'columna', 'asignados', 'creador', 'centroCosto', 'etiquetas',
            'comentarios.usuario', 'adjuntos.subidoPor', 'checklistItems',
        ]);

        return response()->json([
            'id'               => $tarea->id,
            'titulo'           => $tarea->titulo,
            'descripcion'      => $tarea->descripcion,
            'prioridad'        => $tarea->prioridad,
            'asignados'        => $tarea->asignados->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'creador_nombre'   => $tarea->creador?->name,
            'centro_costo_id'  => $tarea->centro_costo_id,
            'columna_id'       => $tarea->columna_id,
            'columna_nombre'   => $tarea->columna?->nombre,
            'columna_color'    => $tarea->columna?->color,
            'fecha_inicio'     => $tarea->fecha_inicio?->toDateString(),
            'fecha_vencimiento'=> $tarea->fecha_vencimiento?->toDateString(),
            'esta_vencida'     => $tarea->estaVencida,
            'tablero_id'       => $tarea->tablero_id,
            'etiquetas'        => $tarea->etiquetas->map(fn ($e) => [
                'id' => $e->id, 'nombre' => $e->nombre, 'color' => $e->color,
            ]),
            'comentarios'      => $tarea->comentarios->map(fn ($c) => [
                'id'        => $c->id,
                'contenido' => $c->contenido,
                'usuario'   => $c->usuario?->name ?? 'Sistema',
                'iniciales' => strtoupper(substr($c->usuario?->name ?? 'S', 0, 2)),
                'fecha'     => $c->created_at->diffForHumans(),
            ]),
            'checklist'        => $tarea->checklistItems->map(fn ($i) => [
                'id'         => $i->id,
                'texto'      => $i->texto,
                'completado' => $i->completado,
            ]),
            'adjuntos'         => $tarea->adjuntos->map(fn ($a) => [
                'id'              => $a->id,
                'nombre_original' => $a->nombre_original,
                'tamanio'         => $a->tamanioFormateado,
                'es_imagen'       => $a->esImagen(),
                'url_imagen'      => $a->esImagen() ? Storage::url($a->ruta) : null,
                'subido_por'      => $a->subidoPor?->name ?? 'Sistema',
                'fecha'           => $a->created_at->diffForHumans(),
                'url_descargar'   => route('kanban.adjuntos.descargar', $a->id),
            ]),
            'checklist_progreso' => $tarea->checklistProgreso,
            'creado_por'         => $tarea->creado_por,
            'puede_eliminar'     => auth()->user()->esSuperAdmin() || $tarea->creado_por === auth()->id(),
        ]);
    }

    // =====================================================
    // COMENTARIOS
    // =====================================================

    public function storeComentario(Request $request, KanbanTarea $tarea)
    {
        $request->validate(['contenido' => 'required|string|max:2000']);

        $comentario = $tarea->comentarios()->create([
            'user_id'   => auth()->id(),
            'contenido' => $request->contenido,
        ]);
        $comentario->load('usuario');

        KanbanActividadLog::registrar($tarea->tablero_id, $tarea->id, 'commented',
            "Comentario en «{$tarea->titulo}»");

        return response()->json([
            'success'  => true,
            'comentario' => [
                'id'        => $comentario->id,
                'contenido' => $comentario->contenido,
                'usuario'   => $comentario->usuario?->name,
                'iniciales' => strtoupper(substr($comentario->usuario?->name ?? 'S', 0, 2)),
                'fecha'     => $comentario->created_at->diffForHumans(),
            ],
        ]);
    }

    // =====================================================
    // CHECKLIST
    // =====================================================

    public function storeChecklistItem(Request $request, KanbanTarea $tarea)
    {
        $request->validate(['texto' => 'required|string|max:500']);

        $item = $tarea->checklistItems()->create([
            'texto' => $request->texto,
            'orden' => $tarea->checklistItems()->max('orden') + 1,
        ]);

        return response()->json(['success' => true, 'item' => [
            'id' => $item->id, 'texto' => $item->texto, 'completado' => false,
        ]]);
    }

    public function toggleChecklistItem(KanbanChecklistItem $item)
    {
        $item->update(['completado' => !$item->completado]);

        $progreso = $item->tarea->checklistProgreso;

        return response()->json([
            'success'    => true,
            'completado' => $item->completado,
            'progreso'   => $progreso,
        ]);
    }

    public function destroyChecklistItem(KanbanChecklistItem $item)
    {
        $item->delete();
        return response()->json(['success' => true]);
    }

    // =====================================================
    // ADJUNTOS
    // =====================================================

    public function storeAdjunto(Request $request, KanbanTarea $tarea)
    {
        $request->validate([
            'archivo' => 'required|file|max:10240', // 10 MB máx
        ]);

        $file = $request->file('archivo');
        $ruta = $file->store('kanban/adjuntos/' . $tarea->id, 'public');

        $adjunto = $tarea->adjuntos()->create([
            'nombre_original' => $file->getClientOriginalName(),
            'ruta'            => $ruta,
            'mime_type'       => $file->getClientMimeType(),
            'tamanio'         => $file->getSize(),
            'subido_por'      => auth()->id(),
        ]);

        KanbanActividadLog::registrar($tarea->tablero_id, $tarea->id, 'attachment',
            "Adjunto «{$file->getClientOriginalName()}» subido a «{$tarea->titulo}»");

        return response()->json(['success' => true, 'adjunto' => [
            'id'              => $adjunto->id,
            'nombre_original' => $adjunto->nombre_original,
            'tamanio'         => $adjunto->tamanioFormateado,
            'es_imagen'       => $adjunto->esImagen(),
            'url_imagen'      => $adjunto->esImagen() ? Storage::url($adjunto->ruta) : null,
            'subido_por'      => auth()->user()->name,
            'fecha'           => 'Justo ahora',
            'url_descargar'   => route('kanban.adjuntos.descargar', $adjunto->id),
        ]]);
    }

    public function destroyAdjunto(KanbanAdjunto $adjunto)
    {
        Storage::disk('public')->delete($adjunto->ruta);
        $adjunto->delete();
        return response()->json(['success' => true]);
    }

    public function descargarAdjunto(KanbanAdjunto $adjunto)
    {
        $path = Storage::disk('public')->path($adjunto->ruta);

        if (!file_exists($path)) {
            abort(404, 'Archivo no encontrado');
        }

        return response()->download($path, $adjunto->nombre_original);
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

    // =====================================================
    // DUPLICAR TABLERO
    // =====================================================

    public function duplicar(KanbanTablero $kanban)
    {
        $nuevo = $kanban->replicate(['activo']);
        $nuevo->nombre = $kanban->nombre . ' (copia)';
        $nuevo->creado_por = auth()->id();
        $nuevo->save();

        // Copiar columnas
        $colMap = [];
        foreach ($kanban->columnas as $col) {
            $newCol = $nuevo->columnas()->create($col->only('nombre', 'color', 'orden'));
            $colMap[$col->id] = $newCol->id;
        }

        // Copiar etiquetas
        foreach ($kanban->etiquetas as $etq) {
            $nuevo->etiquetas()->create($etq->only('nombre', 'color'));
        }

        // Agregar creador como admin
        $nuevo->miembros()->attach(auth()->id(), ['rol' => 'admin']);

        KanbanActividadLog::registrar($nuevo->id, null, 'created',
            "Tablero duplicado desde «{$kanban->nombre}»");

        return redirect()->route('kanban.show', $nuevo)
            ->with('success', "Tablero «{$nuevo->nombre}» creado como copia.");
    }

    // =====================================================
    // PLANTILLAS DE TABLERO
    // =====================================================

    public function crearDesdePlantilla(Request $request)
    {
        $request->validate([
            'plantilla'       => 'required|string|in:proyecto,sprint,rrhh,sst,basico',
            'nombre'          => 'required|string|max:200',
            'centro_costo_id' => 'nullable|exists:centros_costo,id',
        ]);

        $plantillas = [
            'proyecto' => [
                'columnas' => [
                    ['nombre' => 'Idea', 'color' => '#6b7280'],
                    ['nombre' => 'Planificación', 'color' => '#3b82f6'],
                    ['nombre' => 'En Desarrollo', 'color' => '#f59e0b'],
                    ['nombre' => 'Testing', 'color' => '#8b5cf6'],
                    ['nombre' => 'Deploy', 'color' => '#10b981'],
                ],
                'etiquetas' => [
                    ['nombre' => 'Bug', 'color' => '#ef4444'],
                    ['nombre' => 'Feature', 'color' => '#3b82f6'],
                    ['nombre' => 'Mejora', 'color' => '#10b981'],
                    ['nombre' => 'Urgente', 'color' => '#f97316'],
                ],
            ],
            'sprint' => [
                'columnas' => [
                    ['nombre' => 'Product Backlog', 'color' => '#6b7280'],
                    ['nombre' => 'Sprint Backlog', 'color' => '#3b82f6'],
                    ['nombre' => 'En Curso', 'color' => '#f59e0b'],
                    ['nombre' => 'Code Review', 'color' => '#8b5cf6'],
                    ['nombre' => 'Done', 'color' => '#10b981'],
                ],
                'etiquetas' => [
                    ['nombre' => 'Historia', 'color' => '#3b82f6'],
                    ['nombre' => 'Tarea', 'color' => '#6b7280'],
                    ['nombre' => 'Blocker', 'color' => '#ef4444'],
                    ['nombre' => 'Tech Debt', 'color' => '#f97316'],
                ],
            ],
            'rrhh' => [
                'columnas' => [
                    ['nombre' => 'Solicitudes', 'color' => '#6b7280'],
                    ['nombre' => 'En Revisión', 'color' => '#3b82f6'],
                    ['nombre' => 'Aprobado', 'color' => '#10b981'],
                    ['nombre' => 'Rechazado', 'color' => '#ef4444'],
                    ['nombre' => 'Completado', 'color' => '#8b5cf6'],
                ],
                'etiquetas' => [
                    ['nombre' => 'Vacaciones', 'color' => '#3b82f6'],
                    ['nombre' => 'Permiso', 'color' => '#f59e0b'],
                    ['nombre' => 'Capacitación', 'color' => '#10b981'],
                    ['nombre' => 'Contratación', 'color' => '#8b5cf6'],
                ],
            ],
            'sst' => [
                'columnas' => [
                    ['nombre' => 'Identificado', 'color' => '#ef4444'],
                    ['nombre' => 'Evaluando', 'color' => '#f59e0b'],
                    ['nombre' => 'Medidas Aplicadas', 'color' => '#3b82f6'],
                    ['nombre' => 'Seguimiento', 'color' => '#8b5cf6'],
                    ['nombre' => 'Cerrado', 'color' => '#10b981'],
                ],
                'etiquetas' => [
                    ['nombre' => 'Riesgo Alto', 'color' => '#ef4444'],
                    ['nombre' => 'Riesgo Medio', 'color' => '#f59e0b'],
                    ['nombre' => 'Riesgo Bajo', 'color' => '#10b981'],
                    ['nombre' => 'Incidente', 'color' => '#8b5cf6'],
                ],
            ],
            'basico' => [
                'columnas' => [
                    ['nombre' => 'Por Hacer', 'color' => '#6b7280'],
                    ['nombre' => 'En Progreso', 'color' => '#f59e0b'],
                    ['nombre' => 'Completado', 'color' => '#10b981'],
                ],
                'etiquetas' => [],
            ],
        ];

        $tpl = $plantillas[$request->plantilla];

        $tablero = KanbanTablero::create([
            'nombre'          => $request->nombre,
            'descripcion'     => $request->descripcion,
            'centro_costo_id' => $request->centro_costo_id,
            'creado_por'      => auth()->id(),
        ]);

        foreach ($tpl['columnas'] as $i => $col) {
            $tablero->columnas()->create(array_merge($col, ['orden' => $i + 1]));
        }
        foreach ($tpl['etiquetas'] as $etq) {
            $tablero->etiquetas()->create($etq);
        }

        $tablero->miembros()->attach(auth()->id(), ['rol' => 'admin']);

        KanbanActividadLog::registrar($tablero->id, null, 'created',
            "Tablero creado desde plantilla «{$request->plantilla}»");

        return redirect()->route('kanban.show', $tablero)
            ->with('success', "Tablero «{$tablero->nombre}» creado desde plantilla.");
    }

    // =====================================================
    // BÚSQUEDA GLOBAL
    // =====================================================

    public function buscar(Request $request)
    {
        $q = $request->input('q', '');

        if (strlen($q) < 2) {
            return view('kanban.buscar', ['tareas' => collect(), 'q' => $q]);
        }

        $tareas = KanbanTarea::with(['tablero', 'columna', 'asignados', 'etiquetas'])
            ->where('archivada', false)
            ->whereHas('tablero', fn ($tb) => $tb->where('activo', true)->visiblesParaUsuario())
            ->where(function ($w) use ($q) {
                $w->where('titulo', 'like', "%{$q}%")
                  ->orWhere('descripcion', 'like', "%{$q}%");
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return view('kanban.buscar', compact('tareas', 'q'));
    }

    // =====================================================
    // ARCHIVAR / DESARCHIVAR TAREAS
    // =====================================================

    public function archivarTarea(KanbanTarea $tarea)
    {
        $tarea->update(['archivada' => true]);

        KanbanActividadLog::registrar($tarea->tablero_id, $tarea->id, 'updated',
            "Tarea «{$tarea->titulo}» archivada");

        return response()->json(['success' => true]);
    }

    public function desarchivarTarea(KanbanTarea $tarea)
    {
        $tarea->update(['archivada' => false]);

        KanbanActividadLog::registrar($tarea->tablero_id, $tarea->id, 'updated',
            "Tarea «{$tarea->titulo}» desarchivada");

        return response()->json(['success' => true]);
    }

    public function tareasArchivadas(KanbanTablero $kanban)
    {
        $tareas = $kanban->tareas()
            ->where('archivada', true)
            ->with(['columna', 'asignados', 'etiquetas'])
            ->orderByDesc('updated_at')
            ->get();

        return view('kanban.archivadas', compact('kanban', 'tareas'));
    }

    // =====================================================
    // EXPORTAR PDF
    // =====================================================

    public function exportarPdf(KanbanTablero $kanban)
    {
        $kanban->load([
            'columnas.tareas' => fn ($q) => $q->where('archivada', false)->with(['asignados', 'etiquetas']),
            'creador', 'centroCosto',
        ]);

        $totalTareas  = $kanban->columnas->flatMap->tareas->count();
        $tareasAlta   = $kanban->columnas->flatMap->tareas->where('prioridad', 'ALTA')->count();
        $tareasVenc   = $kanban->columnas->flatMap->tareas->filter(fn ($t) => $t->estaVencida)->count();

        $pdf = Pdf::loadView('kanban.pdf_reporte', compact('kanban', 'totalTareas', 'tareasAlta', 'tareasVenc'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("kanban-{$kanban->id}-" . now()->format('Y-m-d') . '.pdf');
    }

    // =====================================================
    // DASHBOARD / ANALYTICS
    // =====================================================

    public function dashboard()
    {
        $userId   = auth()->id();
        $tableros = KanbanTablero::where('activo', true)->visiblesParaUsuario()->pluck('id');

        // Stats generales
        $totalTareas   = KanbanTarea::whereIn('tablero_id', $tableros)->where('archivada', false)->count();
        $tareasAlta    = KanbanTarea::whereIn('tablero_id', $tableros)->where('archivada', false)->where('prioridad', 'ALTA')->count();
        $tareasVencidas= KanbanTarea::whereIn('tablero_id', $tableros)->where('archivada', false)
            ->whereNotNull('fecha_vencimiento')->where('fecha_vencimiento', '<', now())->count();
        $misTareas     = KanbanTarea::whereIn('tablero_id', $tableros)->where('archivada', false)
            ->whereHas('asignados', fn ($q) => $q->where('user_id', $userId))->count();

        // Tareas por columna (agrupado)
        $porColumna = DB::table('kanban_tareas')
            ->join('kanban_columnas', 'kanban_tareas.columna_id', '=', 'kanban_columnas.id')
            ->whereIn('kanban_tareas.tablero_id', $tableros)
            ->where('kanban_tareas.archivada', false)
            ->select('kanban_columnas.nombre', 'kanban_columnas.color', DB::raw('COUNT(*) as total'))
            ->groupBy('kanban_columnas.nombre', 'kanban_columnas.color')
            ->orderByDesc('total')
            ->get();

        // Tareas por prioridad
        $porPrioridad = KanbanTarea::whereIn('tablero_id', $tableros)
            ->where('archivada', false)
            ->selectRaw("prioridad, COUNT(*) as total")
            ->groupBy('prioridad')
            ->pluck('total', 'prioridad');

        // Carga por usuario (top 10)
        $cargaUsuarios = DB::table('kanban_tarea_asignados')
            ->join('kanban_tareas', 'kanban_tarea_asignados.tarea_id', '=', 'kanban_tareas.id')
            ->join('users', 'kanban_tarea_asignados.user_id', '=', 'users.id')
            ->whereIn('kanban_tareas.tablero_id', $tableros)
            ->where('kanban_tareas.archivada', false)
            ->select('users.name', DB::raw('COUNT(*) as total'))
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Tareas completadas últimos 30 días (las que están en la última columna)
        $completadasMes = KanbanTarea::whereIn('kanban_tareas.tablero_id', $tableros)
            ->where('archivada', false)
            ->whereHas('columna', function ($q) {
                $q->whereRaw('kanban_columnas.orden = (SELECT MAX(c2.orden) FROM kanban_columnas c2 WHERE c2.tablero_id = kanban_columnas.tablero_id)');
            })
            ->where('kanban_tareas.updated_at', '>=', now()->subDays(30))
            ->count();

        // Próximas a vencer (7 días)
        $proximasVencer = KanbanTarea::whereIn('tablero_id', $tableros)
            ->where('archivada', false)
            ->whereBetween('fecha_vencimiento', [now(), now()->addDays(7)])
            ->with(['tablero', 'asignados', 'columna'])
            ->orderBy('fecha_vencimiento')
            ->limit(10)
            ->get();

        // Actividad reciente (últimos 20)
        $actividadReciente = KanbanActividadLog::whereIn('tablero_id', $tableros)
            ->with(['usuario', 'tarea'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('kanban.dashboard', compact(
            'totalTareas', 'tareasAlta', 'tareasVencidas', 'misTareas',
            'porColumna', 'porPrioridad', 'cargaUsuarios', 'completadasMes',
            'proximasVencer', 'actividadReciente'
        ));
    }
}
