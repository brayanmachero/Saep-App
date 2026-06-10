<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\Cliente;
use App\Modules\Comercial\Models\CentroCosto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClienteController
{
    /**
     * Listar clientes
     */
    public function index()
    {
        $clientes = Cliente::withCount('centrosCosto')
            ->orderBy('nombre')
            ->paginate(20);

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
            'nombre' => ['required', 'string', 'max:180'],
            'rut' => ['nullable', 'string', 'max:30', Rule::unique('comercial_clientes', 'rut')],
            'nombre_comercial' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180', Rule::unique('comercial_clientes', 'email')],
            'telefono' => ['nullable', 'string', 'max:80'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'contacto_principal' => ['nullable', 'string', 'max:180'],
            'contacto_email' => ['nullable', 'email', 'max:180'],
            'contacto_telefono' => ['nullable', 'string', 'max:80'],
            'estado' => ['nullable', 'in:activo,inactivo'],
        ]);

        $validated['estado'] ??= 'activo';
        $validated['nombre_comercial'] ??= null;
        $cliente = Cliente::create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'cliente' => [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'label' => $cliente->nombre_comercial ?: $cliente->nombre,
                ],
            ], 201);
        }

        return redirect()->route('comercial.clientes.index')
            ->with('success', 'Cliente creado exitosamente');
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

                if ($linea === 1 && in_array(mb_strtolower($clienteNombre), ['cliente', 'nombre', 'nombre_cliente'], true)) {
                    continue;
                }

                if ($clienteNombre === '') {
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

                if ($centroNombre === '') {
                    continue;
                }

                $centroExiste = CentroCosto::where('cliente_id', $cliente->id)
                    ->where('nombre', $centroNombre)
                    ->exists();

                if (! $centroExiste) {
                    CentroCosto::create([
                        'cliente_id' => $cliente->id,
                        'nombre' => $centroNombre,
                        'estado' => 'activo',
                    ]);
                    $centrosCreados++;
                }
            }

            fclose($file);

            return compact('clientesCreados', 'centrosCreados');
        });

        return back()->with(
            'success',
            "Importación completada: {$resultado['clientesCreados']} clientes y {$resultado['centrosCreados']} centros creados."
        );
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
            'nombre' => ['required', 'string', 'max:180'],
            'rut' => ['nullable', 'string', 'max:30', Rule::unique('comercial_clientes', 'rut')->ignore($cliente->id)],
            'nombre_comercial' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180', Rule::unique('comercial_clientes', 'email')->ignore($cliente->id)],
            'telefono' => ['nullable', 'string', 'max:80'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'contacto_principal' => ['nullable', 'string', 'max:180'],
            'contacto_email' => ['nullable', 'email', 'max:180'],
            'contacto_telefono' => ['nullable', 'string', 'max:80'],
            'estado' => ['nullable', 'in:activo,inactivo'],
        ]);

        if (! $request->has('nombre_comercial')) {
            $validated['nombre_comercial'] = null;
        }

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
