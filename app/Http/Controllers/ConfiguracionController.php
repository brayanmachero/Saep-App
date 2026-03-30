<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $configuraciones = Configuracion::orderBy('categoria')->orderBy('clave')->get();
        return view('configuraciones.index', compact('configuraciones'));
    }

    public function update(Request $request)
    {
        $items = $request->input('config', []);
        foreach ($items as $clave => $valor) {
            $config = Configuracion::where('clave', $clave)->where('editable', true)->first();
            if ($config) {
                if ($config->tipo === 'PASSWORD' && empty($valor)) continue;
                $config->update(['valor' => $valor]);
            }
        }
        return redirect()->route('configuraciones.index')->with('success', 'Configuración guardada.');
    }
}
