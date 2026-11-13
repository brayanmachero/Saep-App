<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RrhhDocumentFileController extends Controller
{
    public function store(Request $request, string $id, int $item)
    {
        $data = $request->validate([
            'position' => 'required|integer|min:0',
            'category' => 'required|string|max:120',
            'pdf' => 'required|file|mimes:pdf|max:10240',
        ]);
        $file = $request->file('pdf');
        $disk = Storage::disk('local');
        $path = $file->store('rrhh/documentos', 'local');
        abort_unless($path, 500, 'No se pudo guardar el PDF.');
        try {
            $oldPath = DB::transaction(function () use ($id, $item, $data, $path, $file, $request) {
                $batch = DB::table('rrhh_document_batches')->where('id', $id)->lockForUpdate()->first();
                abort_unless($batch, 404);
                $row = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->where('id', $item)->first();
                abort_unless($row, 404);
                if ($batch->estado !== 'BORRADOR' || $row->excluido || json_decode($row->observaciones, true)) {
                    throw ValidationException::withMessages(['pdf' => 'El contrato debe estar incluido, sin observaciones y en un borrador.']);
                }
                $docs = json_decode($batch->battery_snapshot, true)['documentos'];
                abort_unless(array_key_exists($data['position'], $docs), 422);
                $key = ['item_id' => $item, 'position' => $data['position']];
                $previous = DB::table('rrhh_document_deliveries')->where($key)->first();
                $values = ['path' => $path, 'category' => trim($data['category']), 'prepared_by' => $request->user()->id,
                    'sha256' => hash_file('sha256', $file->getRealPath()), 'updated_at' => now()];
                if ($previous) {
                    DB::table('rrhh_document_deliveries')->where('id', $previous->id)->update($values);
                } else {
                    DB::table('rrhh_document_deliveries')->insert($key + $values + ['created_at' => now()]);
                }
                DB::table('rrhh_document_batch_events')->insert(['batch_id' => $id, 'actor_id' => $request->user()->id,
                    'accion' => 'Preparar PDF', 'detalle' => json_encode(['contrato_id' => $row->contrato_id, 'motivo' => $docs[$data['position']]]), 'created_at' => now()]);

                return $previous?->path;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
        if ($oldPath) {
            $disk->delete($oldPath);
        }

        return back()->with('success', 'PDF preparado.');
    }

    public function download(string $id, int $item, int $position)
    {
        $path = DB::table('rrhh_document_deliveries as d')->join('rrhh_document_batch_items as i', 'i.id', '=', 'd.item_id')
            ->where('i.batch_id', $id)->where('i.id', $item)->where('d.position', $position)->value('d.path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'documento.pdf', ['Cache-Control' => 'private, no-store']);
    }
}
