<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MisFormulariosController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $query = DB::table('formulario_usuario')
            ->join('formularios', 'formularios.id', '=', 'formulario_usuario.formulario_id')
            ->where('formulario_usuario.user_id', $userId)
            ->select(
                'formulario_usuario.*',
                'formularios.nombre',
                'formularios.codigo',
                'formularios.descripcion',
                'formularios.categoria_id',
            );

        if ($request->filled('estado')) {
            $query->where('formulario_usuario.estado', $request->estado);
        }

        if ($request->filled('buscar')) {
            $q = str_replace(['%', '_'], ['\%', '\_'], $request->buscar);
            $query->where(function ($w) use ($q) {
                $w->where('formularios.nombre', 'like', "%$q%")
                  ->orWhere('formularios.codigo', 'like', "%$q%");
            });
        }

        $asignaciones = $query->orderByRaw("FIELD(formulario_usuario.estado, 'Pendiente', 'Vencido', 'Completado')")
            ->orderBy('formulario_usuario.fecha_limite')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'pendientes'  => DB::table('formulario_usuario')->where('user_id', $userId)->where('estado', 'Pendiente')->count(),
            'completados' => DB::table('formulario_usuario')->where('user_id', $userId)->where('estado', 'Completado')->count(),
            'vencidos'    => DB::table('formulario_usuario')->where('user_id', $userId)->where('estado', 'Vencido')->count(),
        ];

        return view('mis-formularios.index', compact('asignaciones', 'stats'));
    }
}
