<?php

namespace App\Http\Controllers;

use App\Models\MailLog;
use Illuminate\Http\Request;

class MailLogController extends Controller
{
    public function index(Request $request)
    {
        $query = MailLog::query()->orderByDesc('sent_at');

        $hasFilters = $request->hasAny(['status', 'desde', 'hasta', 'buscar', 'mailable']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('mailable')) {
            $query->where('mailable', $request->mailable);
        }
        if ($request->filled('buscar')) {
            $b = str_replace(['%', '_'], ['\%', '\_'], $request->buscar);
            $query->where(function ($q) use ($b) {
                $q->where('to_email', 'like', "%{$b}%")
                  ->orWhere('subject', 'like', "%{$b}%")
                  ->orWhere('to_name', 'like', "%{$b}%");
            });
        }
        if ($request->filled('desde')) {
            $query->whereDate('sent_at', '>=', $request->desde);
        } elseif (!$hasFilters) {
            $query->whereDate('sent_at', '>=', today());
        }
        if ($request->filled('hasta')) {
            $query->whereDate('sent_at', '<=', $request->hasta);
        }

        $logs = $query->paginate(50)->withQueryString();

        $stats = [
            'total'   => MailLog::count(),
            'sent'    => MailLog::where('status', 'sent')->count(),
            'failed'  => MailLog::where('status', 'failed')->count(),
            'hoy'     => MailLog::whereDate('sent_at', today())->count(),
            'hoy_err' => MailLog::where('status', 'failed')->whereDate('sent_at', today())->count(),
        ];

        $mailables = MailLog::whereNotNull('mailable')
            ->distinct()
            ->orderBy('mailable')
            ->pluck('mailable');

        return view('mail_logs.index', compact('logs', 'stats', 'mailables'));
    }

    public function show(MailLog $mailLog)
    {
        return view('mail_logs.show', compact('mailLog'));
    }

    public function limpiar(Request $request)
    {
        $request->validate([
            'dias' => 'required|integer|min:1|max:365',
        ]);

        $eliminados = MailLog::where('created_at', '<', now()->subDays($request->dias))->delete();

        return back()->with('success', "Se eliminaron {$eliminados} registros con más de {$request->dias} días de antigüedad.");
    }
}
