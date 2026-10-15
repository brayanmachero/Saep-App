<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RrhhDocumentReviewService
{
    public function update(string $id, array $data, int $actor): void
    {
        DB::transaction(function () use ($id, $data, $actor) {
            $batch = DB::table('rrhh_document_batches')->where('id', $id)->lockForUpdate()->first();
            abort_unless($batch, 404);
            if ($batch->estado !== 'BORRADOR') {
                throw ValidationException::withMessages(['accion' => 'El lote está cancelado y no admite cambios.']);
            }
            $detail = ['motivo' => $data['motivo']];
            if ($data['accion'] === 'cancelar') {
                DB::table('rrhh_document_batches')->where('id', $id)->update(['estado' => 'CANCELADO', 'updated_at' => now()]);
            } else {
                $item = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->where('id', $data['item_id'])->first();
                abort_unless($item, 404);
                $excluded = $data['accion'] === 'retirar';
                if ((bool) $item->excluido === $excluded) {
                    return;
                }
                DB::table('rrhh_document_batch_items')->where('id', $item->id)->update(['excluido' => $excluded]);
                $active = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->where('excluido', false)->get();
                $docs = json_decode($batch->battery_snapshot, true)['documentos'];
                DB::table('rrhh_document_batches')->where('id', $id)->update(['trabajadores' => $active->count(), 'documentos' => $active->count() * count($docs), 'observados' => $active->filter(fn ($i) => count(json_decode($i->observaciones, true)) > 0)->count(), 'updated_at' => now()]);
                $detail['item_id'] = $item->id;
                $detail['contrato_id'] = $item->contrato_id;
            }
            DB::table('rrhh_document_batch_events')->insert(['batch_id' => $id, 'actor_id' => $actor, 'accion' => $data['accion'], 'detalle' => json_encode($detail, JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
        });
    }
}
