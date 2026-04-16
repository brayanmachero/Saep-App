<?php

namespace App\Http\Controllers;

use App\Models\CentroCosto;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CentroCostoController extends Controller
{
    public function index()
    {
        $centros = CentroCosto::orderBy('nombre')->get();
        return view('centros_costo.index', compact('centros'));
    }

    public function create()
    {
        return view('centros_costo.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:200',
            'razon_social' => 'required|in:NORMAL,TRANSITORIO',
        ]);
        $codigo = $this->generarCodigo($request->nombre);
        CentroCosto::create(['codigo' => $codigo] + $request->only(['nombre','razon_social']));
        return redirect()->route('centros-costo.index')->with('success', 'Centro de costo creado.');
    }

    public function show(CentroCosto $centrosCosto)
    {
        return redirect()->route('centros-costo.index');
    }

    public function edit(CentroCosto $centrosCosto)
    {
        return view('centros_costo.edit', ['centroCosto' => $centrosCosto]);
    }

    public function update(Request $request, CentroCosto $centrosCosto)
    {
        $request->validate([
            'nombre' => 'required|string|max:200',
            'razon_social' => 'required|in:NORMAL,TRANSITORIO',
        ]);
        $codigo = $this->generarCodigo($request->nombre, $centrosCosto->id);
        $centrosCosto->update(['codigo' => $codigo] + $request->only(['nombre','razon_social','activo']));
        return redirect()->route('centros-costo.index')->with('success', 'Centro de costo actualizado.');
    }

    public function destroy(CentroCosto $centrosCosto)
    {
        $centrosCosto->update(['activo' => false]);
        return redirect()->route('centros-costo.index')->with('success', 'Centro de costo desactivado.');
    }

    private function generarCodigo(string $nombre, ?int $excludeId = null): string
    {
        $base = Str::upper(Str::slug($nombre, '_'));
        $codigo = Str::limit($base, 50, '');
        $suffix = 0;

        while (CentroCosto::where('codigo', $codigo)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $suffix++;
            $codigo = Str::limit($base, 47, '') . '_' . $suffix;
        }

        return $codigo;
    }

    // =====================================================
    // IMPORTACIÓN MASIVA CSV
    // =====================================================

    public function descargarPlantilla()
    {
        $headers = ['nombre', 'razon_social'];
        $ejemplo = ['Empresa Ejemplo S.A.', 'NORMAL'];

        $callback = function () use ($headers, $ejemplo) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, $headers, ';');
            fputcsv($f, $ejemplo, ';');
            fclose($f);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_centros_costo.csv"',
        ]);
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $path = $request->file('archivo')->getRealPath();
        $rows = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $bom = fread($handle, 3);
            if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
                rewind($handle);
            }
            $headers = fgetcsv($handle, 0, ';');
            if ($headers) {
                $headers = array_map(fn($h) => strtolower(trim($h)), $headers);
                while (($line = fgetcsv($handle, 0, ';')) !== false) {
                    if (count($line) === count($headers)) {
                        $rows[] = array_combine($headers, $line);
                    }
                }
            }
            fclose($handle);
        }

        if (empty($rows)) {
            return back()->with('error', 'El archivo está vacío o el formato no es válido. Use la plantilla CSV con separador punto y coma (;).');
        }

        $creados = 0;
        $actualizados = 0;
        $errores = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $fila = $i + 2;
                $nombre = trim($row['nombre'] ?? '');

                if (!$nombre) {
                    $errores[] = "Fila {$fila}: nombre es obligatorio.";
                    continue;
                }

                $razonSocial = strtoupper(trim($row['razon_social'] ?? 'NORMAL'));
                if (!in_array($razonSocial, ['NORMAL', 'TRANSITORIO'])) {
                    $razonSocial = 'NORMAL';
                }

                $existente = CentroCosto::whereRaw('LOWER(nombre) = ?', [strtolower($nombre)])->first();

                if ($existente) {
                    $existente->update([
                        'razon_social' => $razonSocial,
                        'activo' => true,
                    ]);
                    $actualizados++;
                } else {
                    $codigo = $this->generarCodigo($nombre);
                    CentroCosto::create([
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'razon_social' => $razonSocial,
                    ]);
                    $creados++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error importando centros de costo: ' . $e->getMessage());
            return back()->with('error', 'Error al importar: por favor revise el formato del archivo.');
        }

        $msg = '';
        if ($creados) $msg .= "{$creados} centros creados. ";
        if ($actualizados) $msg .= "{$actualizados} centros actualizados. ";
        if (!$creados && !$actualizados) $msg = 'No se importaron registros. ';
        if (!empty($errores)) {
            $msg .= 'Advertencias: ' . implode(' | ', array_slice($errores, 0, 5));
        }

        return back()->with('success', trim($msg));
    }
}
