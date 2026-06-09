<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController
{
    /**
     * Listar clientes
     */
    public function index()
    {
        $clientes = Cliente::paginate(20);
        return view('comercial::clientes.index', compact('clientes'));
    }

    /**
     * Formulario de creación
     */
    public function create()
    {
        return view('comercial::clientes.create');
    }

    /**
     * Guardar cliente
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rut' => 'required|unique:comercial_clientes,rut',
            'nombre' => 'required|string',
            'nombre_comercial' => 'nullable|string',
            'email' => 'required|email|unique:comercial_clientes,email',
            'telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
            'ciudad' => 'nullable|string',
            'region' => 'nullable|string',
        ]);

        Cliente::create($validated);

        return redirect()->route('comercial.clientes.index')
            ->with('success', 'Cliente creado exitosamente');
    }

    /**
     * Mostrar cliente
     */
    public function show(Cliente $cliente)
    {
        $cliente->load('centrosCosto', 'cotizaciones');
        return view('comercial::clientes.show', compact('cliente'));
    }

    /**
     * Formulario de edición
     */
    public function edit(Cliente $cliente)
    {
        return view('comercial::clientes.edit', compact('cliente'));
    }

    /**
     * Actualizar cliente
     */
    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'rut' => "required|unique:comercial_clientes,rut,{$cliente->id}",
            'nombre' => 'required|string',
            'nombre_comercial' => 'nullable|string',
            'email' => "required|email|unique:comercial_clientes,email,{$cliente->id}",
            'telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
            'ciudad' => 'nullable|string',
            'region' => 'nullable|string',
        ]);

        $cliente->update($validated);

        return redirect()->route('comercial.clientes.show', $cliente)
            ->with('success', 'Cliente actualizado');
    }

    /**
     * Eliminar cliente
     */
    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return redirect()->route('comercial.clientes.index')
            ->with('success', 'Cliente eliminado');
    }
}
