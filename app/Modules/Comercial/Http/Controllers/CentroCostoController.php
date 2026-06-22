<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\CentroCosto;
use App\Modules\Comercial\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CentroCostoController
{
    /**
     * Listar centros de costo
     */
    public function index()
    {
        return redirect()->route('comercial.clientes.index', ['tab' => 'centros']);
    }

    /**
     * Formulario de creación
     */
    public function create()
    {
        $clientes = Cliente::activos()->get();
        return view('comercial::centros-costo.create', compact('clientes'));
    }

    /**
     * Guardar centro de costo
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'exists:comercial_clientes,id'],
            'nombre' => ['required', 'string', 'max:180'],
            'codigo' => ['nullable', 'string', 'max:80', Rule::unique('comercial_centros_costo', 'codigo')],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'ubicacion' => ['nullable', 'string', 'max:180'],
            'responsable' => ['nullable', 'string', 'max:180'],
            'email_responsable' => ['nullable', 'email', 'max:180'],
            'estado' => ['nullable', 'in:activo,inactivo'],
        ]);

        $validated['estado'] ??= 'activo';
        $centro = CentroCosto::create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'centro_costo' => [
                    'id' => $centro->id,
                    'cliente_id' => $centro->cliente_id,
                    'nombre' => $centro->nombre,
                    'codigo' => $centro->codigo,
                    'label' => $centro->nombre,
                ],
            ], 201);
        }

        return redirect()->route('comercial.clientes.index', ['tab' => 'centros'])
            ->with('success', 'Centro de costo creado');
    }

    public function importar(Request $request)
    {
        $validated = $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $resultado = DB::transaction(function () use ($validated) {
            $file = fopen($validated['archivo']->getRealPath(), 'r');
            $clientesCreados = 0;
            $centrosCreados = 0;
            $linea = 0;

            while (($raw = fgets($file)) !== false) {
                $linea++;
                $raw = trim($raw);

                if ($raw === '') {
                    continue;
                }

                $delimiter = str_contains($raw, ';') ? ';' : ',';
                $row = array_map('trim', str_getcsv($raw, $delimiter));
                $clienteNombre = $row[0] ?? '';
                $centroNombre = $row[1] ?? '';
                $centroCodigo = trim((string) ($row[2] ?? ''));

                if ($linea === 1 && in_array(mb_strtolower($clienteNombre), ['cliente', 'nombre_cliente'], true)) {
                    continue;
                }

                if ($clienteNombre === '' || $centroNombre === '') {
                    continue;
                }

                $cliente = Cliente::where('nombre', $clienteNombre)
                    ->orWhere('nombre_comercial', $clienteNombre)
                    ->first();

                if (! $cliente) {
                    $cliente = Cliente::create([
                        'nombre' => $clienteNombre,
                        'estado' => 'activo',
                    ]);
                    $clientesCreados++;
                }

                $centroExiste = CentroCosto::where('cliente_id', $cliente->id)
                    ->where('nombre', $centroNombre)
                    ->exists();

                if ($centroExiste) {
                    continue;
                }

                $codigoDisponible = $centroCodigo !== ''
                    && ! CentroCosto::where('codigo', $centroCodigo)->exists();

                CentroCosto::create([
                    'cliente_id' => $cliente->id,
                    'nombre' => $centroNombre,
                    'codigo' => $codigoDisponible ? $centroCodigo : null,
                    'estado' => 'activo',
                ]);
                $centrosCreados++;
            }

            fclose($file);

            return compact('clientesCreados', 'centrosCreados');
        });

        return redirect()->route('comercial.clientes.index', ['tab' => 'centros'])->with(
            'success',
            "Importación completada: {$resultado['clientesCreados']} clientes y {$resultado['centrosCreados']} centros creados."
        );
    }

    /**
     * Mostrar centro de costo
     */
    public function show(CentroCosto $centroCosto)
    {
        $centroCosto->load('cliente', 'cotizaciones');
        return view('comercial::centros-costo.show', compact('centroCosto'));
    }

    /**
     * Formulario de edición
     */
    public function edit(CentroCosto $centroCosto)
    {
        $clientes = Cliente::activos()->get();
        return view('comercial::centros-costo.edit', compact('centroCosto', 'clientes'));
    }

    /**
     * Actualizar
     */
    public function update(Request $request, CentroCosto $centroCosto)
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'exists:comercial_clientes,id'],
            'nombre' => ['required', 'string', 'max:180'],
            'codigo' => ['nullable', 'string', 'max:80', Rule::unique('comercial_centros_costo', 'codigo')->ignore($centroCosto->id)],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'ubicacion' => ['nullable', 'string', 'max:180'],
            'responsable' => ['nullable', 'string', 'max:180'],
            'email_responsable' => ['nullable', 'email', 'max:180'],
            'estado' => ['nullable', 'in:activo,inactivo'],
        ]);

        $centroCosto->update($validated);

        return redirect()->route('comercial.centros-costo.show', $centroCosto)
            ->with('success', 'Centro de costo actualizado');
    }

    /**
     * Eliminar
     */
    public function destroy(CentroCosto $centroCosto)
    {
        $centroCosto->delete();

        return redirect()->route('comercial.clientes.index', ['tab' => 'centros'])
            ->with('success', 'Centro de costo eliminado');
    }
}
