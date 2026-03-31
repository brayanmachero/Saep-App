<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use Illuminate\Http\Request;

class WebhookLogController extends Controller
{
    public function index(Request $request)
    {
        $query = WebhookLog::query()->orderByDesc('created_at');

        // Filtros
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }
        if ($request->filled('buscar')) {
            $search = $request->buscar;
            $query->where(function ($q) use ($search) {
                $q->where('resumen', 'like', "%{$search}%")
                  ->orWhere('archivo', 'like', "%{$search}%")
                  ->orWhere('sharepoint_path', 'like', "%{$search}%")
                  ->orWhere('data_id', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        // Stats para las tarjetas superiores
        $stats = [
            'total'   => WebhookLog::count(),
            'success' => WebhookLog::where('estado', 'success')->count(),
            'error'   => WebhookLog::where('estado', 'error')->count(),
            'ignored' => WebhookLog::where('estado', 'ignored')->count(),
            'hoy'     => WebhookLog::whereDate('created_at', today())->count(),
        ];

        // Tipos únicos para el filtro
        $tipos = WebhookLog::select('tipo')->distinct()->pluck('tipo');

        return view('webhook_logs.index', compact('logs', 'stats', 'tipos'));
    }
}
