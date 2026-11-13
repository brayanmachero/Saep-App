<?php

namespace App\Console\Commands;

use App\Services\RrhhDocumentDraftService;
use App\Services\TalanaDocumentApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RrhhDocumentDispatch extends Command
{
    protected $signature = 'rrhh:documentos {lote} {--consultar : Consultar firmas sin enviar documentos} {--ejecutar : Cargar PDFs en Talana} {--firmar : Solicitar firma de documentos cargados}';

    protected $description = 'Comprueba un lote documental y permite ejecutar su carga o firma por separado.';

    public function handle(TalanaDocumentApi $api): int
    {
        $write = $this->option('ejecutar') || $this->option('firmar');
        if ($this->option('consultar')) {
            if ($write || ! config('services.talana_documents.token')) {
                $this->error('La consulta requiere una credencial y debe ejecutarse sin opciones de escritura.');

                return self::FAILURE;
            }
            $id = $this->argument('lote');
            if (! DB::table('rrhh_document_batches')->where('id', $id)->exists()) {
                return self::FAILURE;
            }
            $rows = DB::table('rrhh_document_deliveries as d')->join('rrhh_document_batch_items as i', 'i.id', '=', 'd.item_id')->where('i.batch_id', $id)->where('d.state', 'firma_solicitada')->select('d.*')->orderBy('d.id')->cursor();
            $unconfirmed = 0;
            foreach ($rows as $row) {
                $response = $api->signatures($row->document_id);
                if (! $response->successful()) {
                    $this->error('Talana no pudo confirmar el estado.');

                    return self::FAILURE;
                }
                $match = collect($response->json('results') ?? [])->first(fn ($r) => is_array($r) && (string) ($r['id'] ?? '') === (string) $row->signature_id);
                if (! $match) {
                    $unconfirmed++;
                } elseif (($match['signed'] ?? false) === true) {
                    DB::table('rrhh_document_deliveries')->where('id', $row->id)->where('state', 'firma_solicitada')->update(['state' => 'firmado', 'updated_at' => now()]);
                }
            }
            $this->info("Solicitudes no localizadas en la respuesta: {$unconfirmed}.");

            return $unconfirmed ? self::FAILURE : self::SUCCESS;
        }
        if ($write && (! config('services.talana_documents.writes') || ! config('services.talana_documents.token'))) {
            $this->error('La escritura documental está deshabilitada o falta la credencial.');

            return self::FAILURE;
        }
        if ($this->option('ejecutar') && $this->option('firmar')) {
            $this->error('Ejecuta la carga y la firma en etapas separadas.');

            return self::FAILURE;
        }

        $id = $this->argument('lote');
        $batch = DB::transaction(function () use ($id, $write) {
            $batch = DB::table('rrhh_document_batches')->where('id', $id)->lockForUpdate()->first();
            if (! $batch || ! in_array($batch->estado, ['BORRADOR', 'EN_PROCESO'], true)) {
                throw new RuntimeException('El lote no existe o no admite procesamiento.');
            }
            $items = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->where('excluido', false);
            $docs = json_decode($batch->battery_snapshot, true)['documentos'];
            $expected = (clone $items)->count() * count($docs);
            $ready = DB::table('rrhh_document_deliveries')->whereIn('item_id', (clone $items)->select('id'))->count();
            $this->info("PDF preparados: {$ready} de {$expected}.");
            if (! $expected || $ready !== $expected || (clone $items)->whereJsonLength('observaciones', '>', 0)->exists()) {
                throw new RuntimeException('Faltan documentos o hay observaciones en el lote.');
            }
            foreach ((clone $items)->cursor() as $item) {
                $snap = json_decode($item->snapshot, true);
                $matches = (new RrhhDocumentDraftService)->query([])->where('persona_rut', $snap['persona_rut'])->get();
                $current = $matches->first();
                if ($matches->count() !== 1 || (int) $current->id !== (int) $item->contrato_id || ! $current->synced_at || $current->synced_at->lt(now()->subHours(48))) {
                    throw new RuntimeException('Contrato ambiguo, desactualizado o inactivo. Revisar antes de procesar.');
                }
                foreach (['empresa_id', 'persona_talana_id', 'talana_id', 'cargo_nombre', 'tipo_contrato_nombre', 'centro_costo_nombre'] as $field) {
                    if ((string) $current->$field !== (string) $snap[$field]) {
                        throw new RuntimeException('El contrato cambio desde la preparacion del lote.');
                    }
                }
            }
            if ($write && $batch->estado === 'BORRADOR') {
                if ($this->option('firmar')) {
                    throw new RuntimeException('Primero realiza la carga documental.');
                }
                DB::table('rrhh_document_batches')->where('id', $id)->update(['estado' => 'EN_PROCESO', 'updated_at' => now()]);
            }

            return $batch;
        });
        $sign = (bool) $this->option('firmar');
        $source = $sign ? 'cargado' : 'preparado';
        $docs = json_decode($batch->battery_snapshot, true)['documentos'];
        $rows = DB::table('rrhh_document_deliveries as d')->join('rrhh_document_batch_items as i', 'i.id', '=', 'd.item_id')
            ->where('i.batch_id', $id)->where('i.excluido', false)->select('d.*', 'i.snapshot')->orderBy('d.id')->cursor();
        $failed = false;
        foreach ($rows as $row) {
            if (in_array($row->state, ['revisar_carga', 'revisar_firma', 'cargando', 'solicitando_firma'], true)) {
                $this->warn("Documento interno {$row->id}: {$row->state}. Conciliar antes de repetir.");
                $failed = true;

                continue;
            }
            if ($row->state !== $source) {
                if ($sign && $row->state === 'preparado') {
                    $failed = true;
                }

                continue;
            }
            $pdf = null;
            if (! $sign) {
                $pdf = Storage::disk('local')->get($row->path);
                if (! $pdf || ! hash_equals($row->sha256, hash('sha256', $pdf))) {
                    $this->error("PDF interno {$row->id} ausente o modificado.");

                    return self::FAILURE;
                }
            }
            if (! $write) {
                continue;
            }
            $delivery = DB::table('rrhh_document_deliveries')->where('id', $row->id);
            $claimed = (clone $delivery)->where('state', $source)->update(['state' => $sign ? 'solicitando_firma' : 'cargando', 'updated_at' => now()]);
            if (! $claimed) {
                continue;
            }
            $status = null;
            try {
                $snapshot = json_decode($row->snapshot, true);
                $response = $sign ? $api->requestSignature($row->document_id)
                    : $api->upload($snapshot['persona_rut'], $docs[$row->position], $row->category, $pdf);
                $status = $response->status();
                $remote = filter_var($response->json($sign ? 'sr_id' : 'id'), FILTER_VALIDATE_INT);
                if (! $response->successful() || ! $remote || $remote < 1) {
                    throw new RuntimeException('Respuesta no confirmada.');
                }
                $delivery->update(['state' => $sign ? 'firma_solicitada' : 'cargado',
                    ($sign ? 'signature_id' : 'document_id') => $remote, 'http_status' => $status, 'updated_at' => now()]);
            } catch (Throwable) {
                $delivery->update(['state' => $sign ? 'revisar_firma' : 'revisar_carga', 'http_status' => $status, 'updated_at' => now()]);
                $this->error("Documento interno {$row->id}: conciliar con Talana. Se detuvo el lote.");

                return self::FAILURE;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
