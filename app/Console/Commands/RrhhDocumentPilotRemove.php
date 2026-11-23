<?php

namespace App\Console\Commands;

use App\Services\RrhhDocumentPilot;
use App\Services\TalanaDocumentApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RrhhDocumentPilotRemove extends Command
{
    protected $signature = 'rrhh:retirar-prueba {entrega : ID interno de entrega, no ID de documento Talana} {--confirmar : Eliminar solo el documento de prueba cargado y sin firma solicitada}';
    protected $description = 'Verifica y retira un documento del piloto sin borrar su historial local.';

    public function handle(TalanaDocumentApi $api): int
    {
        $claimed = false;
        $row = null;
        try {
            if (!config('services.talana_documents.pilot') || !config('services.talana_documents.writes')) throw new RuntimeException('El piloto no está habilitado.');
            $row = DB::table('rrhh_document_deliveries as d')->join('rrhh_document_batch_items as i','i.id','=','d.item_id')
                ->where('d.id',$this->argument('entrega'))->select('d.*','i.snapshot','i.batch_id')->first();
            if (!$row || $row->state !== 'cargado' || !$row->document_id || $row->signature_id) throw new RuntimeException('Solo se retiran cargas de prueba confirmadas sin solicitud de firma.');
            $snapshot = json_decode($row->snapshot,true);
            app(RrhhDocumentPilot::class)->assertRecipient($snapshot);
            $doc = $api->document((int)$row->document_id,(int)$snapshot['empresa_id'])->throw()->json();
            if ((int)($doc['empleado'] ?? 0) !== (int)$snapshot['persona_talana_id'] || !str_starts_with($doc['nombre'] ?? '', '[PRUEBA SAEP] ')) throw new RuntimeException('El documento remoto no coincide con el destinatario o no es de prueba.');
            if (!$this->option('confirmar')) {
                $this->info('Documento de prueba localizado. Para retirarlo usa --confirmar. Talana rechazará documentos firmados.');
                return self::SUCCESS;
            }
            $claimed = DB::table('rrhh_document_deliveries')->where('id',$row->id)->where('state','cargado')->whereNull('signature_id')->update(['state'=>'retirando_prueba','updated_at'=>now()]) === 1;
            if (!$claimed) throw new RuntimeException('La entrega cambió. Revisa su estado.');
            $response = $api->deleteDocument((int)$row->document_id);
            if ($response->status() !== 204) throw new RuntimeException('Talana no confirmó el retiro.');
            $check = $api->document((int)$row->document_id,(int)$snapshot['empresa_id']);
            if ($check->status() !== 404) throw new RuntimeException('Falta confirmar la ausencia del documento retirado.');
            DB::transaction(function () use ($row) {
                DB::table('rrhh_document_deliveries')->where('id',$row->id)->update(['state'=>'retirado_prueba','updated_at'=>now()]);
                DB::table('rrhh_document_batch_events')->insert(['batch_id'=>$row->batch_id,'actor_id'=>0,'accion'=>'Retiro de prueba',
                    'detalle'=>json_encode(['entrega_id'=>$row->id,'document_id'=>$row->document_id,'motivo'=>'Retiro de documento de prueba confirmado por Talana (204 y consulta 404).']), 'created_at'=>now()]);
            });
            $this->info('Retiro confirmado. Se conserva la evidencia local y el ID de Talana.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            if ($claimed) DB::table('rrhh_document_deliveries')->where('id',$row->id)->update(['state'=>'revisar_retiro','updated_at'=>now()]);
            $this->error($e instanceof RuntimeException && !($e instanceof \Illuminate\Http\Client\RequestException) ? $e->getMessage() : 'No se pudo confirmar la operación. Revisar en Talana antes de repetir.');
            return self::FAILURE;
        }
    }
}
