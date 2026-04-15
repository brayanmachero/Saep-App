<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermisoController extends Controller
{
    public function index()
    {
        $roles   = Rol::orderBy('nombre')->get();
        $modulos = Modulo::where('activo', true)->orderBy('orden')->get()->groupBy('grupo');
        $todosModulos = Modulo::where('activo', true)->orderBy('grupo')->orderBy('orden')->get();
        $grupos  = Modulo::where('activo', true)->distinct()->pluck('grupo')->sort()->values();

        // Cargar permisos actuales
        $permisos = DB::table('rol_modulo')
            ->get()
            ->groupBy('rol_id')
            ->map(fn ($items) => $items->keyBy('modulo_id'));

        return view('permisos.index', compact('roles', 'modulos', 'todosModulos', 'grupos', 'permisos'));
    }

    public function update(Request $request)
    {
        $data = $request->input('permisos', []);

        $roles   = Rol::all();
        $modulos = Modulo::where('activo', true)->get();

        DB::beginTransaction();

        foreach ($roles as $rol) {
            foreach ($modulos as $modulo) {
                $key = "{$rol->id}_{$modulo->id}";
                $puedeVer      = !empty($data[$key]['ver']);
                $puedeCrear    = !empty($data[$key]['crear']);
                $puedeEditar   = !empty($data[$key]['editar']);
                $puedeEliminar = !empty($data[$key]['eliminar']);

                DB::table('rol_modulo')
                    ->updateOrInsert(
                        ['rol_id' => $rol->id, 'modulo_id' => $modulo->id],
                        [
                            'puede_ver'       => $puedeVer,
                            'puede_crear'     => $puedeCrear,
                            'puede_editar'    => $puedeEditar,
                            'puede_eliminar'  => $puedeEliminar,
                            'updated_at'      => now(),
                        ]
                    );
            }
        }

        DB::commit();

        return redirect()->route('permisos.index')->with('success', 'Permisos actualizados correctamente.');
    }

    /**
     * Crear un nuevo rol y auto-alimentar la tabla rol_modulo.
     */
    public function storeRol(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'codigo' => 'nullable|string|max:50|unique:roles,codigo',
        ]);

        $codigo = $request->codigo
            ? Str::upper(Str::slug($request->codigo, '_'))
            : Str::upper(Str::slug($request->nombre, '_'));

        // Verificar unicidad del código generado
        if (Rol::where('codigo', $codigo)->exists()) {
            return back()->with('error', "Ya existe un rol con el código «{$codigo}».")->withInput();
        }

        DB::beginTransaction();

        $rol = Rol::create([
            'nombre' => $request->nombre,
            'codigo' => $codigo,
        ]);

        // Auto-alimentar rol_modulo con todos los módulos activos (sin permisos)
        $modulos = Modulo::where('activo', true)->get();
        $rows = $modulos->map(fn ($m) => [
            'rol_id'          => $rol->id,
            'modulo_id'       => $m->id,
            'puede_ver'       => false,
            'puede_crear'     => false,
            'puede_editar'    => false,
            'puede_eliminar'  => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ])->toArray();

        DB::table('rol_modulo')->insert($rows);

        DB::commit();

        return redirect()->route('permisos.index')
            ->with('success', "Rol «{$rol->nombre}» creado. Configure sus permisos en la tabla inferior.");
    }

    /**
     * Actualizar nombre/código de un rol existente.
     */
    public function updateRol(Request $request, Rol $rol)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'codigo' => 'required|string|max:50|unique:roles,codigo,' . $rol->id,
        ]);

        $rol->update([
            'nombre' => $request->nombre,
            'codigo' => Str::upper(Str::slug($request->codigo, '_')),
        ]);

        return redirect()->route('permisos.index')->with('success', "Rol «{$rol->nombre}» actualizado.");
    }

    /**
     * Eliminar un rol (solo si no tiene usuarios asignados).
     */
    public function destroyRol(Rol $rol)
    {
        if ($rol->codigo === 'SUPER_ADMIN') {
            return back()->with('error', 'No se puede eliminar el rol Super Admin.');
        }

        if ($rol->users()->count() > 0) {
            return back()->with('error', "El rol «{$rol->nombre}» tiene {$rol->users()->count()} usuario(s) asignado(s). Reasígnelos antes de eliminar.");
        }

        DB::table('rol_modulo')->where('rol_id', $rol->id)->delete();
        $rol->delete();

        return redirect()->route('permisos.index')->with('success', "Rol «{$rol->nombre}» eliminado.");
    }

    // =====================================================
    // MÓDULOS CRUD
    // =====================================================

    public function storeModulo(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'slug'   => 'nullable|string|max:80|unique:modulos,slug',
            'grupo'  => 'required|string|max:80',
            'icono'  => 'nullable|string|max:60',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $slug = $request->slug
            ? Str::slug($request->slug, '_')
            : Str::slug($request->nombre, '_');

        if (Modulo::where('slug', $slug)->exists()) {
            return back()->with('error', "Ya existe un módulo con el slug «{$slug}».")->withInput();
        }

        $maxOrden = Modulo::where('grupo', $request->grupo)->max('orden') ?? 0;

        DB::beginTransaction();

        $modulo = Modulo::create([
            'nombre'      => $request->nombre,
            'slug'        => $slug,
            'grupo'       => $request->grupo,
            'icono'       => $request->icono ?: 'bi-grid',
            'descripcion' => $request->descripcion,
            'orden'       => $maxOrden + 1,
            'activo'      => true,
        ]);

        // Auto-alimentar rol_modulo para todos los roles existentes
        $roles = Rol::all();
        $rows = $roles->map(fn ($r) => [
            'rol_id'          => $r->id,
            'modulo_id'       => $modulo->id,
            'puede_ver'       => false,
            'puede_crear'     => false,
            'puede_editar'    => false,
            'puede_eliminar'  => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ])->toArray();

        DB::table('rol_modulo')->insert($rows);

        DB::commit();

        return redirect()->route('permisos.index')
            ->with('success', "Módulo «{$modulo->nombre}» creado. Configure sus permisos en la matriz.");
    }

    public function updateModulo(Request $request, Modulo $modulo)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'slug'   => 'required|string|max:80|unique:modulos,slug,' . $modulo->id,
            'grupo'  => 'required|string|max:80',
            'icono'  => 'nullable|string|max:60',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $modulo->update([
            'nombre'      => $request->nombre,
            'slug'        => Str::slug($request->slug, '_'),
            'grupo'       => $request->grupo,
            'icono'       => $request->icono ?: 'bi-grid',
            'descripcion' => $request->descripcion,
        ]);

        return redirect()->route('permisos.index')->with('success', "Módulo «{$modulo->nombre}» actualizado.");
    }

    public function destroyModulo(Modulo $modulo)
    {
        // Desactivar en lugar de eliminar (soft-delete lógico)
        $modulo->update(['activo' => false]);
        DB::table('rol_modulo')->where('modulo_id', $modulo->id)->delete();

        return redirect()->route('permisos.index')->with('success', "Módulo «{$modulo->nombre}» desactivado.");
    }
}
