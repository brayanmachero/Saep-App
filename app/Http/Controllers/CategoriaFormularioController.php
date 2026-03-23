<?php

namespace App\Http\Controllers;

use App\Models\CategoriaFormulario;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoriaFormularioController extends Controller
{
    public function index()
    {
        $categorias = CategoriaFormulario::withCount('formularios')->orderBy('orden')->get();
        return view('categorias_formularios.index', compact('categorias'));
    }

    public function create()
    {
        return view('categorias_formularios.create');
    }

    public function store(Request $request)
    {
        $request->validate(['nombre' => 'required|string|max:200']);
        $data = $request->only(['nombre','descripcion','icono','color','orden']);
        $data['icono'] = $data['icono'] ?: 'bi-folder';
        $data['color'] = $data['color'] ?: '#0d6efd';
        $data['orden'] = $data['orden'] ?? CategoriaFormulario::max('orden') + 1;
        CategoriaFormulario::create($data);
        return redirect()->route('categorias-formularios.index')->with('success', 'Categoria creada.');
    }

    public function show(CategoriaFormulario $categoriaFormulario)
    {
        return redirect()->route('categorias-formularios.index');
    }

    public function edit(CategoriaFormulario $categoriaFormulario)
    {
        return view('categorias_formularios.edit', ['categoria' => $categoriaFormulario]);
    }

    public function update(Request $request, CategoriaFormulario $categoriaFormulario)
    {
        $request->validate(['nombre' => 'required|string|max:200']);
        $categoriaFormulario->update($request->only(['nombre','descripcion','icono','color','orden','activo']));
        return redirect()->route('categorias-formularios.index')->with('success', 'Categoria actualizada.');
    }

    public function destroy(CategoriaFormulario $categoriaFormulario)
    {
        $categoriaFormulario->update(['activo' => false]);
        return redirect()->route('categorias-formularios.index')->with('success', 'Categoria desactivada.');
    }
}
