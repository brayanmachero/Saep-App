<?php

namespace App\Http\Controllers;

use App\Models\AccidenteSst;
use App\Models\CentroCosto;
use App\Models\OpcionAccidenteSst;
use App\Models\User;
use App\Services\KizeoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccidenteSstController extends Controller
{
    public function index(Request $request)
    {
        $query = AccidenteSst::with(['centroCosto', 'trabajador', 'registradoPor']);

        // Filtros de búsqueda
        if ($request->filled('buscar')) {
            $term = $request->buscar;
            $query->where(function ($q) use ($term) {
                $q->where('trabajador_nombre', 'like', "%{$term}%")
                  ->orWhere('trabajador_rut', 'like', "%{$term}%")
                  ->orWhere('numero_diat', 'like', "%{$term}%")
                  ->orWhere('id', $term);
            });
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_accidente', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_accidente', '<=', $request->fecha_hasta);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('gravedad')) {
            $query->where('gravedad', $request->gravedad);
        }

        $accidentes = $query->orderByDesc('fecha_accidente')->paginate(20)->withQueryString();

        return view('accidentes_sst.index', compact('accidentes'));
    }

    public function create(KizeoService $kizeo)
    {
        $centros   = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $personal  = collect($kizeo->getPersonalVigente())->sortBy('label')->values();
        $lesiones  = OpcionAccidenteSst::tipo('lesion')->activo()->orderBy('nombre')->get();
        $causas    = OpcionAccidenteSst::tipo('causa')->activo()->orderBy('nombre')->get();
        $medidas   = OpcionAccidenteSst::tipo('medida')->activo()->orderBy('nombre')->get();

        return view('accidentes_sst.create', compact('centros', 'personal', 'lesiones', 'causas', 'medidas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo'            => 'required|string',
            'fecha_accidente' => 'required|date',
            'descripcion'     => 'required|string',
            'gravedad'        => 'required|string',
            'centro_costo_id' => 'required|exists:centros_costo,id',
        ]);

        $data = $request->except(['_token', 'trabajador_data', 'lesiones_ids', 'causas_ids', 'medidas_ids']);
        $data['registrado_por'] = auth()->id();
        $data['estado'] = 'ingresado';

        // Parsear datos del trabajador seleccionado de Kizeo
        if ($request->filled('trabajador_data')) {
            $trabajador = json_decode($request->input('trabajador_data'), true);
            if ($trabajador) {
                $data['trabajador_kizeo_id'] = $trabajador['id'] ?? null;
                $data['trabajador_nombre']   = $trabajador['label'] ?? null;
                $data['trabajador_rut']      = $trabajador['rut'] ?? null;
                $data['trabajador_cargo']    = $trabajador['cargo'] ?? null;
            }
        }

        $accidente = AccidenteSst::create($data);

        // Sincronizar opciones (lesiones, causas, medidas)
        $opcionIds = array_merge(
            $request->input('lesiones_ids', []),
            $request->input('causas_ids', []),
            $request->input('medidas_ids', [])
        );
        $accidente->opciones()->sync(array_filter($opcionIds));

        return redirect()->route('accidentes-sst.index')->with('success', 'Accidente registrado correctamente.');
    }

    public function show(AccidenteSst $accidentesSst)
    {
        $accidenteSst = $accidentesSst->load([
            'centroCosto', 'trabajador', 'registradoPor',
            'lesionesOpciones', 'causasOpciones', 'medidasOpciones',
        ]);
        return view('accidentes_sst.show', compact('accidenteSst'));
    }

    public function edit(AccidenteSst $accidentesSst, KizeoService $kizeo)
    {
        $accidenteSst = $accidentesSst->load(['lesionesOpciones', 'causasOpciones', 'medidasOpciones']);
        $centros   = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $personal  = collect($kizeo->getPersonalVigente())->sortBy('label')->values();
        $lesiones  = OpcionAccidenteSst::tipo('lesion')->activo()->orderBy('nombre')->get();
        $causas    = OpcionAccidenteSst::tipo('causa')->activo()->orderBy('nombre')->get();
        $medidas   = OpcionAccidenteSst::tipo('medida')->activo()->orderBy('nombre')->get();

        return view('accidentes_sst.edit', compact('accidenteSst', 'centros', 'personal', 'lesiones', 'causas', 'medidas'));
    }

    public function update(Request $request, AccidenteSst $accidentesSst)
    {
        $request->validate([
            'tipo'            => 'required|string',
            'fecha_accidente' => 'required|date',
            'descripcion'     => 'required|string',
            'gravedad'        => 'required|string',
            'centro_costo_id' => 'required|exists:centros_costo,id',
        ]);

        $data = $request->except(['_token', '_method', 'trabajador_data', 'lesiones_ids', 'causas_ids', 'medidas_ids']);

        if ($request->filled('trabajador_data')) {
            $trabajador = json_decode($request->input('trabajador_data'), true);
            if ($trabajador) {
                $data['trabajador_kizeo_id'] = $trabajador['id'] ?? null;
                $data['trabajador_nombre']   = $trabajador['label'] ?? null;
                $data['trabajador_rut']      = $trabajador['rut'] ?? null;
                $data['trabajador_cargo']    = $trabajador['cargo'] ?? null;
            }
        }

        $accidentesSst->update($data);

        // Sincronizar opciones
        $opcionIds = array_merge(
            $request->input('lesiones_ids', []),
            $request->input('causas_ids', []),
            $request->input('medidas_ids', [])
        );
        $accidentesSst->opciones()->sync(array_filter($opcionIds));

        return redirect()->route('accidentes-sst.show', $accidentesSst)->with('success', 'Accidente actualizado.');
    }

    /**
     * Acción rápida: actualizar estado y/o días perdidos (AJAX).
     */
    public function accionRapida(Request $request, AccidenteSst $accidentesSst)
    {
        $request->validate([
            'estado'        => 'sometimes|string|in:ingresado,aceptado,rechazado,aprobado,cerrado',
            'dias_perdidos' => 'sometimes|integer|min:0',
        ]);

        if ($request->filled('estado')) {
            $accidentesSst->estado = $request->estado;
        }
        if ($request->has('dias_perdidos')) {
            $accidentesSst->dias_perdidos = $request->dias_perdidos;
        }

        $accidentesSst->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'estado'  => $accidentesSst->estadoBadge,
                'dias'    => $accidentesSst->dias_perdidos,
            ]);
        }

        return back()->with('success', 'Caso actualizado.');
    }

    public function destroy(AccidenteSst $accidentesSst)
    {
        $accidentesSst->delete();
        return redirect()->route('accidentes-sst.index')->with('success', 'Registro eliminado.');
    }
}
