<?php

namespace App\Http\Controllers;

use App\Models\TalanaContrato;
use App\Services\RrhhDocumentBatteryService;
use App\Services\RrhhDocumentCompositionService;
use App\Services\TalanaDocumentApi;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RrhhDocumentBatteryController extends Controller
{
    public function show(Request $request, int $battery)
    {
        $input = $request->validate(['documento'=>'nullable|integer|min:0','filtro'=>'nullable|in:todos,sin-vincular,por-revisar,observados,validados']);
        $record = DB::table('rrhh_document_batteries')->find($battery);
        abort_unless($record, 404);
        $documents = json_decode($record->documentos, true);
        $details = json_decode($record->document_details ?? '{}', true);
        $rules = json_decode($record->reglas, true);
        $editable = $record->estado === 'BORRADOR' && ! DB::table('rrhh_document_batches')->where('battery_id', $battery)->exists();
        $events = DB::table('rrhh_document_battery_events')->where('battery_id', $battery)->orderByDesc('id')->paginate(15);
        $options = [];
        foreach (['centro_costo_nombre', 'cargo_nombre', 'tipo_contrato_nombre'] as $field) {
            $options[$field] = TalanaContrato::whereNotNull($field)->where($field, '!=', '')->distinct()->orderBy($field)->pluck($field);
        }
        $companies = TalanaContrato::select('empresa_id', 'empresa_nombre')->distinct()->orderBy('empresa_nombre')->get();

        $composition = app(RrhhDocumentCompositionService::class)->resolve($record);
        $parentChoices = app(RrhhDocumentCompositionService::class)->choices($battery);
        $filter = $input['filtro'] ?? 'todos';
        $linked = collect($documents)->filter(fn($name) => !empty($details[$name]['talana_id']))->count();
        $reviewService = app(RrhhDocumentBatteryService::class);
        $review = $reviewService->review($documents,$details);
        $visibleDocuments = array_filter($documents, fn($name) => $filter === 'todos' || $reviewService->documentState($details[$name] ?? []) === $filter);
        $firstPending = collect($visibleDocuments)->search(fn($name) => empty($details[$name]['validated_at']));
        $selectedPosition = isset($input['documento']) && isset($visibleDocuments[$input['documento']]) ? (int)$input['documento'] : ($firstPending !== false ? $firstPending : array_key_first($visibleDocuments));
        $positions = array_keys($visibleDocuments);
        $index = array_search($selectedPosition,$positions,true);
        $previous = $index !== false && $index > 0 ? $positions[$index-1] : null;
        $next = $index !== false ? ($positions[$index+1] ?? null) : null;
        $selectedName = $selectedPosition !== null ? $documents[$selectedPosition] : null;
        $selectedDetails = $selectedName !== null ? ($details[$selectedName] ?? []) : [];
        $versions = DB::table('rrhh_document_batteries')->where('nombre',$record->nombre)->orderByDesc('version')->get(['id','version','estado']);
        return view('rrhh_documentos.battery', compact('composition','parentChoices','review','filter','linked','visibleDocuments','previous','next','selectedPosition','selectedName','selectedDetails','versions','options', 'companies', 'record', 'documents', 'details', 'rules', 'editable', 'events'));
    }

    public function finalReview(Request $request, int $battery, RrhhDocumentBatteryService $service)
    {
        $input = $request->validate(['comparar'=>'nullable|integer|min:1']);
        $data = $this->show($request,$battery)->getData();
        $record = $data['record'];
        $others = DB::table('rrhh_document_batteries')->where('nombre',$record->nombre)->where('id','!=',$battery)->orderByDesc('version')->get();
        $baseline = isset($input['comparar']) ? $others->firstWhere('id',(int)$input['comparar']) : ($others->firstWhere('estado','ACTIVA') ?? $others->first(fn($version) => $version->version < $record->version));
        abort_if(isset($input['comparar']) && !$baseline,404);
        $comparison = $baseline ? $service->compare($baseline,$record) : null;
        return view('rrhh_documentos.final-review',$data + compact('others','baseline','comparison'));
    }

    public function change(Request $request, int $battery, RrhhDocumentBatteryService $service)
    {
        $data = $request->validate([
            'revision' => 'required|integer|min:1', 'action' => 'required|in:remove,add,replace,rules,observe,resolve,composition',
            'position' => 'nullable|required_if:action,remove,replace,observe,resolve|integer|min:0',
            'documentos' => 'required_if:action,add,replace|array|min:1|max:50', 'documentos.*' => 'string|max:4096',
            'reason'=>'required_if:action,observe,resolve|string|min:5|max:1500',
            'kind'=>'required_if:action,composition|in:base,specific,independent',
            'parent_ids'=>'nullable|array|max:10', 'parent_ids.*'=>'integer|distinct|min:1',
            'empresa_id' => 'nullable|integer',
            'centro_costo_nombre' => 'nullable|string|max:200',
            'cargo_nombre' => 'nullable|string|max:200', 'tipo_contrato_nombre' => 'nullable|string|max:200',
        ]);
        $added = in_array($data['action'], ['add','replace'], true) ? $service->selections($data['documentos'], (int) $request->user()->id) : [];
        DB::transaction(function () use ($battery, $request, $data, $added, $service) {
            $record = DB::table('rrhh_document_batteries')->where('id', $battery)->lockForUpdate()->first();
            abort_unless($record, 404);
            $service->editable($record, (int) $data['revision']);
            $documents = json_decode($record->documentos, true);
            $details = json_decode($record->document_details ?? '{}', true);
            $eventDetail = [];
            if ($data['action'] === 'remove') {
                abort_unless(isset($documents[$data['position']]), 422);
                $name = $documents[$data['position']];
                unset($documents[$data['position']],$details[$name]);
                $documents = array_values($documents);
                $eventDetail = ['documento' => $name];
            }
            if ($data['action'] === 'add') {
                foreach ($added as $name => $metadata) {
                    if (! in_array($name, $documents, true)) {
                        $documents[] = $name;
                    }
                    if (($details[$name]['talana_id'] ?? null) !== $metadata['talana_id']) {
                        $details[$name] = $metadata;
                    }
                }
                if (count($documents) > 50) {
                    throw ValidationException::withMessages(['documentos' => 'La batería admite hasta 50 documentos.']);
                }
                $eventDetail = ['documentos' => array_keys($added)];
            }
            if ($data['action'] === 'replace') {
                abort_unless(isset($documents[$data['position']]),422);
                if (count($data['documentos']) !== 1 || count($added) !== 1) {
                    throw ValidationException::withMessages(['documentos'=>'Selecciona exactamente un documento para este requisito.']);
                }
                $oldName = $documents[$data['position']];
                $newName = array_key_first($added);
                if ($newName !== $oldName && in_array($newName,$documents,true)) {
                    throw ValidationException::withMessages(['documentos'=>'Ese documento ya está en otro requisito de esta batería.']);
                }
                $reference = $details[$oldName]['source_reference'] ?? $oldName;
                $eventDetail = ['documento'=>$newName,'anterior'=>$oldName,'talana_id_anterior'=>$details[$oldName]['talana_id'] ?? null,'talana_id_nuevo'=>$added[$newName]['talana_id']];
                unset($details[$oldName]);
                $documents[$data['position']] = $newName;
                $details[$newName] = $added[$newName] + ['source_reference'=>$reference];
            }
            if (in_array($data['action'],['observe','resolve'],true)) {
                abort_unless(isset($documents[$data['position']]),422);
                $name = $documents[$data['position']];
                if ($data['action'] === 'observe') {
                    foreach (['validated_at','validated_by','validated_name','validated_sha256'] as $field) unset($details[$name][$field]);
                    $details[$name]['observation'] = ['state'=>'open','reason'=>$data['reason'],'by'=>(int)$request->user()->id,'name'=>$request->user()->name,'at'=>now()->toIso8601String()];
                } else {
                    if (($details[$name]['observation']['state'] ?? null) !== 'open') throw ValidationException::withMessages(['documento'=>'Este documento no tiene una observación abierta.']);
                    $details[$name]['observation']['state'] = 'resolved';
                    $details[$name]['observation']['resolution'] = ['reason'=>$data['reason'],'by'=>(int)$request->user()->id,'name'=>$request->user()->name,'at'=>now()->toIso8601String()];
                }
                $eventDetail = ['documento'=>$name,'motivo'=>$data['reason']];
            }
            $changes = ['documentos' => json_encode($documents), 'document_details' => json_encode($details), 'revision' => $record->revision + 1, 'updated_at' => now()];
            if ($data['action'] === 'rules') {
                $rules = array_intersect_key($data, array_flip(['empresa_id', 'centro_costo_nombre', 'cargo_nombre', 'tipo_contrato_nombre']));
                $changes['reglas'] = json_encode($rules);
                foreach ($details as &$metadata) {
                    unset($metadata['validated_by'],$metadata['validated_at']);
                }
                unset($metadata);
                $changes['document_details'] = json_encode($details);
                $eventDetail = ['reglas' => $rules];
            }
            if ($data['action'] === 'composition') {
                $changes['kind'] = $data['kind'];
                foreach ($details as &$metadata) { unset($metadata['validated_by'], $metadata['validated_at']); }
                unset($metadata);
                $changes['document_details'] = json_encode($details);
                $changes['parent_ids'] = json_encode(array_map('intval', $data['parent_ids'] ?? []));
                $eventDetail = ['kind'=>$data['kind'], 'parent_ids'=>$data['parent_ids'] ?? []];
            }
            // Check scope conflicts and dependency validity before persisting edits.
            $candidate = (object)array_merge((array)$record, $changes);
            app(RrhhDocumentCompositionService::class)->assertUsable(app(RrhhDocumentCompositionService::class)->resolve($candidate, true));
            DB::table('rrhh_document_batteries')->where('id', $battery)->update($changes);
            $service->event($battery, (int) $request->user()->id, $data['action'], $eventDetail);
        });

        return redirect()->route('rrhh-documentos.battery-show', in_array($data['action'],['replace','observe','resolve'],true) ? ['battery'=>$battery,'documento'=>$data['position']] : $battery)->with('success', 'Borrador actualizado.');
    }

    public function candidatePdf(Request $request, TalanaDocumentApi $api, RrhhDocumentBatteryService $service)
    {
        $data = $request->validate(['selection'=>'required|string|max:4096']);
        $selected = $service->selections([$data['selection']], (int)$request->user()->id);
        $name = array_key_first($selected);
        try {
            $document = $api->document($selected[$name]['talana_id'])->throw()->json();
            abort_unless(($document['nombre'] ?? null) === $name,422);
            $pdf = $api->previewPdf($document['adjunto'] ?? '');
        } catch (HttpClientException|\RuntimeException $exception) {
            return response()->json(['message'=>'No se pudo abrir el PDF de referencia.'],503);
        }
        return response($pdf)->header('Content-Type','application/pdf')->header('Content-Disposition','inline; filename="referencia.pdf"')->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
    }

    public function clone(Request $request, int $battery, RrhhDocumentBatteryService $service)
    {
        $id = DB::transaction(function () use ($battery, $request, $service) {
            $source = DB::table('rrhh_document_batteries')->find($battery);
            abort_unless($source, 404);
            $family = DB::table('rrhh_document_batteries')->where('nombre', $source->nombre)->orderBy('id')->lockForUpdate()->get();
            $source = $family->firstWhere('id', $battery);
            $draft = $family->first(fn ($item) => $item->estado === 'BORRADOR' && $item->version > $source->version);
            if ($draft) {
                return $draft->id;
            }
            $details = json_decode($source->document_details ?? '{}', true);
            foreach ($details as &$metadata) {
                unset($metadata['validated_at'],$metadata['validated_by']);
            }
            unset($metadata);
            $id = DB::table('rrhh_document_batteries')->insertGetId([
                'nombre' => $source->nombre, 'version' => $family->max('version') + 1, 'documentos' => $source->documentos,
                'kind'=>$source->kind, 'parent_ids'=>$source->parent_ids,
                'reglas' => $source->reglas, 'document_details' => json_encode($details), 'created_by' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $service->event($id, (int) $request->user()->id, 'clone', ['origen' => $battery]);

            return $id;
        });

        return redirect()->route('rrhh-documentos.battery-show', $id)->with('success', 'Nueva versión en borrador. Revisa los documentos y confirma su aplicación.');
    }

    public function preview(Request $request, int $battery, int $position, TalanaDocumentApi $api)
    {
        $record = DB::table('rrhh_document_batteries')->find($battery);
        abort_unless($record, 404);
        $documents = json_decode($record->documentos, true);
        abort_unless(isset($documents[$position]), 404);
        $name = $documents[$position];
        $metadata = json_decode($record->document_details ?? '{}', true)[$name] ?? [];
        $url = null;
        $error = null;
        if (empty($metadata['talana_id'])) {
            $error = 'Selecciona este documento nuevamente desde Talana para vincular el archivo.';
        } else {
            try {
                $document = $api->document($metadata['talana_id'])->throw()->json();
                $candidate = $document['adjunto'] ?? '';
                $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
                if (($document['nombre'] ?? null) !== $name || ! str_starts_with($candidate, 'https://') || ($host !== 'talana.com' && ! str_ends_with($host, '.talana.com'))) {
                    $error = 'El documento cambió o su adjunto requiere revisión. Vuelve a seleccionarlo desde Talana.';
                } else {
                    $url = route('rrhh-documentos.battery-pdf', [$battery, $position]);

                }
            } catch (HttpClientException|\RuntimeException) {
                $error = 'No se pudo abrir el documento de Talana. Intenta nuevamente.';
            }
        }

        return response()->view('rrhh_documentos.battery-preview', compact('record', 'name', 'position', 'url', 'error'))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function pdf(Request $request, int $battery, int $position, TalanaDocumentApi $api)
    {
        $record = DB::table('rrhh_document_batteries')->find($battery);
        abort_unless($record, 404);
        $name = json_decode($record->documentos, true)[$position] ?? null;
        abort_unless($name, 404);
        $metadata = json_decode($record->document_details ?? '{}', true)[$name] ?? [];
        abort_unless(! empty($metadata['talana_id']), 404);
        try {
            $document = $api->document($metadata['talana_id'])->throw()->json();
            abort_unless(($document['nombre'] ?? null) === $name, 409, 'El documento cambió. Vuelve a seleccionarlo.');
            $content = $api->previewPdf($document['adjunto'] ?? '');
        } catch (HttpClientException|\RuntimeException $e) {
            abort(502, 'No se pudo abrir el PDF. Vuelve a intentar desde el detalle de la batería.');
        }
        $request->session()->put("battery_preview.{$battery}.{$position}", [
            'revision' => $record->revision, 'id' => $metadata['talana_id'], 'name' => $name,
            'user' => (int) $request->user()->id, 'sha256' => hash('sha256', $content),
        ]);

        return response($content)->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="documento-talana.pdf"')
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function validateDocument(Request $request, int $battery, int $position, RrhhDocumentBatteryService $service)
    {
        $data = $request->validate(['revision' => 'required|integer', 'confirmed' => 'accepted', 'filtro'=>'nullable|in:todos,sin-vincular,por-revisar,observados,validados']);
        DB::transaction(function () use ($request, $battery, $position, $data, $service) {
            $record = DB::table('rrhh_document_batteries')->where('id', $battery)->lockForUpdate()->first();
            abort_unless($record, 404);
            $service->editable($record, (int) $data['revision']);
            $documents = json_decode($record->documentos, true);
            abort_unless(isset($documents[$position]), 404);
            $name = $documents[$position];
            $details = json_decode($record->document_details ?? '{}', true);
            if (($details[$name]['observation']['state'] ?? null) === 'open') throw ValidationException::withMessages(['documento'=>'Resuelve la observación antes de validar este documento.']);
            $preview = $request->session()->get("battery_preview.{$battery}.{$position}");
            if (! $preview || $preview['revision'] !== $record->revision || $preview['name'] !== $name || $preview['user'] !== (int) $request->user()->id || $preview['id'] !== ($details[$name]['talana_id'] ?? null)) {
                throw ValidationException::withMessages(['documento' => 'Abre el documento de esta versión antes de validarlo.']);
            }
            $details[$name]['validated_by'] = (int) $request->user()->id;
            $details[$name]['validated_name'] = $request->user()->name;
            $details[$name]['validated_at'] = now()->toIso8601String();
            $details[$name]['validated_sha256'] = $preview['sha256'];
            DB::table('rrhh_document_batteries')->where('id', $battery)->update(['document_details' => json_encode($details), 'revision' => $record->revision + 1, 'updated_at' => now()]);
            $service->event($battery, (int) $request->user()->id, 'validate', ['documento' => $name, 'talana_id' => $preview['id']]);
        });

        return redirect()->route('rrhh-documentos.battery-show', empty($data['filtro']) ? $battery : ['battery'=>$battery,'filtro'=>$data['filtro']])->with('success', 'Documento validado.');
    }

    public function activate(Request $request, int $battery, RrhhDocumentBatteryService $service)
    {
        $data = $request->validate(['revision' => 'required|integer']);
        DB::transaction(function () use ($request, $battery, $data, $service) {
            $source = DB::table('rrhh_document_batteries')->find($battery);
            abort_unless($source, 404);
            $family = DB::table('rrhh_document_batteries')->where('nombre', $source->nombre)->orderBy('id')->lockForUpdate()->get();
            $record = $family->firstWhere('id', $battery);
            $service->editable($record, (int) $data['revision']);
            $documents = json_decode($record->documentos, true);
            $details = json_decode($record->document_details ?? '{}', true);
            if (! app(RrhhDocumentCompositionService::class)->resolve($record, true)['review']['ready']) {
                throw ValidationException::withMessages(['bateria' => 'Valida todos los documentos antes de activar la batería.']);
            }
            app(RrhhDocumentCompositionService::class)->assertUsable(app(RrhhDocumentCompositionService::class)->resolve($record, true), true);
            foreach ($family->where('estado', 'ACTIVA') as $previous) {
                DB::table('rrhh_document_batteries')->where('id', $previous->id)->update(['estado' => 'ARCHIVADA', 'revision' => $previous->revision + 1, 'updated_at' => now()]);
                $service->event($previous->id, (int) $request->user()->id, 'archive', ['reemplazada_por' => $battery]);
            }
            DB::table('rrhh_document_batteries')->where('id',$battery)->update(['estado' => 'ACTIVA', 'activated_by' => $request->user()->id, 'activated_at' => now(), 'revision' => $record->revision + 1, 'updated_at' => now()]);
            $service->event($battery,(int) $request->user()->id,'activate');
        });

        return redirect()->route('rrhh-documentos.battery-show',$battery)->with('success','Batería activa y disponible para nuevos lotes.');
    }
}
