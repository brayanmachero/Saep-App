<?php

namespace App\Http\Controllers;

use App\Mail\RespuestaAprobadaMail;
use App\Mail\RespuestaCreadaMail;
use App\Mail\RespuestaFormularioMail;
use App\Models\FormularioCampoOpcion;
use App\Models\Formulario;
use App\Models\Respuesta;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
            if ($field['type'] === 'file') {
                $multiple = !empty($field['multiple']);
                $inputName = 'file_' . $field['id'];

                if ($multiple && $request->hasFile($inputName)) {
                    $files = $request->file($inputName);
                    $items = [];
                    foreach ((array) $files as $file) {
                        $path = $file->store('respuestas/adjuntos/' . $formulario->id, 'public');
                        $items[] = [
                            'path' => $path,
                            'name' => $file->getClientOriginalName(),
                            'mime' => $file->getClientMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                    $datos[$field['id']] = $items;
                } elseif (!$multiple && $request->hasFile($inputName)) {
                    $file = $request->file($inputName);
                    $path = $file->store('respuestas/adjuntos/' . $formulario->id, 'public');
                    $datos[$field['id']] = [
                        'path'     => $path,
                        'name'     => $file->getClientOriginalName(),
                        'mime'     => $file->getClientMimeType(),
                        'size'     => $file->getSize(),
                    ];
                }
            }
        }

        $estadoSolicitado = $request->input('estado', 'Pendiente');

        // If form doesn't require approval and user is not saving as draft, auto-approve
        if ($estadoSolicitado !== 'Borrador' && !$formulario->requiere_aprobacion) {
            $estadoFinal = 'Aprobado';
        } else {
            $estadoFinal = $estadoSolicitado;
        }

        $respuesta = Respuesta::create([
            'formulario_id'   => $formulario->id,
            'version_form'    => $formulario->version,
            'usuario_id'      => auth()->id(),
            'departamento_id' => auth()->user()->departamento_id,
            'estado'          => $estadoFinal,
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

        // Send email notification to configured recipients (independent of approval flow)
        if ($respuesta->estado !== 'Borrador' && $formulario->enviar_email_respuesta && $formulario->email_notificacion) {
            $emails = array_filter(array_map('trim', explode(',', $formulario->email_notificacion)));
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($email)->send(new RespuestaFormularioMail($respuesta));
                }
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

        $estadoSolicitado = $request->input('estado', 'Pendiente');
        $formulario = $respuesta->formulario;

        if ($estadoSolicitado !== 'Borrador' && !$formulario->requiere_aprobacion) {
            $estadoFinal = 'Aprobado';
        } else {
            $estadoFinal = $estadoSolicitado;
        }

        // Handle file uploads on edit
        $datos = json_decode($request->datos_json, true) ?? [];
        $schema = json_decode($formulario->schema_json ?? '[]', true);
        $existingDatos = json_decode($respuesta->datos_json ?? '{}', true);

        foreach ($schema as $field) {
            if ($field['type'] === 'file') {
                $multiple = !empty($field['multiple']);
                $inputName = 'file_' . $field['id'];

                if ($multiple && $request->hasFile($inputName)) {
                    $files = $request->file($inputName);
                    $items = [];
                    foreach ((array) $files as $file) {
                        $path = $file->store('respuestas/adjuntos/' . $formulario->id, 'public');
                        $items[] = [
                            'path' => $path,
                            'name' => $file->getClientOriginalName(),
                            'mime' => $file->getClientMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                    $datos[$field['id']] = $items;
                } elseif (!$multiple && $request->hasFile($inputName)) {
                    $file = $request->file($inputName);
                    $path = $file->store('respuestas/adjuntos/' . $formulario->id, 'public');
                    $datos[$field['id']] = [
                        'path'     => $path,
                        'name'     => $file->getClientOriginalName(),
                        'mime'     => $file->getClientMimeType(),
                        'size'     => $file->getSize(),
                    ];
                } elseif (isset($existingDatos[$field['id']])) {
                    // Keep existing file(s) if no new upload
                    $datos[$field['id']] = $existingDatos[$field['id']];
                }
            }
        }

        $respuesta->update([
            'datos_json' => json_encode($datos),
            'estado'     => $estadoFinal,
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
     * Bulk delete multiple responses (soft delete).
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['exists:respuestas,id'],
        ]);

        $count = Respuesta::whereIn('id', $request->ids)->count();
        Respuesta::whereIn('id', $request->ids)->delete();

        return back()->with('success', "{$count} registro(s) eliminado(s) exitosamente.");
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

    /**
     * Download an import template Excel for a specific form.
     */
    public function plantillaImport(Formulario $formulario)
    {
        $schema = json_decode($formulario->schema_json ?? '[]', true);

        // Filter importable fields (exclude divider, signature, file, auto — auto fields resolve from user)
        $fields = collect($schema)->filter(fn($f) => !in_array($f['type'], ['divider', 'signature', 'file', 'auto']))->values();
        $hasAutoFields = collect($schema)->contains(fn($f) => $f['type'] === 'auto');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plantilla');

        // Row 1: Hidden field IDs (for mapping on import)
        // Row 2: Column headers with labels
        // Row 3: Helper (type / options info)

        $col = 'A';
        // Fixed columns
        $fixedCols = [
            '_email_solicitante' => ['Email Solicitante', 'Email del trabajador registrado en SAEP (resuelve nombre, cargo, depto automáticamente)'],
            '_fecha_envio'       => ['Fecha Envío (dd/mm/aaaa)', 'Ej: 15/03/2026'],
        ];
        foreach ($fixedCols as $fid => [$label, $hint]) {
            $sheet->setCellValue($col . '1', $fid);
            $sheet->setCellValue($col . '2', $label);
            $sheet->setCellValue($col . '3', $hint);
            $sheet->getStyle($col . '2')->getFont()->setBold(true);
            $sheet->getStyle($col . '3')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF888888'));
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Dynamic columns from schema
        foreach ($fields as $f) {
            $sheet->setCellValue($col . '1', $f['id']);
            $sheet->setCellValue($col . '2', $f['label']);

            $tablaHints = [
                'usuarios'      => 'Nombre completo del usuario del sistema',
                'departamentos' => 'Nombre del departamento',
                'cargos'        => 'Nombre del cargo',
                'centros_costo' => 'Nombre del centro de costo',
            ];

            $hint = match ($f['type']) {
                'number'          => 'Número',
                'date'            => 'Formato: dd/mm/aaaa',
                'select', 'radio' => 'Opciones: ' . implode(' | ', $f['options'] ?? []),
                'checkbox'        => 'Valores separados por coma. Opciones: ' . implode(' | ', $f['options'] ?? []),
                'select_dynamic'  => 'Texto libre (si el valor no existe, se agrega automáticamente al desplegable)',
                'select_tabla'    => $tablaHints[$f['tabla'] ?? ''] ?? 'Nombre del registro',
                default           => 'Texto',
            };
            $sheet->setCellValue($col . '3', $hint);

            $sheet->getStyle($col . '2')->getFont()->setBold(true);
            $sheet->getStyle($col . '3')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF888888'));
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Add note about auto fields if form has them
        if ($hasAutoFields) {
            $autoLabels = collect($schema)->filter(fn($f) => $f['type'] === 'auto')->pluck('label')->implode(', ');
            $row4Note = "Nota: Los campos automáticos ({$autoLabels}) se llenan desde el email del solicitante.";
            $sheet->setCellValue('A' . '4', $row4Note);
            $sheet->getStyle('A4')->getFont()->setItalic(true)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0066CC'));
        }

        // Hide row 1 (field IDs)
        $sheet->getRowDimension(1)->setVisible(false);

        $filename = 'plantilla-' . \Str::slug($formulario->nombre) . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Bulk import responses from an Excel file.
     */
    public function importar(Request $request, Formulario $formulario)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $schema = json_decode($formulario->schema_json ?? '[]', true);
        $schemaMap = collect($schema)->keyBy('id');

        try {
            $spreadsheet = IOFactory::load($request->file('archivo')->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestCol = $sheet->getHighestColumn();

            // Row 1 has field IDs (hidden), Row 2 has labels, data starts at Row 3 or 4
            // Read field IDs from Row 1
            $fieldIds = [];
            $colIndex = 'A';
            $lastCol = ++$highestCol; // increment to iterate past last
            while ($colIndex !== $lastCol) {
                $cellValue = trim((string) $sheet->getCell($colIndex . '1')->getValue());
                if ($cellValue !== '') {
                    $fieldIds[$colIndex] = $cellValue;
                }
                $colIndex++;
            }

            if (empty($fieldIds)) {
                return back()->with('error', 'El archivo no tiene la estructura esperada. Descargue la plantilla primero.');
            }

            // Determine data start row (skip: 1=IDs, 2=labels, 3=hints, 4=optional note)
            $startRow = 5;
            // If row 4 has no note (no auto fields), check if it's data
            $row4Check = trim((string) $sheet->getCell('A4')->getValue());
            if ($row4Check && !str_starts_with($row4Check, 'Nota:')) {
                $startRow = 4;
            }
            // If row 3 has data instead of hints, start even earlier
            $row3Check = trim((string) $sheet->getCell('A3')->getValue());
            if ($row3Check && !str_starts_with($row3Check, 'Ej:') && !str_starts_with($row3Check, 'Email') && !str_starts_with($row3Check, 'Número') && !str_starts_with($row3Check, 'Formato:') && !str_starts_with($row3Check, 'Opciones:') && !str_starts_with($row3Check, 'Texto') && !str_starts_with($row3Check, 'Nombre completo') && !str_starts_with($row3Check, 'Valores separados')) {
                $startRow = 3;
            }

            $imported = 0;
            $errors = [];

            for ($row = $startRow; $row <= $highestRow; $row++) {
                $datosJson = [];
                $emailSolicitante = null;
                $fechaEnvio = null;
                $rowEmpty = true;

                foreach ($fieldIds as $col => $fid) {
                    $raw = $sheet->getCell($col . $row)->getValue();

                    // Handle Excel date serial numbers
                    if (is_numeric($raw) && in_array($fid, ['_fecha_envio'])) {
                        try {
                            $raw = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($raw)->format('d/m/Y');
                        } catch (\Exception $e) {
                            // keep as-is
                        }
                    }
                    // Handle date fields from schema too
                    if (is_numeric($raw) && $schemaMap->has($fid) && ($schemaMap[$fid]['type'] ?? '') === 'date') {
                        try {
                            $raw = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($raw)->format('d/m/Y');
                        } catch (\Exception $e) {}
                    }

                    $val = trim((string) ($raw ?? ''));
                    if ($val !== '') $rowEmpty = false;

                    // Support both _email_solicitante (new) and _solicitante (legacy)
                    if ($fid === '_email_solicitante' || $fid === '_solicitante') {
                        $emailSolicitante = $val;
                        continue;
                    }
                    if ($fid === '_fecha_envio') {
                        $fechaEnvio = $val;
                        continue;
                    }

                    // Skip if no matching schema field
                    if (!$schemaMap->has($fid)) continue;

                    $field = $schemaMap[$fid];

                    // Type-specific processing
                    if ($field['type'] === 'checkbox' && $val !== '') {
                        $datosJson[$fid] = array_map('trim', explode(',', $val));
                    } elseif ($field['type'] === 'date' && $val !== '') {
                        // Convert dd/mm/yyyy to yyyy-mm-dd
                        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $val, $m)) {
                            $datosJson[$fid] = "{$m[3]}-" . str_pad($m[2], 2, '0', STR_PAD_LEFT) . "-" . str_pad($m[1], 2, '0', STR_PAD_LEFT);
                        } else {
                            $datosJson[$fid] = $val;
                        }
                    } elseif ($field['type'] === 'select_dynamic' && $val !== '') {
                        $datosJson[$fid] = $val;
                        // Auto-create option for the dynamic dropdown so future users see it
                        FormularioCampoOpcion::firstOrCreate([
                            'formulario_id' => $formulario->id,
                            'campo_id'      => $fid,
                            'valor'         => $val,
                        ]);
                    } else {
                        $datosJson[$fid] = $val;
                    }
                }

                if ($rowEmpty) continue;

                // Resolve user: try by email first, then by name (fallback)
                $user = null;
                $userId = null;
                $departamentoId = null;
                if ($emailSolicitante) {
                    // Try exact email match
                    $user = User::with(['departamento', 'cargo', 'centroCosto'])
                        ->where('email', $emailSolicitante)
                        ->first();
                    // Fallback: try name match (for legacy templates or pasted names)
                    if (!$user) {
                        $user = User::with(['departamento', 'cargo', 'centroCosto'])
                            ->where('name', 'LIKE', "%{$emailSolicitante}%")
                            ->first();
                    }
                    if ($user) {
                        $userId = $user->id;
                        $departamentoId = $user->departamento_id;
                    }
                }

                // Parse fecha_envio
                $fechaParsed = now();
                if ($fechaEnvio) {
                    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $fechaEnvio, $m)) {
                        $fechaParsed = \Carbon\Carbon::create($m[3], $m[2], $m[1], 0, 0, 0);
                    } else {
                        try {
                            $fechaParsed = \Carbon\Carbon::parse($fechaEnvio);
                        } catch (\Exception $e) {
                            // default to now
                        }
                    }
                }

                // Auto-fill all 'auto' fields from the resolved user (same as form fill-time)
                foreach ($schemaMap as $fid => $field) {
                    if ($field['type'] === 'auto') {
                        $datosJson[$fid] = match ($field['fuente'] ?? '') {
                            'usuario_nombre'       => $user?->nombre_completo ?? $emailSolicitante ?? '',
                            'usuario_email'        => $user?->email ?? $emailSolicitante ?? '',
                            'usuario_cargo'        => $user?->cargo?->nombre ?? '',
                            'usuario_departamento' => $user?->departamento?->nombre ?? '',
                            'usuario_centro_costo' => $user?->centroCosto?->nombre ?? '',
                            'fecha_actual'         => $fechaParsed->format('d/m/Y'),
                            'hora_actual'          => $fechaParsed->format('H:i'),
                            'fecha_hora_actual'    => $fechaParsed->format('d/m/Y H:i'),
                            default                => '',
                        };
                    }
                }

                try {
                    Respuesta::create([
                        'formulario_id'  => $formulario->id,
                        'version_form'   => $formulario->version,
                        'usuario_id'     => $userId,
                        'departamento_id' => $departamentoId,
                        'estado'         => 'Aprobado',
                        'datos_json'     => json_encode($datosJson, JSON_UNESCAPED_UNICODE),
                        'fecha_envio'    => $fechaParsed,
                        'fecha_resolucion' => $fechaParsed,
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Fila {$row}: {$e->getMessage()}";
                }
            }

            $msg = "{$imported} registro(s) importado(s) exitosamente.";
            if (!empty($errors)) {
                $msg .= ' Errores: ' . count($errors) . ' fila(s) con problemas.';
            }

            return back()->with('success', $msg);

        } catch (\Exception $e) {
            return back()->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
        }
    }
}
