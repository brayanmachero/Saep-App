<?php

namespace App\Http\Controllers;

use App\Models\MailLog;
use App\Services\MailAutomationService;
use Illuminate\Http\Request;

class MailLogController extends Controller
{
    public function index(Request $request, MailAutomationService $mailAutomation)
    {
        $mailAutomation->ensureDefaults();

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
            'blocked' => MailLog::where('status', 'blocked')->count(),
            'hoy'     => MailLog::whereDate('sent_at', today())->count(),
            'hoy_err' => MailLog::where('status', 'failed')->whereDate('sent_at', today())->count(),
        ];

        $mailables = $mailAutomation->all();
        $mailAutomationGroups = collect($mailables)->groupBy('category');
        $mailGlobalEnabled = $mailAutomation->isGlobalEnabled();
        $mailables = MailLog::whereNotNull('mailable')
            ->distinct()
            ->orderBy('mailable')
            ->pluck('mailable')
            ->merge(collect($mailAutomation->all())->pluck('key'))
            ->unique()
            ->sort()
            ->values();

        return view('mail_logs.index', compact(
            'logs',
            'stats',
            'mailables',
            'mailAutomationGroups',
            'mailGlobalEnabled'
        ));
    }

    public function show(MailLog $mailLog)
    {
        return view('mail_logs.show', compact('mailLog'));
    }

    public function updateAutomation(Request $request, MailAutomationService $mailAutomation)
    {
        $request->validate([
            'global_enabled' => ['nullable', 'boolean'],
            'automations' => ['nullable', 'array'],
            'automations.*' => ['nullable', 'boolean'],
        ]);

        $mailAutomation->setGlobalEnabled($request->boolean('global_enabled'));
        $submitted = $request->input('automations', []);

        foreach ($mailAutomation->all() as $automation) {
            $mailAutomation->setEnabled(
                $automation['key'],
                filter_var($submitted[$automation['key']] ?? false, FILTER_VALIDATE_BOOLEAN)
            );
        }

        return back()->with('success', 'Automatizaciones de email actualizadas correctamente.');
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
