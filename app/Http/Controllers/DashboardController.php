<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        // Stats del usuario logueado
        $stats = [
            'pendientes'  => DB::table('formulario_usuario')->where('user_id', $userId)->where('estado', 'Pendiente')->count(),
            'completados' => DB::table('formulario_usuario')->where('user_id', $userId)->where('estado', 'Completado')->count(),
            'vencidos'    => DB::table('formulario_usuario')->where('user_id', $userId)->where('estado', 'Vencido')->count(),
        ];
        $stats['total'] = $stats['pendientes'] + $stats['completados'] + $stats['vencidos'];

        // Formularios disponibles: pendientes + continuos (sin fecha_fin y frecuencia != unica)
        $disponibles = DB::table('formulario_usuario')
            ->join('formularios', 'formularios.id', '=', 'formulario_usuario.formulario_id')
            ->where('formulario_usuario.user_id', $userId)
            ->where('formularios.activo', true)
            ->where(function ($q) {
                $q->where('formulario_usuario.estado', 'Pendiente')
                  ->orWhere(function ($sub) {
                      // Formularios continuos: sin fecha_fin y frecuencia != unica
                      $sub->whereNull('formularios.fecha_fin')
                          ->where('formularios.frecuencia', '!=', 'unica');
                  });
            })
            ->select(
                'formulario_usuario.*',
                'formularios.nombre',
                'formularios.codigo',
                'formularios.descripcion',
                'formularios.fecha_fin',
                'formularios.frecuencia'
            )
            ->orderByRaw("FIELD(formulario_usuario.estado, 'Pendiente', 'Completado')")
            ->orderBy('formulario_usuario.fecha_limite')
            ->get()
            ->unique('formulario_id');

        return view('dashboard', compact('stats', 'disponibles'));
    }
}
