<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Interactive, one-document pilot. Never signs on behalf of a worker. */
class RrhhDocumentLivePilot
{
    public function context(string $id, bool $writing = false): array
    {
        $batch = DB::table('rrhh_document_batches')->where('id', $id)->first();
        abort_unless($batch, 404);
        if (!config('services.talana_documents.pilot')) throw new RuntimeException('El piloto interactivo no está habilitado.');
        if (!in_array($batch->estado, ['BORRADOR', 'EN_PROCESO'])) throw new RuntimeException('Este lote no admite operaciones.');
        $items = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->where('excluido', false)->get();
        $docs = json_decode($batch->battery_snapshot, true)['documentos'];
        if ($items->count() !== 1 || count($docs) !== 1 || !str_starts_with($docs[0], 'SAEP-PILOTO-') || !str_contains($docs[0], 'SIN VALIDEZ')) {
            throw new RuntimeException('La prueba en vivo requiere un trabajador y un PDF de prueba identificado como SAEP-PILOTO y SIN VALIDEZ.');
        }
        $item = $items->first();
        $person = json_decode($item->snapshot, true);
        if (!in_array(($person['empresa_id'] ?? '').':'.($person['persona_talana_id'] ?? ''), config('services.talana_documents.pilot_recipients', []), true)) throw new RuntimeException('El destinatario no está autorizado para este piloto.');
        if ($writing) app(RrhhDocumentPilot::class)->assertRecipient($person);
        $file = DB::table('rrhh_document_deliveries')->where('item_id', $item->id)->where('position', 0)->first();
        $until = config('services.talana_documents.pilot_until');
        $windowOpen = $until && now()->lt(\Carbon\Carbon::parse($until));
        return compact('batch', 'item', 'person', 'file', 'windowOpen') + ['name' => $docs[0]];
    }

    public function run(string $id, string $action, int $actor): string
    {
        $ctx = $this->context($id, $action !== 'refresh');
        $file = $ctx['file'];
        if (!$file) throw new RuntimeException('Primero guarda el PDF en este lote.');
        if ($action !== 'refresh' && !config('services.talana_documents.writes')) throw new RuntimeException('Los envíos están deshabilitados.');
        $api = app(TalanaDocumentApi::class);
        if ($action === 'upload') {
            if ($file->state !== 'preparado') throw new RuntimeException('La carga ya se inició. Actualiza el estado; no vuelvas a enviarla.');
            $this->event($id, $actor, 'Carga solicitada desde SAEP', 'Carga de un PDF de prueba, sin solicitud de firma.');
            $code = Artisan::call('rrhh:documentos', ['lote' => $id, '--ejecutar' => true]);
            if ($code !== 0) throw new RuntimeException('No se confirmó la carga. Revisa el estado y el historial antes de repetir.');
            $result = DB::table('rrhh_document_deliveries')->find($file->id);
            if ($result->state !== 'cargado') throw new RuntimeException('La carga requiere conciliación. No la repitas.');
            $message = 'PDF cargado en Talana. Todavía no se ha solicitado firma.';
        } elseif ($action === 'signature') {
            if ($file->state !== 'cargado' || $file->signature_id) throw new RuntimeException('La firma ya fue solicitada o el documento no admite esta operación.');
            if (Artisan::call('rrhh:documentos', ['lote' => $id]) !== 0) throw new RuntimeException('El lote no supera la revisión previa.');
            $remote = $api->document($file->document_id, (int)$ctx['person']['empresa_id'])->throw()->json();
            if ((int)($remote['empleado'] ?? 0) !== (int)$ctx['person']['persona_talana_id'] || ($remote['nombre'] ?? '') !== '[PRUEBA SAEP] '.$ctx['name']) throw new RuntimeException('El destinatario o documento remoto no coincide.');
            if (!hash_equals($file->sha256, hash('sha256', $api->previewPdf($remote['adjunto'])))) throw new RuntimeException('El PDF remoto cambió. Revisa el documento.');
            $existing = $api->signatures($file->document_id)->throw()->json();
            if (!isset($existing['results']) || count($existing['results']) || !empty($existing['next'])) throw new RuntimeException('Hay una solicitud previa o la consulta no es concluyente. No se enviará otra.');
            app(RrhhDocumentPilot::class)->assertRecipient($ctx['person']);
            $claimed = DB::table('rrhh_document_deliveries')->where('id', $file->id)->where('state', 'cargado')->whereNull('signature_id')->update(['state'=>'solicitando_firma', 'updated_at'=>now()]);
            if (!$claimed) throw new RuntimeException('Otra operación ya inició la solicitud.');
            $this->event($id, $actor, 'Firma solicitada desde SAEP', 'Solicitud explícita al trabajador del PDF de prueba; sin contrapartes.');
            $status = null;
            try {
                $response = $api->requestSignature($file->document_id);
                $status = $response->status();
                $signatureId = filter_var($response->json('sr_id'), FILTER_VALIDATE_INT);
                if (!$response->successful() || !$signatureId || $signatureId < 1) throw new RuntimeException('Respuesta no confirmada.');
                DB::table('rrhh_document_deliveries')->where('id', $file->id)->update(['state'=>'firma_solicitada', 'signature_id'=>$signatureId, 'http_status'=>$status, 'updated_at'=>now()]);
            } catch (\Throwable $e) {
                DB::table('rrhh_document_deliveries')->where('id', $file->id)->update(['state'=>'revisar_firma', 'http_status'=>$status, 'updated_at'=>now()]);
                throw new RuntimeException('Talana no confirmó la solicitud. No repitas el envío; requiere conciliación.');
            }
            $message = 'Solicitud de firma creada. El trabajador debe comprobar sus pendientes en Talana.';
        } elseif ($action === 'refresh') {
            if (!$file->document_id) throw new RuntimeException('Todavía no hay un documento remoto que consultar.');
            $remote = $api->document($file->document_id, (int)$ctx['person']['empresa_id'])->throw()->json();
            if ((int)($remote['empleado'] ?? 0) !== (int)$ctx['person']['persona_talana_id']) throw new RuntimeException('No se confirmó el destinatario remoto.');
            $message = 'Documento localizado en la ficha de Talana. No se ha confirmado una solicitud de firma.';
            if ($file->signature_id) {
                $rows = $api->signatures($file->document_id)->throw()->json('results') ?? [];
                $match = collect($rows)->first(fn($r) => (int)($r['id'] ?? 0) === (int)$file->signature_id && (string)($r['documentReference'] ?? '') === (string)$file->document_id);
                $detail = collect($match['details'] ?? [])->first(fn($r) => (int)($r['requestedUser'] ?? 0) === (int)$ctx['person']['persona_talana_id']);
                if (!$match || !$detail) throw new RuntimeException('La consulta no localizó la solicitud y el firmante. Se conserva el estado anterior.');
                $signed = filter_var($match['signed'] ?? false, FILTER_VALIDATE_BOOLEAN) && filter_var($detail['signed'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $state = $signed ? 'firmado' : match ($detail['status'] ?? '') { 'P','E','FR','SC' => 'firma_solicitada', 'R' => 'firma_rechazada', 'C' => 'firma_cancelada', default => null };
                if (!$state) throw new RuntimeException('Talana devolvió un estado no reconocido. Se conserva el estado anterior.');
                DB::table('rrhh_document_deliveries')->where('id', $file->id)->update(['state'=>$state, 'updated_at'=>now()]);
                $message = match($state) { 'firmado'=>'Talana confirma la firma.', 'firma_rechazada'=>'Talana indica firma rechazada.', 'firma_cancelada'=>'Talana indica solicitud cancelada.', default=>'Talana confirma que la firma todavía está pendiente.' };
            }
        } else throw new RuntimeException('Acción no válida.');
        $this->event($id, $actor, $action === 'refresh' ? 'Estado consultado en Talana' : 'Resultado de operación Talana', $message);
        return $message;
    }

    private function event(string $id, int $actor, string $action, string $message): void
    {
        DB::table('rrhh_document_batch_events')->insert(['batch_id'=>$id, 'actor_id'=>$actor, 'accion'=>$action, 'detalle'=>json_encode(['motivo'=>$message], JSON_UNESCAPED_UNICODE), 'created_at'=>now()]);
    }
}
