<?php

namespace App\Http\Controllers;

use App\Models\TalanaContrato;
use App\Services\RrhhDocumentDraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RrhhDocumentController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate(['empresa_id' => 'nullable|integer', 'centro_costo_nombre' => 'nullable|string|max:200', 'cargo_nombre' => 'nullable|string|max:200', 'tipo_contrato_nombre' => 'nullable|string|max:200', 'buscar' => 'nullable|string|max:100']);
    }

    public function index(Request $r, RrhhDocumentDraftService $service)
    {
        $filters = $this->filters($r);
        $contracts = $service->query($filters)->orderBy('persona_nombre')->paginate(50)->withQueryString();
        $options = [];
        foreach (['empresa_nombre', 'centro_costo_nombre', 'cargo_nombre', 'tipo_contrato_nombre'] as $field) {
            $options[$field] = TalanaContrato::whereNotNull($field)->where($field, '!=', '')->distinct()->orderBy($field)->pluck($field);
        }
        $companies = TalanaContrato::select('empresa_id', 'empresa_nombre')->distinct()->get();

        return view('rrhh_documentos.index', compact('contracts', 'filters', 'options', 'companies') + ['batteries' => DB::table('rrhh_document_batteries')->orderByDesc('id')->get(), 'batches' => DB::table('rrhh_document_batches')->orderByDesc('created_at')->paginate(10, ['*'], 'lotes_pagina')]);
    }

    public function battery(Request $r)
    {
        $d = $r->validate(['nombre' => 'required|string|max:150', 'version' => 'required|integer|min:1|max:9999', 'documentos' => 'required|string|max:12000', 'empresa_id' => 'nullable|integer', 'centro_costo_nombre' => 'nullable|string|max:200', 'cargo_nombre' => 'nullable|string|max:200', 'tipo_contrato_nombre' => 'nullable|string|max:200']);
        $docs = array_values(array_unique(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $d['documentos'])))));
        if (! $docs || count($docs) > 50 || collect($docs)->contains(fn ($v) => mb_strlen($v) > 200)) {
            return back()->withErrors(['documentos' => 'Incluye entre 1 y 50 nombres de documentos, de hasta 200 caracteres.'])->withInput();
        }
        if (DB::table('rrhh_document_batteries')->where('nombre', $d['nombre'])->where('version', $d['version'])->exists()) {
            return back()->withErrors(['nombre' => 'Ya existe esa batería y versión. Crea una nueva versión.'])->withInput();
        }
        DB::table('rrhh_document_batteries')->insert(['nombre' => $d['nombre'], 'version' => $d['version'], 'documentos' => json_encode($docs, JSON_UNESCAPED_UNICODE), 'reglas' => json_encode(array_intersect_key($d, array_flip(['empresa_id', 'centro_costo_nombre', 'cargo_nombre', 'tipo_contrato_nombre']))), 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('rrhh-documentos.index')->with('success', 'Batería en borrador creada. Es una definición documental; aún no contiene plantillas ni genera PDFs.');
    }

    public function store(Request $r, RrhhDocumentDraftService $service)
    {
        $filters = $this->filters($r);
        $data = $r->validate(['nombre' => 'required|string|max:150', 'request_id' => 'required|uuid', 'battery_id' => 'required|integer', 'seleccion' => 'required|in:manual,todos', 'contratos' => 'nullable|array|max:5000', 'contratos.*' => 'integer|distinct']);
        $id = $service->create($data + $filters, $r->user()->id);

        return redirect()->route('rrhh-documentos.show', $id);
    }

    public function show(string $id)
    {
        $batch = DB::table('rrhh_document_batches')->where('id', $id)->first();
        abort_unless($batch, 404);
        $items = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->orderBy('id')->paginate(50);

        return view('rrhh_documentos.show',compact('batch','items'));
    }
}
