<?php

namespace App\Http\Controllers;

use App\Models\Formulario;
use App\Models\Respuesta;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_solicitudes'   => Respuesta::count(),
            'pendientes_aprobacion' => Respuesta::where('estado', 'Pendiente')->count(),
            'empleados_activos'   => User::where('activo', true)->count(),
            'accion_requerida'    => Respuesta::whereIn('estado', ['Revisión', 'Borrador'])->count(),
        ];

        $solicitudes_recientes = Respuesta::with(['formulario', 'usuario'])
            ->latest()
            ->take(10)
            ->get();

        // Formularios pendientes del usuario actual
        $mis_pendientes = DB::table('formulario_usuario')
            ->join('formularios', 'formularios.id', '=', 'formulario_usuario.formulario_id')
            ->where('formulario_usuario.user_id', auth()->id())
            ->where('formulario_usuario.estado', 'Pendiente')
            ->select('formulario_usuario.*', 'formularios.nombre', 'formularios.codigo')
            ->orderBy('formulario_usuario.fecha_limite')
            ->limit(5)
            ->get();

        return view('dashboard', compact('stats', 'solicitudes_recientes', 'mis_pendientes'));
    }
}
