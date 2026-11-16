<?php

namespace App\Http\Controllers;

use App\Models\TalanaContrato;
use App\Services\RrhhDocumentDraftService;
use App\Services\RrhhDocumentReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RrhhDocumentController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate(['empresa_id' => 'nullable|integer', 'centro_costo_nombre' => 'nullable|string|max:200', 'cargo_nombre' => 'nullable|string|max:200', 'tipo_contrato_nombre' => 'nullable|string|max:200', 'buscar' => 'nullable|string|max:100']);
    }

    public function overview(Request $request)
    {
        $filters = $request->validate([
            'q'=>'nullable|string|max:150',
            'estado'=>'nullable|in:BORRADOR,ACTIVA,ARCHIVADA',
        ]);
        $query = DB::table('rrhh_document_batteries');
        if (filled($filters['q'] ?? null)) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn($q)=>$q->where('nombre','like',$term)->orWhere('reglas','like',$term));
        }
        if (filled($filters['estado'] ?? null)) {
            $query->where('estado',$filters['estado']);
        }
        $batteries = $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(12)->withQueryString();
        $counts = DB::table('rrhh_document_batteries')->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total','estado');
        return view('rrhh_documentos.overview',compact('batteries','counts','filters'));
    }

    public function newBattery(Request $request)
    {
        $groups = json_decode(file_get_contents(resource_path('data/rrhh-est.json')),true);
        $input = $request->validate([
            'grupo'=>['nullable',\Illuminate\Validation\Rule::in(array_keys($groups))],
            'buscar'=>'nullable|string|max:255',
        ]);
        $group = $input['grupo'] ?? null;
        $initialSearch = $input['buscar'] ?? '';
        $companies = TalanaContrato::select('empresa_id','empresa_nombre')->whereNotNull('empresa_id')->distinct()->orderBy('empresa_nombre')->get();
        $options = [];
        foreach (['centro_costo_nombre','cargo_nombre','tipo_contrato_nombre'] as $field) {
            $query = TalanaContrato::whereNotNull($field)->where($field,'!=','');
            if ($group) {
                $query->where('empresa_id',config('services.talana.inventory_companies.EST',1081));
            }
            $options[$field] = $query->distinct()->orderBy($field)->pluck($field);
        }
        return view('rrhh_documentos.create',compact('group','initialSearch','companies','options'));
    }

    public function index(Request $r, RrhhDocumentDraftService $service)
    {
        $filters = $this->filters($r);
        $contracts = $service->query($filters)->orderBy('persona_nombre')->paginate(50)->withQueryString();
        $options = [];
        foreach (['empresa_nombre', 'centro_costo_nombre', 'cargo_nombre', 'tipo_contrato_nombre'] as $field) {
            $options[$field] = TalanaContrato::whereNotNull($field)->where($field, '!=', '')->distinct()->orderBy($field)->pluck($field);
        }
        $companies = TalanaContrato::select('empresa_id', 'empresa_nombre')->whereNotNull('empresa_id')->whereNotNull('empresa_nombre')->where('empresa_nombre', '!=', '')->distinct()->orderBy('empresa_nombre')->get();

        return view('rrhh_documentos.index', compact('contracts', 'filters', 'options', 'companies') + ['batteries' => DB::table('rrhh_document_batteries')->orderByDesc('id')->get(), 'batches' => DB::table('rrhh_document_batches')->orderByDesc('created_at')->paginate(10, ['*'], 'lotes_pagina')]);
    }

    public function est(Request $request)
    {
        $groups = json_decode(file_get_contents(resource_path('data/rrhh-est.json')), true, 512, JSON_THROW_ON_ERROR);
        $matches = json_decode(file_get_contents(resource_path('data/rrhh-est-talana.json')), true, 512, JSON_THROW_ON_ERROR);
        $input = $request->validate([
            'grupo' => ['nullable', \Illuminate\Validation\Rule::in(array_keys($groups))],
            'buscar' => 'nullable|string|min:2|max:255',
            'pagina' => 'nullable|integer|min:1',
            'orden' => 'nullable|in:recent,oldest,name',
        ]);
        $group = $input['grupo'] ?? 'Base EST';
        $sources = ['Base EST'];
        foreach (['IDL', 'DHL'] as $client) {
            if (str_starts_with($group, $client.' ')) {
                $sources[] = $client;
            }
        }
        $sources[] = $group;
        $documents = [];
        foreach (array_unique($sources) as $source) {
            foreach ($groups[$source] as $name) {
                $documents[$name][] = $source;
            }
        }
        return view('rrhh_documentos.est', compact('matches','groups','group','documents'));
    }

    public function documentOptions(Request $request, \App\Services\TalanaDocumentApi $api)
    {
        abort_unless($request->user()->tieneAcceso('gestion_documental','puede_crear') || $request->user()->tieneAcceso('gestion_documental','puede_editar'),403);
        $data = $request->validate(['q'=>'required|string|min:2|max:255', 'page'=>'nullable|integer|min:1|max:10000', 'order'=>'nullable|in:recent,oldest,name']);
        try {
            $response = $api->searchDocuments($data['q'], $data['page'] ?? 1)->throw();
        } catch (\Illuminate\Http\Client\HttpClientException|\RuntimeException $e) {
            return response()->json(['message'=>'No se pudo consultar Talana. Intenta nuevamente.'],503);
        }
        $options = [];
        foreach ($api->summarizeDocuments($response->json('results') ?? [], $data['order'] ?? 'recent') as $entry) {
            $name = $entry['name'];
            if ($name === '' || mb_strlen($name)>255 || isset($options[$name])) {
                continue;
            }
            $options[$name] = $entry + ['value'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode([
                'name'=>$name, 'id'=>$entry['id'], 'user'=>(int)$request->user()->id,
            ]))];
        }
        return response()->json(['options'=>array_values($options), 'next'=>filled($response->json('next'))])->header('Cache-Control','private, no-store');
    }

    public function battery(Request $r)
    {
        if ($r->boolean('origen_est')) {
            $companyId = (int) config('services.talana.inventory_companies.EST', 1081);
            $r->merge(['empresa_id' => $companyId]);
            $r->validate(['centro_costo_nombre' => [
                'required', 'string',
                \Illuminate\Validation\Rule::exists('talana_contratos', 'centro_costo_nombre')->where('empresa_id', $companyId),
            ]]);
        }
        $d = $r->validate(['nombre' => 'required|string|max:150', 'version' => 'required|integer|min:1|max:9999', 'documentos' => 'required|array|min:1|max:50', 'documentos.*'=>'required|string|max:4096', 'empresa_id' => 'nullable|integer', 'centro_costo_nombre' => $r->boolean('origen_est') ? 'required|string|max:200|exists:talana_contratos,centro_costo_nombre' : 'nullable|string|max:200', 'cargo_nombre' => 'nullable|string|max:200', 'tipo_contrato_nombre' => 'nullable|string|max:200']);
        $details = app(\App\Services\RrhhDocumentBatteryService::class)->selections($d['documentos'], (int)$r->user()->id);
        $docs = array_keys($details);
        if (DB::table('rrhh_document_batteries')->where('nombre', $d['nombre'])->where('version', $d['version'])->exists()) {
            return back()->withErrors(['nombre' => 'Ya existe esa batería y versión. Crea una nueva versión.'])->withInput();
        }
        try {
            $batteryId = DB::transaction(function () use ($details,$d,$docs,$r) {
                $id = DB::table('rrhh_document_batteries')->insertGetId(['document_details'=>json_encode($details), 'nombre' => $d['nombre'], 'version' => $d['version'], 'documentos' => json_encode($docs, JSON_UNESCAPED_UNICODE), 'reglas' => json_encode(array_intersect_key($d, array_flip(['empresa_id', 'centro_costo_nombre', 'cargo_nombre', 'tipo_contrato_nombre']))), 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
                app(\App\Services\RrhhDocumentBatteryService::class)->event($id,(int)$r->user()->id,'create');
                return $id;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return back()->withErrors(['nombre'=>'Ya existe esa batería y versión. Abre su detalle para continuar.'])->withInput();
        }

        return redirect()->route('rrhh-documentos.battery-show', $batteryId)->with('success', 'Borrador creado. Abre y valida los documentos antes de activar la batería.');
    }

    public function store(Request $r, RrhhDocumentDraftService $service)
    {
        $filters = $this->filters($r);
        $data = $r->validate(['nombre' => 'required|string|max:150', 'request_id' => 'required|uuid', 'battery_id' => 'required|integer', 'seleccion' => 'required|in:manual,todos', 'contratos' => 'nullable|array|max:5000', 'contratos.*' => 'integer|distinct']);
        $id = $service->create($data + $filters, $r->user()->id);

        return redirect()->route('rrhh-documentos.show', $id);
    }

    public function show(Request $request, string $id)
    {
        $batch = DB::table('rrhh_document_batches')->where('id', $id)->first();
        abort_unless($batch, 404);
        $filter = $request->validate(['revision' => 'nullable|in:activos,observados,listos,retirados'])['revision'] ?? 'activos';
        $query = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->where('excluido', $filter === 'retirados');
        if ($filter === 'observados') {
            $query->whereJsonLength('observaciones', '>', 0);
        }
        if ($filter === 'listos') {
            $query->whereJsonLength('observaciones', 0);
        }
        $items = $query->orderBy('id')->paginate(50)->withQueryString();
        $events = DB::table('rrhh_document_batch_events')->where('batch_id', $id)->orderByDesc('id')->paginate(20, ['*'], 'historial_pagina')->withQueryString();

        $files = DB::table('rrhh_document_deliveries')->whereIn('item_id', $items->pluck('id'))->get()->groupBy('item_id');

        return view('rrhh_documentos.show', compact('batch', 'items', 'filter', 'events', 'files'));
    }

    public function review(Request $request, string $id, RrhhDocumentReviewService $service)
    {
        $data = $request->validate(['accion' => 'required|in:retirar,restaurar,cancelar', 'item_id' => 'required_unless:accion,cancelar|integer', 'motivo' => 'required|string|min:3|max:500']);
        $service->update($id, $data, $request->user()->id);

        return back()->with('success', 'Revisión guardada en el historial del lote.');
    }
}
