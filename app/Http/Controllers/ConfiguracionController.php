<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $configs = Configuracion::orderBy('categoria')->orderBy('clave')->get()->groupBy('categoria');
        return view('configuraciones.index', compact('configs'));
    }

    public function update(Request $request)
    {
        $data = $request->except(['_token','_method']);
        foreach ($data as $clave => $valor) {
            $config = Configuracion::where('clave', $clave)->where('editable', true)->first();
            if ($config) {
                if ($config->tipo === 'PASSWORD' && empty($valor)) continue;
                $config->update(['valor' => $valor]);
            }
        }
        return redirect()->route('configuraciones.index')->with('success', 'Configuración guardada.');
    }
}
