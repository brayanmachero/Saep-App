<?php

namespace App\Http\Controllers;

use App\Mail\RespuestaAprobadaMail;
use App\Mail\RespuestaCreadaMail;
use App\Models\Formulario;
use App\Models\Respuesta;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class RespuestaController extends Controller
{
    public function create(Request $request)
    {
        $formularios = Formulario::where('activo', true)->get();
        $formulario = $request->formulario_id
            ? Formulario::findOrFail($request->formulario_id)
            : null;

        return view('respuestas.create', compact('formularios', 'formulario'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'formulario_id' => ['required', 'exists:formularios,id'],
            'datos_json'    => ['required', 'json'],
            'estado'        => ['in:Borrador,Pendiente'],
        ]);

        $formulario = Formulario::findOrFail($request->formulario_id);

        // Handle file uploads
        $datos = json_decode($request->datos_json, true) ?? [];
        $schema = json_decode($formulario->schema_json ?? '[]', true);

        foreach ($schema as $field) {
            if ($field['type'] === 'file' && $request->hasFile('file_' . $field['id'])) {
                $file = $request->file('file_' . $field['id']);
                $path = $file->store('respuestas/adjuntos/' . $formulario->id, 'public');
                $datos[$field['id']] = [
                    'path'     => $path,
                    'name'     => $file->getClientOriginalName(),
                    'mime'     => $file->getClientMimeType(),
                    'size'     => $file->getSize(),
                ];
            }
        }

        $respuesta = Respuesta::create([
            'formulario_id'   => $formulario->id,
            'version_form'    => $formulario->version,
            'usuario_id'      => auth()->id(),
            'departamento_id' => auth()->user()->departamento_id,
            'estado'          => $request->input('estado', $formulario->requiere_aprobacion ? 'Pendiente' : 'Aprobado'),
            'datos_json'      => json_encode($datos),
        ]);

        // Mark assignment as completed if user was assigned
        $pivot = \DB::table('formulario_usuario')
            ->where('formulario_id', $formulario->id)
            ->where('user_id', auth()->id())
            ->where('estado', 'Pendiente')
            ->first();

        if ($pivot) {
            // For permanent forms (no end date), keep assignment active
            $esContinuo = !$formulario->fecha_fin;

            if ($esContinuo) {
                // Update completion timestamp but keep Pendiente so user can submit again
                \DB::table('formulario_usuario')
                    ->where('formulario_id', $formulario->id)
                    ->where('user_id', auth()->id())
                    ->update(['completado_at' => now()]);
            } else {
                \DB::table('formulario_usuario')
                    ->where('formulario_id', $formulario->id)
                    ->where('user_id', auth()->id())
                    ->update(['estado' => 'Completado', 'completado_at' => now()]);
            }
        }

        // Notify approvers when submitted (not draft)
        if ($respuesta->estado === 'Pendiente' && $formulario->requiere_aprobacion && $formulario->aprobador_rol_id) {
            $aprobadores = User::where('rol_id', $formulario->aprobador_rol_id)->where('activo', true)->get();
            foreach ($aprobadores as $ap) {
                Mail::to($ap->email)->send(new RespuestaCreadaMail($respuesta));
                $ap->notify(new AppNotification(
                    'Nuevo formulario pendiente',
                    auth()->user()->name . ' envió ' . $formulario->nombre,
                    'info',
                    route('respuestas.show', $respuesta)
                ));
            }
        }

        $msg = $formulario->requiere_aprobacion && $respuesta->estado === 'Pendiente'
            ? 'Formulario enviado para aprobación.'
            : ($respuesta->estado === 'Borrador' ? 'Borrador guardado correctamente.' : 'Formulario completado exitosamente.');

        return redirect()->route('respuestas.show', $respuesta)
            ->with('success', $msg);
    }

    public function show(Respuesta $respuesta)
    {
        $respuesta->load(['formulario', 'usuario.departamento', 'aprobaciones.aprobador']);
        $schema = json_decode($respuesta->formulario->schema_json ?? '[]', true);
        $datos  = json_decode($respuesta->datos_json ?? '{}', true);

        return view('respuestas.show', compact('respuesta', 'schema', 'datos'));
    }

    public function edit(Respuesta $respuesta)
    {
        abort_if($respuesta->estado !== 'Borrador', 403, 'Solo puedes editar borradores.');

        $schema = json_decode($respuesta->formulario->schema_json ?? '[]', true);
        $datos  = json_decode($respuesta->datos_json ?? '{}', true);

        return view('respuestas.edit', compact('respuesta', 'schema', 'datos'));
    }

    public function update(Request $request, Respuesta $respuesta)
    {
        abort_if($respuesta->estado !== 'Borrador', 403);

        $request->validate([
            'datos_json' => ['required', 'json'],
            'estado'     => ['in:Borrador,Pendiente'],
        ]);

        $respuesta->update([
            'datos_json' => $request->datos_json,
            'estado'     => $request->input('estado', 'Pendiente'),
        ]);

        return redirect()->route('respuestas.show', $respuesta)
            ->with('success', 'Solicitud actualizada.');
    }

    public function destroy(Respuesta $respuesta)
    {
        abort_if($respuesta->estado !== 'Borrador', 403, 'Solo puedes eliminar borradores.');
        $formularioId = $respuesta->formulario_id;
        $respuesta->delete();
        return redirect()->route('formularios.show', $formularioId)
            ->with('success', 'Solicitud eliminada.');
    }

    public function cambiarEstado(Request $request, Respuesta $respuesta)
    {
        $request->validate([
            'estado'     => ['required', 'in:Aprobado,Rechazado,Revisión,Pendiente'],
            'comentario' => ['nullable', 'string', 'max:2000'],
        ]);

        $respuesta->update([
            'estado'            => $request->estado,
            'fecha_resolucion'  => in_array($request->estado, ['Aprobado', 'Rechazado'])
                ? now() : $respuesta->fecha_resolucion,
        ]);

        // Registro de aprobación
        $respuesta->aprobaciones()->create([
            'aprobador_id' => auth()->id(),
            'accion'       => $request->estado,
            'comentario'   => $request->comentario,
            'fecha'        => now(),
        ]);

        // Notify requester when approved or rejected
        if (in_array($request->estado, ['Aprobado', 'Rechazado']) && $respuesta->usuario?->email) {
            Mail::to($respuesta->usuario->email)->send(new RespuestaAprobadaMail($respuesta->fresh(['formulario', 'aprobaciones.aprobador'])));
            $respuesta->usuario->notify(new AppNotification(
                'Solicitud ' . strtolower($request->estado),
                $respuesta->formulario->nombre . ' fue ' . strtolower($request->estado),
                $request->estado === 'Aprobado' ? 'success' : 'danger',
                route('respuestas.show', $respuesta)
            ));
        }

        return back()->with('success', "Solicitud marcada como {$request->estado}.");
    }

    /**
     * Bulk approve/reject multiple responses.
     */
    public function bulkEstado(Request $request)
    {
        $request->validate([
            'ids'        => ['required', 'array'],
            'ids.*'      => ['exists:respuestas,id'],
            'estado'     => ['required', 'in:Aprobado,Rechazado'],
            'comentario' => ['nullable', 'string', 'max:2000'],
        ]);

        $count = 0;
        foreach ($request->ids as $id) {
            $resp = Respuesta::find($id);
            if (!$resp || $resp->estado !== 'Pendiente') continue;

            $resp->update([
                'estado'           => $request->estado,
                'fecha_resolucion' => now(),
            ]);

            $resp->aprobaciones()->create([
                'aprobador_id' => auth()->id(),
                'accion'       => $request->estado,
                'comentario'   => $request->comentario ?? 'Acción masiva',
                'fecha'        => now(),
            ]);

            if ($resp->usuario?->email) {
                Mail::to($resp->usuario->email)->send(
                    new RespuestaAprobadaMail($resp->fresh(['formulario', 'aprobaciones.aprobador']))
                );
                $resp->usuario->notify(new AppNotification(
                    'Solicitud ' . strtolower($request->estado),
                    $resp->formulario->nombre . ' fue ' . strtolower($request->estado),
                    $request->estado === 'Aprobado' ? 'success' : 'danger',
                    route('respuestas.show', $resp)
                ));
            }
            $count++;
        }

        return back()->with('success', "{$count} solicitud(es) marcada(s) como {$request->estado}.");
    }

    /**
     * Export responses as Excel/CSV.
     */
    public function exportar(Request $request)
    {
        $query = Respuesta::with(['formulario', 'usuario.departamento']);

        if ($request->filled('formulario_id')) {
            $query->where('formulario_id', $request->formulario_id);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $respuestas = $query->latest()->get();

        if ($respuestas->isEmpty()) {
            return back()->with('error', 'No hay datos para exportar.');
        }

        // Collect all unique field labels from schema for headers
        $fieldMap = [];
        foreach ($respuestas as $r) {
            $schema = json_decode($r->formulario->schema_json ?? '[]', true);
            foreach ($schema as $f) {
                if ($f['type'] !== 'divider' && !isset($fieldMap[$f['id']])) {
                    $fieldMap[$f['id']] = $f['label'];
                }
            }
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Respuestas');

        // Headers
        $headers = ['#', 'Formulario', 'Solicitante', 'Departamento', 'Estado', 'Fecha Envío'];
        foreach ($fieldMap as $label) {
            $headers[] = $label;
        }

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Data rows
        $row = 2;
        foreach ($respuestas as $r) {
            $datos = json_decode($r->datos_json ?? '{}', true);

            $col = 'A';
            $sheet->setCellValue($col++ . $row, $r->id);
            $sheet->setCellValue($col++ . $row, $r->formulario->nombre ?? '');
            $sheet->setCellValue($col++ . $row, $r->usuario->name ?? '');
            $sheet->setCellValue($col++ . $row, $r->usuario->departamento->nombre ?? '');
            $sheet->setCellValue($col++ . $row, $r->estado);
            $sheet->setCellValue($col++ . $row, $r->created_at->format('d/m/Y H:i'));

            foreach ($fieldMap as $fid => $label) {
                $val = $datos[$fid] ?? '';
                if (is_array($val)) {
                    // File field or checkbox array
                    $val = isset($val['name']) ? $val['name'] : implode(', ', $val);
                }
                $sheet->setCellValue($col++ . $row, $val);
            }
            $row++;
        }

        $filename = 'respuestas-' . now()->format('Y-m-d_His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
