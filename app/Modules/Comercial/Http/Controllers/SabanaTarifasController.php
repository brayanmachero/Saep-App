<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\TarifaFuente;
use App\Modules\Comercial\Models\TarifaReferencia;
use App\Modules\Comercial\Services\SabanaTarifasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SabanaTarifasController
{
    private function filtros(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:255', 'cliente' => 'nullable|string|max:255',
            'centro' => 'nullable|string|max:255', 'cargo' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:255', 'modalidad' => 'nullable|string|max:255',
            'unidad' => 'nullable|string|max:255', 'fecha' => 'nullable|date_format:Y-m-d',
            'vista' => 'nullable|in:ultimas,referencias,vigentes,pendientes,historico',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);
    }

    public function index(Request $request, SabanaTarifasService $service)
    {
        $filtros = $this->filtros($request);
        $tarifas = $service->consulta($filtros)->paginate(40)->withQueryString();
        $opciones = [];
        foreach (['cliente', 'centro', 'cargo', 'categoria', 'modalidad', 'unidad'] as $campo) {
            $opciones[$campo] = TarifaReferencia::whereNotNull($campo)->distinct()->orderBy($campo)->pluck($campo);
        }
        $resumen = [
            'fuentes' => TarifaFuente::count(), 'referencias' => TarifaReferencia::count(),
            'pendientes' => TarifaReferencia::where('estado', 'pendiente')->count(),
            'vigentes' => $service->consulta(['vista' => 'vigentes'])->count(),
        ];
        $fuentes = TarifaFuente::withCount('tarifas')->orderBy('carpeta')->orderBy('archivo')->get();

        return view('comercial::tarifas.index', compact('tarifas', 'opciones', 'filtros', 'resumen', 'fuentes'));
    }

    public function show(TarifaReferencia $tarifa)
    {
        $tarifa->load('fuente', 'revisiones');
        $versiones = TarifaReferencia::where('grupo', $tarifa->grupo)->orderByDesc('fecha_referencia')->get();

        return view('comercial::tarifas.show', compact('tarifa', 'versiones'));
    }

    public function create(Request $request)
    {
        $request->validate(['base' => 'nullable|integer|exists:comercial_tarifas_referencia,id']);
        $base = $request->filled('base') ? TarifaReferencia::findOrFail($request->integer('base')) : new TarifaReferencia;

        return view('comercial::tarifas.create', compact('base'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'cliente' => 'required|string|max:255', 'centro' => 'nullable|string|max:255',
            'cargo' => 'required|string|max:255', 'categoria' => 'nullable|string|max:255',
            'modalidad' => 'nullable|string|max:255', 'unidad' => 'required|string|max:100',
            'variante' => 'nullable|string|max:255', 'moneda' => 'required|in:CLP,UF,USD',
            'precio' => 'required|numeric|min:0|max:999999999999',
            'fecha_referencia' => 'required|date_format:Y-m-d',
            'observaciones' => 'required|string|max:4000',
        ]);
        $tarifa = DB::transaction(function () use ($datos, $request) {
            $identidad = hash('sha256', (string) Str::uuid());
            $fuente = TarifaFuente::create([
                'identidad' => $identidad, 'hash_sha256' => hash('sha256', json_encode($datos)),
                'archivo' => 'Registro manual — '.$request->user()->name, 'carpeta' => 'Comercial / Registro manual',
                'estado' => 'leida', 'observaciones' => $datos['observaciones'],
            ]);
            $tarifa = TarifaReferencia::create($datos + [
                'identidad' => $identidad, 'grupo' => TarifaReferencia::grupoPara($datos),
                'fuente_id' => $fuente->id, 'estado' => 'pendiente',
            ]);
            $tarifa->revisiones()->create(['usuario_id' => $request->user()->id, 'antes' => [], 'despues' => $tarifa->toArray()]);

            return $tarifa;
        });

        return redirect()->route('comercial.tarifas.show', $tarifa)->with('success', 'Referencia registrada. Confirma su vigencia después de revisar el origen.');
    }

    public function revisar(Request $request, TarifaReferencia $tarifa)
    {
        $datos = $request->validate([
            'categoria' => 'nullable|string|max:255', 'modalidad' => 'required|string|max:100',
            'cliente' => 'sometimes|required|string|max:255', 'centro' => 'sometimes|nullable|string|max:255',
            'cargo' => 'sometimes|required|string|max:255', 'variante' => 'sometimes|nullable|string|max:255',
            'unidad' => 'required|string|max:100',
            'estado' => ['required', Rule::in(['pendiente', 'confirmada', 'descartada'])],
            'vigente_desde' => 'nullable|required_if:estado,confirmada|date_format:Y-m-d',
            'vigente_hasta' => 'nullable|date_format:Y-m-d|after_or_equal:vigente_desde',
            'motivo' => 'required|string|max:2000',
        ]);
        abort_if($datos['estado'] === 'confirmada' && $tarifa->precio === null, 422, 'No se puede confirmar una tarifa sin precio.');
        if ($datos['estado'] === 'confirmada') {
            abort_if(($datos['cliente'] ?? $tarifa->cliente) === 'Por homologar', 422, 'Identifica el cliente antes de confirmar.');
            abort_if(collect($tarifa->conceptos ?? [])->contains(fn ($c) => is_string($c['valor'] ?? null) && str_starts_with($c['valor'], '#')), 422, 'El desglose contiene errores de Excel. Registra una versión corregida.');
        }
        DB::transaction(function () use ($datos, $request, $tarifa) {
            $registro = TarifaReferencia::lockForUpdate()->findOrFail($tarifa->id);
            $antes = $registro->toArray();
            $registro->fill(collect($datos)->except('motivo')->all());
            $registro->grupo = TarifaReferencia::grupoPara($registro->toArray());
            $registro->revisado_por = $request->user()->id;
            $registro->revisado_at = now();
            $registro->save();
            $registro->revisiones()->create([
                'usuario_id' => $request->user()->id, 'antes' => $antes,
                'despues' => $registro->toArray() + ['motivo_revision' => $datos['motivo']],
            ]);
        });

        return back()->with('success', 'Revisión guardada con historial.');
    }

    public function export(Request $request, SabanaTarifasService $service)
    {
        $query = $service->consulta($this->filtros($request));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Cliente', 'Centro', 'Cargo', 'Categoría', 'Modalidad', 'Unidad', 'Variante', 'Moneda', 'Tarifa', 'Período origen', 'Fecha referencia', 'Estado revisión', 'Vigente desde', 'Vigente hasta', 'Archivo', 'Hoja', 'Celda', 'Observaciones'], ';');
            foreach ($query->lazy(200) as $tarifa) {
                $fila = [$tarifa->cliente, $tarifa->centro, $tarifa->cargo, $tarifa->categoria, $tarifa->modalidad, $tarifa->unidad, $tarifa->variante, $tarifa->moneda,
                    $tarifa->precio, $tarifa->periodo, $tarifa->fecha_referencia?->format('Y-m-d'), $tarifa->estado,
                    $tarifa->vigente_desde?->format('Y-m-d'), $tarifa->vigente_hasta?->format('Y-m-d'),
                    $tarifa->fuente?->archivo, $tarifa->hoja, $tarifa->celda, $tarifa->observaciones];
                fputcsv($out, array_map(fn ($valor) => is_string($valor) && preg_match('/^[\s]*[=+@-]/u', $valor) ? "'".$valor : $valor, $fila), ';');
            }
            fclose($out);
        }, 'sabana-tarifas-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
