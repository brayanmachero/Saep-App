<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermisoController extends Controller
{
    public function index()
    {
        $roles   = Rol::orderBy('nombre')->get();
        $modulos = Modulo::where('activo', true)->orderBy('orden')->get()->groupBy('grupo');

        // Cargar permisos actuales
        $permisos = DB::table('rol_modulo')
            ->get()
            ->groupBy('rol_id')
            ->map(fn ($items) => $items->keyBy('modulo_id'));

        return view('permisos.index', compact('roles', 'modulos', 'permisos'));
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
}
