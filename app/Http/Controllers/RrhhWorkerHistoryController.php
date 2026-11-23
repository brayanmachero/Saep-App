<?php

namespace App\Http\Controllers;

use App\Models\TalanaContrato;
use App\Services\RrhhWorkerHistoryService;
use App\Services\TalanaDocumentApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RrhhWorkerHistoryController extends Controller
{
    public function show(Request $request, int $contract, RrhhWorkerHistoryService $history)
    {
        $input = $request->validate(['tipo'=>'nullable|in:todos,contrato,anexo,otro','orden'=>'nullable|in:asc,desc',
            'q'=>'nullable|string|max:150','fecha_desde'=>'nullable|date_format:Y-m-d','fecha_hasta'=>'nullable|date_format:Y-m-d',
            'item'=>'nullable|integer|min:1']);
        if (!empty($input['fecha_desde']) && !empty($input['fecha_hasta']) && $input['fecha_hasta'] < $input['fecha_desde']) throw ValidationException::withMessages(['fecha_hasta'=>'La fecha hasta debe ser igual o posterior a desde.']);
        $worker = TalanaContrato::findOrFail($contract);
        abort_unless($worker->empresa_id && $worker->persona_talana_id,422);
        $item = !empty($input['item']) ? DB::table('rrhh_document_batch_items')->where('id',$input['item'])->where('contrato_id',$contract)->first() : null;
        abort_if(!empty($input['item']) && !$item,404);
        $batch = $item ? DB::table('rrhh_document_batches')->find($item->batch_id) : null;
        $contracts = $history->contracts($worker)->orderBy('desde',$input['orden'] ?? 'desc')->orderBy('id')->get();
        $error = null; $result = ['documents'=>[], 'complete'=>false, 'consulted_at'=>null];
        try { $result = $history->documents($worker); } catch (\Throwable $e) { $error = 'No se pudo consultar el historial documental de Talana. Intenta nuevamente.'; }
        $allDocuments = collect($result['documents']);
        $documents = $allDocuments->filter(function($d) use ($input) {
            if (($input['tipo'] ?? 'todos') !== 'todos' && $d['type'] !== $input['tipo']) return false;
            if (!empty($input['q']) && !str_contains(mb_strtolower($d['name']),mb_strtolower($input['q']))) return false;
            $date = substr($d['created_at'] ?? '',0,10);
            if (!empty($input['fecha_desde']) && (!$date || $date < $input['fecha_desde'])) return false;
            if (!empty($input['fecha_hasta']) && (!$date || $date > $input['fecha_hasta'])) return false;
            return true;
        })->sort(function($a,$b)use($input){
            if (!$a['created_at'] || !$b['created_at']) return empty($a['created_at']) <=> empty($b['created_at']);
            return (($input['orden'] ?? 'desc') === 'asc' ? 1 : -1) * strcmp($a['created_at'],$b['created_at']);
        });
        $reference = $item ? (json_decode($item->snapshot,true)['reference'] ?? null) : null;
        $canChange = $batch && $batch->estado === 'BORRADOR' && !$item->excluido
            && !DB::table('rrhh_document_deliveries')->where('item_id',$item->id)->exists();
        return response()->view('rrhh_documentos.worker-history',compact('input','worker','contracts','documents','allDocuments','result','error','item','batch','reference','canChange'))->header('Cache-Control','private, no-store');
    }

    public function pdf(Request $request, int $contract, int $document, TalanaDocumentApi $api)
    {
        $worker = TalanaContrato::findOrFail($contract);
        abort_unless($worker->empresa_id && $worker->persona_talana_id,422);
        try {
            $data = $api->document($document,(int)$worker->empresa_id)->throw()->json();
            if ((int)($data['empleado'] ?? 0) !== (int)$worker->persona_talana_id) return response('Documento no disponible para este trabajador.',404);
            $pdf = $api->previewPdf($data['adjunto'] ?? '');
        } catch (\Illuminate\Http\Client\HttpClientException|\RuntimeException $e) {
            abort(502,'No se pudo abrir el PDF. Reintenta desde el historial.');
        }
        return response($pdf)->header('Content-Type','application/pdf')->header('Content-Disposition','inline; filename="documento-trabajador.pdf"')->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
    }

    public function reference(Request $request, string $id, int $item, RrhhWorkerHistoryService $history, TalanaDocumentApi $api)
    {
        $data = $request->validate(['snapshot_hash'=>'required|string|size:64','source'=>'required|in:primero,ultimo,especifico,documento',
            'fecha_campo'=>'required_unless:source,documento|in:desde,fecha_contratacion','contract_id'=>'required_if:source,especifico|nullable|integer',
            'document_id'=>'required_if:source,documento|nullable|integer|min:1','fecha_efectiva'=>'required_if:source,documento|nullable|date_format:Y-m-d',
            'motivo'=>'required|string|min:5|max:1000','confirmed'=>'accepted']);
        $row = DB::table('rrhh_document_batch_items')->where('batch_id',$id)->find($item);
        abort_unless($row,404);
        $worker = TalanaContrato::findOrFail($row->contrato_id);
        if ($data['source'] === 'documento') {
            try {
                $doc = $api->document((int)$data['document_id'],(int)$worker->empresa_id)->throw()->json();
            } catch (\Illuminate\Http\Client\HttpClientException|\RuntimeException $e) {
                throw ValidationException::withMessages(['referencia'=>'No se pudo comprobar el documento en Talana. Intenta nuevamente.']);
            }
            abort_unless((int)($doc['empleado'] ?? 0) === (int)$worker->persona_talana_id,422);
            $reference = ['type'=>'documento','document_id'=>(int)$data['document_id'],'name'=>$doc['nombre'] ?? ('Documento #'.$data['document_id']),
                'company_id'=>(int)$worker->empresa_id,'employee_id'=>(int)$worker->persona_talana_id,
                'field'=>'vigencia_confirmada_manualmente','date'=>$data['fecha_efectiva'],'document_created_at'=>$doc['fechaCreacion'] ?? null];
        } else {
            $reference = $history->contractReference($worker,$data['source'],$data['fecha_campo'],$data['contract_id'] ?? null);
        }
        $reference = array_merge($reference,['confirmed'=>true,'confirmed_by'=>(int)$request->user()->id,'confirmed_at'=>now()->toIso8601String(),'reason'=>$data['motivo']]);
        DB::transaction(function()use($id,$item,$data,$reference,$request){
            $batch = DB::table('rrhh_document_batches')->where('id',$id)->lockForUpdate()->first();
            $row = DB::table('rrhh_document_batch_items')->where('batch_id',$id)->find($item);
            if (!$batch || !$row || $batch->estado !== 'BORRADOR' || $row->excluido || DB::table('rrhh_document_deliveries')->where('item_id',$item)->exists()) {
                throw ValidationException::withMessages(['referencia'=>'La referencia solo se modifica en borradores incluidos y antes de preparar sus PDF.']);
            }
            if (!hash_equals(hash('sha256',$row->snapshot),$data['snapshot_hash'])) throw ValidationException::withMessages(['referencia'=>'La referencia cambió. Recarga antes de confirmar.']);
            $snapshot = json_decode($row->snapshot,true);
            if ((int)$snapshot['empresa_id'] !== $reference['company_id'] || (int)$snapshot['persona_talana_id'] !== $reference['employee_id']) throw ValidationException::withMessages(['referencia'=>'El trabajador cambió desde la creación del lote. Revisa la selección.']);
            $before = $snapshot['reference'] ?? null; $snapshot['reference']=$reference;
            $warnings = array_values(array_filter(json_decode($row->observaciones,true),fn($w)=>$w !== 'Confirma la fecha de referencia de regularización'));
            DB::table('rrhh_document_batch_items')->where('id',$item)->update(['snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE),'observaciones'=>json_encode($warnings)]);
            $count = DB::table('rrhh_document_batch_items')->where('batch_id',$id)->where('excluido',false)->whereJsonLength('observaciones','>',0)->count();
            DB::table('rrhh_document_batches')->where('id',$id)->update(['observados'=>$count,'updated_at'=>now()]);
            DB::table('rrhh_document_batch_events')->insert(['batch_id'=>$id,'actor_id'=>$request->user()->id,'accion'=>'Referencia documental','detalle'=>json_encode(['item_id'=>$item,'anterior'=>$before,'referencia'=>$reference],JSON_UNESCAPED_UNICODE),'created_at'=>now()]);
        });
        return back()->with('success','Referencia guardada. Usa esta fecha al preparar el PDF personal; el sistema no modifica el contenido del archivo.');
    }
}
