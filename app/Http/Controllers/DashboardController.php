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

        // Formularios pendientes del usuario actual (todos, no solo 5)
        $mis_pendientes = DB::table('formulario_usuario')
            ->join('formularios', 'formularios.id', '=', 'formulario_usuario.formulario_id')
            ->where('formulario_usuario.user_id', $userId)
            ->where('formulario_usuario.estado', 'Pendiente')
            ->select('formulario_usuario.*', 'formularios.nombre', 'formularios.codigo', 'formularios.descripcion')
            ->orderBy('formulario_usuario.fecha_limite')
            ->get();

        return view('dashboard', compact('stats', 'mis_pendientes'));
    }
}
