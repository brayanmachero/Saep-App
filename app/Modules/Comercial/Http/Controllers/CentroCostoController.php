<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\CentroCosto;
use App\Modules\Comercial\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CentroCostoController
{
    /**
     * Listar centros de costo
     */
    public function index()
    {
        $centrosCosto = CentroCosto::with('cliente')
            ->orderBy('nombre')
            ->paginate(20);

        return view('comercial::centros-costo.index', compact('centrosCosto'));
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

        return redirect()->route('comercial.centros-costo.index')
            ->with('success', 'Centro de costo creado');
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

        return redirect()->route('comercial.centros-costo.index')
            ->with('success', 'Centro de costo eliminado');
    }
}
