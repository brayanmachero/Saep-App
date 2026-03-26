<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaSst;
use App\Models\CentroCosto;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuditoriaSstController extends Controller
{
    public function index()
    {
        $auditorias = AuditoriaSst::with(['centroCosto', 'auditor'])
            ->orderByDesc('fecha_auditoria')->paginate(20);
        return view('auditorias_sst.index', compact('auditorias'));
    }

    public function create()
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('auditorias_sst.create', compact('centros', 'usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo'           => 'required|string|max:300',
            'tipo_auditoria'   => 'required|string',
            'fecha_auditoria'  => 'required|date',
            'centro_costo_id'  => 'required|exists:centros_costo,id',
        ]);
        $data = $request->except(['_token']);
        $data['auditor_id'] = $data['auditor_id'] ?? auth()->id();
        AuditoriaSst::create($data);
        return redirect()->route('auditorias-sst.index')->with('success', 'Auditoría registrada correctamente.');
    }

    public function show(AuditoriaSst $auditoriasSst)
    {
        $auditoriaSst = $auditoriasSst->load(['centroCosto', 'auditor']);
        return view('auditorias_sst.show', compact('auditoriaSst'));
    }

    public function edit(AuditoriaSst $auditoriasSst)
    {
        $auditoriaSst = $auditoriasSst;
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('auditorias_sst.edit', compact('auditoriaSst', 'centros', 'usuarios'));
    }

    public function update(Request $request, AuditoriaSst $auditoriasSst)
    {
        $request->validate([
            'titulo'          => 'required|string|max:300',
            'fecha_auditoria' => 'required|date',
            'centro_costo_id' => 'required|exists:centros_costo,id',
        ]);
        $auditoriasSst->update($request->except(['_token', '_method']));
        return redirect()->route('auditorias-sst.show', $auditoriasSst)->with('success', 'Auditoría actualizada.');
    }

    public function destroy(AuditoriaSst $auditoriasSst)
    {
        $auditoriasSst->delete();
        return redirect()->route('auditorias-sst.index')->with('success', 'Auditoría eliminada.');
    }
}