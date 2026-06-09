<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\CentroCosto;
use App\Modules\Comercial\Models\Cliente;
use Illuminate\Http\Request;

class CentroCostoController
{
    /**
     * Listar centros de costo
     */
    public function index()
    {
        $centrosCosto = CentroCosto::with('cliente')->paginate(20);
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
            'cliente_id' => 'required|exists:comercial_clientes,id',
            'nombre' => 'required|string',
            'codigo' => 'required|unique:comercial_centros_costo,codigo',
            'descripcion' => 'nullable|string',
            'ubicacion' => 'nullable|string',
            'responsable' => 'nullable|string',
            'email_responsable' => 'nullable|email',
            'estado' => 'nullable|in:activo,inactivo',
        ]);

        CentroCosto::create($validated);

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
            'cliente_id' => 'required|exists:comercial_clientes,id',
            'nombre' => 'required|string',
            'codigo' => "required|unique:comercial_centros_costo,codigo,{$centroCosto->id}",
            'descripcion' => 'nullable|string',
            'ubicacion' => 'nullable|string',
            'responsable' => 'nullable|string',
            'email_responsable' => 'nullable|email',
            'estado' => 'nullable|in:activo,inactivo',
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
