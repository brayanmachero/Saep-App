<?php

namespace App\Http\Controllers;

use App\Mail\InspeccionPreventivaPdrReporteMail;
use App\Services\InspeccionPreventivaPdrAnalyticsService;
use App\Services\InspeccionPreventivaPdrExcelExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class InspeccionPreventivaPdrDashboardController extends Controller
{
    public function __construct(private readonly InspeccionPreventivaPdrAnalyticsService $analytics)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->filtersFromRequest($request, true);

        return view('inspecciones-preventivas-dashboard.index', [
            'analytics' => $this->analytics->getFilteredAnalytics($filters),
            'filters' => $filters,
            'syncInfo' => $this->analytics->getSyncInfo(),
            'hasData' => $this->analytics->hasSyncedData(),
        ]);
    }

    public function sync()
    {
        try {
            Artisan::call('kizeo:sync-inspecciones-preventivas', ['--force' => true]);
            return back()->with('success', trim(Artisan::output()) ?: 'Datos de inspecciones preventivas sincronizados.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No fue posible sincronizar las inspecciones preventivas: ' . $e->getMessage());
        }
    }

    public function downloadExcel(Request $request)
    {
        $filters = $this->filtersFromRequest($request, false);
        $analytics = $this->analytics->getFilteredAnalytics($filters);
        if (($analytics['total'] ?? 0) === 0) {
            return back()->with('error', 'No hay inspecciones con los filtros seleccionados para exportar.');
        }
        $path = (new InspeccionPreventivaPdrExcelExport())->generate($analytics, $this->analytics->getFilteredRecords($filters), $filters);
        return response()->download($path, 'inspecciones_preventivas_pdr_' . now()->format('Ymd_His') . '.xlsx')->deleteFileAfterSend(true);
    }

    public function emailPreview(Request $request)
    {
        [$analytics, $records, $filters] = $this->reportData($request);
        if (($analytics['total'] ?? 0) === 0) {
            return redirect()->route('pdr-inspecciones-dashboard.index', $filters)->with('error', 'No hay inspecciones para generar el correo.');
        }
        $mailable = $this->reportMailable($analytics, $records, $filters, (string) ($request->user()?->name ?? 'Usuario SAEP'), false);
        return view('inspecciones-preventivas-dashboard.reporte-preview', ['emailHtml' => $mailable->render(), 'filters' => $filters]);
    }

    public function sendToCurrentUser(Request $request)
    {
        $user = $request->user();
        $email = trim((string) ($user?->email ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'Tu usuario no tiene un correo válido para recibir el reporte.');
        }
        [$analytics, $records, $filters] = $this->reportData($request);
        if (($analytics['total'] ?? 0) === 0) {
            return back()->with('error', 'No hay inspecciones para generar el correo.');
        }
        try {
            Mail::to($email)->send($this->reportMailable($analytics, $records, $filters, (string) ($user->name ?? 'Usuario SAEP')));
            return back()->with('success', "Reporte enviado a {$email} con el Excel adjunto.");
        } catch (\Throwable $e) {
            return back()->with('error', 'No fue posible enviar el reporte: ' . $e->getMessage());
        }
    }

    private function filtersFromRequest(Request $request, bool $defaultCurrentMonth): array
    {
        $keys = ['centro', 'objetivo', 'inspector_nombre', 'responsable_area', 'frecuencia', 'verificacion', 'fecha_desde', 'fecha_hasta', 'todo'];
        $clean = !$request->hasAny($keys);
        $filters = array_filter([
            'centro' => $request->input('centro'), 'objetivo' => $request->input('objetivo'),
            'inspector_nombre' => $request->input('inspector_nombre'), 'responsable_area' => $request->input('responsable_area'),
            'frecuencia' => $request->input('frecuencia'), 'verificacion' => $request->input('verificacion'),
            'fecha_desde' => $request->input('fecha_desde', $defaultCurrentMonth && $clean ? now()->startOfMonth()->toDateString() : null),
            'fecha_hasta' => $request->input('fecha_hasta', $defaultCurrentMonth && $clean ? now()->endOfMonth()->toDateString() : null),
        ], fn ($value) => $value !== null && $value !== '');
        if ($request->boolean('todo')) {
            $filters['todo'] = '1';
        }
        return $filters;
    }

    private function reportData(Request $request): array
    {
        $filters = $this->filtersFromRequest($request, true);
        return [$this->analytics->getFilteredAnalytics($filters), $this->analytics->getFilteredRecords($filters), $filters];
    }

    private function reportMailable(array $analytics, $records, array $filters, string $recipientName, bool $attachExcel = true): InspeccionPreventivaPdrReporteMail
    {
        return new InspeccionPreventivaPdrReporteMail($analytics, $records, $filters, route('pdr-inspecciones-dashboard.index', $filters), $recipientName, $attachExcel);
    }
}
