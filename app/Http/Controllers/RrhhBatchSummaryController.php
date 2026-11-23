<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RrhhBatchSummaryController extends Controller
{
    public function show(Request $request, string $id)
    {
        $batch = DB::table('rrhh_document_batches')->find($id);
        abort_unless($batch, 404);
        $items = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->where('excluido', false)->get();
        $battery = json_decode($batch->battery_snapshot, true);
        $count = count($battery['documentos']);
        $states = DB::table('rrhh_document_deliveries')->whereIn('item_id', $items->pluck('id'))
            ->where('position', '>=', 0)->where('position', '<', $count)
            ->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');
        $expected = $items->count() * $count;
        $missing = max(0, $expected - $states->sum());
        $observed = $items->filter(fn ($i) => count(json_decode($i->observaciones, true)) > 0)->count();
        $companies = $items->map(fn ($i) => json_decode($i->snapshot, true)['empresa_nombre'])->unique()->implode(', ');
        $labels = ['preparado'=>'Preparados en SAEP', 'cargando'=>'Carga en curso', 'cargado'=>'Cargados en Talana sin firma solicitada',
            'solicitando_firma'=>'Solicitud de firma en curso', 'firma_solicitada'=>'Firma solicitada, pendiente de confirmación',
            'firmado'=>'Firma confirmada por Talana', 'revisar_carga'=>'Carga por revisar', 'revisar_firma'=>'Firma por revisar', 'retirando_prueba'=>'Retiro de prueba en curso', 'retirado_prueba'=>'Pruebas retiradas de Talana', 'revisar_retiro'=>'Retiro por conciliar'];
        $completed = $expected > 0 && (int)($states['firmado'] ?? 0) === $expected && !$observed && $batch->estado !== 'CANCELADO';
        $subject = 'Gestión documental · '.$batch->nombre;
        $lines = ['Hola Cristian,', '', 'Resumen del lote: '.$batch->nombre, 'Identificador: '.$batch->id,
            'Corte del informe: '.now()->timezone('America/Santiago')->format('d/m/Y H:i').' (Chile)',
            'Empresa(s): '.($companies ?: 'Sin contratos incluidos'), 'Batería: '.$battery['nombre'].' · versión '.$battery['version'],
            'Estado del lote: '.$batch->estado,
            'Resultado documental: '.($completed ? 'Todos los documentos tienen firma confirmada.' : 'Pendiente de completar / revisar.'),
            'Contratos incluidos: '.$items->count(), 'Documentos previstos: '.$expected,
            'Sin PDF registrado: '.$missing, 'Contratos con observaciones: '.$observed, ''];
        foreach ($labels as $state=>$label) $lines[] = $label.': '.($states[$state] ?? 0);
        foreach ($states as $state=>$total) if (!isset($labels[$state])) $lines[] = 'Estado adicional '.$state.': '.$total;
        $lines = array_merge($lines, ['', 'Los contratos retirados no se contabilizan. Cargado no significa firmado.',
            'La confirmación de firma corresponde a la última consulta registrada; este informe no consulta firmas en tiempo real.',
            'Revisar detalle: '.route('rrhh-documentos.show', $id)]);
        $body = implode("\n", $lines);
        if ($request->query('formato') === 'eml') {
            $mail = 'To: chormazabal@saep.cl'."\r\n".'Subject: =?UTF-8?B?'.base64_encode($subject)."?=\r\n".
                "X-Unsent: 1\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".
                chunk_split(base64_encode($body));
            return response($mail)->header('Content-Type','message/rfc822')->header('Content-Disposition','attachment; filename="resumen-documental.eml"')->header('Cache-Control','private, no-store');
        }
        return response()->view('rrhh_documentos.summary',compact('batch','subject','body'))->header('Cache-Control','private, no-store');
    }
}
