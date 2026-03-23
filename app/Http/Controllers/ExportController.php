<?php

namespace App\Http\Controllers;

use App\Models\Respuesta;
use App\Models\FirmaElectronica;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function respuestas(Request $request)
    {
        $query = Respuesta::with(['formulario','usuario','aprobaciones.aprobador'])
            ->when($request->estado, fn($q) => $q->where('estado', $request->estado))
            ->when($request->desde,  fn($q) => $q->whereDate('created_at', '>=', $request->desde))
            ->when($request->hasta,  fn($q) => $q->whereDate('created_at', '<=', $request->hasta))
            ->orderByDesc('created_at');

        $respuestas = $query->get();

        $filename = 'respuestas_' . now()->format('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($respuestas) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 para Excel
            fputcsv($handle, ['ID','Formulario','Usuario','Estado','Fecha','Aprobado por'], ';');
            foreach ($respuestas as $r) {
                $aprobador = $r->aprobaciones->where('resultado','Aprobado')->first()?->aprobador?->name ?? '-';
                fputcsv($handle, [
                    $r->id,
                    $r->formulario->nombre ?? '-',
                    $r->usuario->name ?? '-',
                    $r->estado,
                    $r->created_at->format('d/m/Y H:i'),
                    $aprobador,
                ], ';');
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function firmas(Request $request)
    {
        $firmas = FirmaElectronica::with('firmante')
            ->when($request->desde, fn($q) => $q->whereDate('created_at', '>=', $request->desde))
            ->when($request->hasta, fn($q) => $q->whereDate('created_at', '<=', $request->hasta))
            ->orderByDesc('created_at')
            ->get();

        $filename = 'firmas_' . now()->format('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($firmas) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['ID','Entidad Tipo','Entidad ID','Firmante','RUT','Email','Propósito','IP','Fecha'], ';');
            foreach ($firmas as $f) {
                fputcsv($handle, [
                    $f->id, $f->entidad_tipo, $f->entidad_id,
                    $f->firmante_nombre, $f->firmante_rut ?? '-',
                    $f->firmante_email ?? '-', $f->proposito,
                    $f->ip_address ?? '-',
                    $f->created_at->format('d/m/Y H:i'),
                ], ';');
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
