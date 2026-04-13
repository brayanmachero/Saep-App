<?php

namespace App\Http\Controllers;

use App\Mail\BienvenidaUsuarioMail;
use App\Models\Cargo;
use App\Models\CentroCosto;
use App\Models\Configuracion;
use App\Models\Departamento;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    /**
     * Mostrar la vista de importación.
     */
    public function index()
    {
        return view('importacion.index');
    }

    /**
     * Previsualizar el CSV antes de importar.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'tipo'    => ['required', 'in:usuarios'],
        ]);

        $tipo = $request->tipo;
        $file = $request->file('archivo');
        $path = $file->getRealPath();

        $rows = $this->parseCsv($path);

        if (empty($rows)) {
            return back()->with('error', 'El archivo está vacío o no se pudo leer.');
        }

        $headers = array_keys($rows[0]);
        $preview = array_slice($rows, 0, 20);
        $totalRows = count($rows);

        // Guardar en sesión para la importación
        $request->session()->put('import_data', $rows);
        $request->session()->put('import_tipo', $tipo);

        return view('importacion.preview', compact('headers', 'preview', 'totalRows', 'tipo'));
    }

    /**
     * Ejecutar la importación.
     */
    public function import(Request $request)
    {
        $rows = $request->session()->get('import_data');
        $tipo = $request->session()->get('import_tipo');

        if (!$rows || !$tipo) {
            return redirect()->route('importacion.index')
                ->with('error', 'No hay datos para importar. Sube un archivo primero.');
        }

        $result = match ($tipo) {
            'usuarios' => $this->importUsuarios($rows),
            default    => ['creados' => 0, 'actualizados' => 0, 'errores' => ['Tipo de importación no soportado.']],
        };

        // Limpiar sesión
        $request->session()->forget(['import_data', 'import_tipo']);

        return redirect()->route('importacion.index')
            ->with('success', "Importación completada: {$result['creados']} creados, {$result['actualizados']} actualizados.")
            ->with('import_errores', $result['errores']);
    }

    /**
     * Importar usuarios desde datos CSV (formato Talana).
     */
    private function importUsuarios(array $rows): array
    {
        $creados = 0;
        $actualizados = 0;
        $errores = [];

        // Cache de lookups para evitar queries repetidas
        $cargos = Cargo::all()->keyBy(fn ($c) => mb_strtolower(trim($c->nombre)));
        $departamentos = Departamento::all()->keyBy(fn ($d) => mb_strtolower(trim($d->nombre)));
        $centrosCosto = CentroCosto::all()->keyBy(fn ($cc) => mb_strtolower(trim($cc->nombre)));
        $rolTrabajador = Rol::where('nombre', 'TRABAJADOR')->first();

        // Mapeo de columnas CSV (Talana) → campos de BD
        $columnMap = [
            'rut'               => ['rut', 'rut trabajador', 'rut_trabajador'],
            'name'              => ['nombre', 'nombres', 'name', 'primer nombre'],
            'apellido_paterno'  => ['apellido paterno', 'apellido_paterno', 'primer apellido'],
            'apellido_materno'  => ['apellido materno', 'apellido_materno', 'segundo apellido'],
            'email'             => ['email', 'correo', 'correo electronico', 'correo electrónico', 'e-mail'],
            'cargo'             => ['cargo', 'puesto', 'cargo actual'],
            'departamento'      => ['departamento', 'area', 'área', 'seccion', 'sección'],
            'centro_costo'      => ['centro de costo', 'centro_costo', 'centro costo', 'cc'],
            'tipo_nomina'       => ['tipo nomina', 'tipo_nomina', 'tipo de nomina', 'tipo de nómina', 'tipo nómina'],
            'razon_social'      => ['razon social', 'razón social', 'razon_social', 'empresa'],
            'fecha_nacimiento'  => ['fecha nacimiento', 'fecha_nacimiento', 'fecha de nacimiento', 'nacimiento'],
            'nacionalidad'      => ['nacionalidad', 'pais', 'país'],
            'sexo'              => ['sexo', 'genero', 'género'],
            'estado_civil'      => ['estado civil', 'estado_civil'],
            'fecha_ingreso'     => ['fecha ingreso', 'fecha_ingreso', 'fecha de ingreso', 'ingreso'],
            'telefono'          => ['telefono', 'teléfono', 'telefono contacto', 'celular', 'fono'],
        ];

        foreach ($rows as $i => $row) {
            $fila = $i + 2; // Fila real en el CSV (header = 1)
            try {
                $mapped = $this->mapColumns($row, $columnMap);

                // Validaciones mínimas
                $rut = trim($mapped['rut'] ?? '');
                $name = trim($mapped['name'] ?? '');
                $email = trim($mapped['email'] ?? '');

                if (empty($rut) && empty($email)) {
                    $errores[] = "Fila {$fila}: Sin RUT ni email, se omite.";
                    continue;
                }

                if (empty($name)) {
                    $errores[] = "Fila {$fila}: Sin nombre, se omite.";
                    continue;
                }

                // Buscar usuario existente por RUT o email
                $usuario = null;
                if ($rut) {
                    $usuario = User::where('rut', $rut)->first();
                }
                if (!$usuario && $email) {
                    $usuario = User::where('email', $email)->first();
                }

                // Resolver relaciones
                $cargoNombre = mb_strtolower(trim($mapped['cargo'] ?? ''));
                $depNombre = mb_strtolower(trim($mapped['departamento'] ?? ''));
                $ccNombre = mb_strtolower(trim($mapped['centro_costo'] ?? ''));

                $cargoId = $cargos->get($cargoNombre)?->id;
                $depId = $departamentos->get($depNombre)?->id;
                $ccId = $centrosCosto->get($ccNombre)?->id;

                // Auto-crear cargo si no existe
                if (!$cargoId && $cargoNombre) {
                    $nuevoCargo = Cargo::create(['nombre' => trim($mapped['cargo']), 'activo' => true]);
                    $cargos->put($cargoNombre, $nuevoCargo);
                    $cargoId = $nuevoCargo->id;
                }

                // Auto-crear departamento si no existe
                if (!$depId && $depNombre) {
                    $nuevoDep = Departamento::create(['nombre' => trim($mapped['departamento']), 'activo' => true]);
                    $departamentos->put($depNombre, $nuevoDep);
                    $depId = $nuevoDep->id;
                }

                // Auto-crear centro de costo si no existe
                if (!$ccId && $ccNombre) {
                    $nuevoCC = CentroCosto::create(['nombre' => trim($mapped['centro_costo']), 'activo' => true]);
                    $centrosCosto->put($ccNombre, $nuevoCC);
                    $ccId = $nuevoCC->id;
                }

                $userData = [
                    'name'              => $name,
                    'apellido_paterno'  => trim($mapped['apellido_paterno'] ?? '') ?: null,
                    'apellido_materno'  => trim($mapped['apellido_materno'] ?? '') ?: null,
                    'rut'               => $rut ?: null,
                    'email'             => $email ?: ($usuario?->email ?? $rut . '@importado.local'),
                    'cargo_id'          => $cargoId,
                    'departamento_id'   => $depId,
                    'centro_costo_id'   => $ccId,
                    'tipo_nomina'       => strtoupper(trim($mapped['tipo_nomina'] ?? '')) === 'TRANSITORIO' ? 'TRANSITORIO' : 'NORMAL',
                    'razon_social'      => trim($mapped['razon_social'] ?? '') ?: null,
                    'fecha_nacimiento'  => $this->parseDate($mapped['fecha_nacimiento'] ?? ''),
                    'nacionalidad'      => trim($mapped['nacionalidad'] ?? '') ?: null,
                    'sexo'              => $this->parseSexo($mapped['sexo'] ?? ''),
                    'estado_civil'      => trim($mapped['estado_civil'] ?? '') ?: null,
                    'fecha_ingreso'     => $this->parseDate($mapped['fecha_ingreso'] ?? ''),
                    'telefono'          => trim($mapped['telefono'] ?? '') ?: null,
                ];

                if ($usuario) {
                    // Actualizar sin tocar password ni rol
                    unset($userData['email']); // No cambiar email de existentes si ya lo tienen
                    if ($email && $email !== $usuario->email) {
                        // Solo actualizar email si no colisiona
                        $existeEmail = User::where('email', $email)->where('id', '!=', $usuario->id)->exists();
                        if (!$existeEmail) {
                            $userData['email'] = $email;
                        }
                    }
                    $usuario->update($userData);
                    $actualizados++;
                } else {
                    // Crear nuevo
                    $tempPassword = Str::upper(Str::random(3)) . rand(100, 999) . Str::random(3);
                    $userData['rol_id'] = $rolTrabajador?->id ?? Rol::first()?->id;
                    $userData['password'] = Hash::make($tempPassword);
                    $userData['must_change_password'] = true;
                    $userData['activo'] = true;
                    $nuevoUsuario = User::create($userData);

                    if (Configuracion::get('notificaciones_email') === 'true' && $nuevoUsuario->email && !str_ends_with($nuevoUsuario->email, '@importado.local')) {
                        try {
                            Mail::to($nuevoUsuario->email)->send(new BienvenidaUsuarioMail($nuevoUsuario, $tempPassword));
                        } catch (\Exception $mailEx) {
                            Log::warning("Import fila {$fila}: No se pudo enviar email a {$nuevoUsuario->email}", ['error' => $mailEx->getMessage()]);
                        }
                    }

                    $creados++;
                }
            } catch (\Exception $e) {
                $errores[] = "Fila {$fila}: " . $e->getMessage();
                Log::warning("Import error fila {$fila}", ['error' => $e->getMessage()]);
            }
        }

        return compact('creados', 'actualizados', 'errores');
    }

    /**
     * Parsear CSV con detección de delimitador y encoding.
     */
    private function parseCsv(string $path): array
    {
        $content = file_get_contents($path);

        // Detectar y convertir encoding
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3); // Remove BOM
        }
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Detectar delimitador
        $firstLine = strtok($content, "\n");
        $semicolons = substr_count($firstLine, ';');
        $commas = substr_count($firstLine, ',');
        $delimiter = $semicolons > $commas ? ';' : ',';

        // Parsear
        $lines = explode("\n", $content);
        $rows = [];
        $headers = null;

        foreach ($lines as $line) {
            $line = trim($line, "\r\n");
            if (empty($line)) continue;

            $fields = str_getcsv($line, $delimiter, '"');

            if ($headers === null) {
                $headers = array_map(fn ($h) => mb_strtolower(trim($h)), $fields);
                continue;
            }

            if (count($fields) !== count($headers)) {
                // Ajustar tamaño si hay diferencia
                $fields = array_pad($fields, count($headers), '');
                $fields = array_slice($fields, 0, count($headers));
            }

            $rows[] = array_combine($headers, $fields);
        }

        return $rows;
    }

    /**
     * Mapear columnas CSV a campos internos.
     */
    private function mapColumns(array $row, array $columnMap): array
    {
        $mapped = [];
        foreach ($columnMap as $field => $aliases) {
            $mapped[$field] = '';
            foreach ($aliases as $alias) {
                if (isset($row[$alias]) && trim($row[$alias]) !== '') {
                    $mapped[$field] = trim($row[$alias]);
                    break;
                }
            }
        }
        return $mapped;
    }

    /**
     * Parsear fecha en múltiples formatos.
     */
    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if (empty($value)) return null;

        // Intentar formatos comunes
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd.m.Y'];
        foreach ($formats as $fmt) {
            $date = \DateTime::createFromFormat($fmt, $value);
            if ($date && $date->format($fmt) === $value) {
                return $date->format('Y-m-d');
            }
        }

        // Intentar con strtotime
        $ts = strtotime($value);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    /**
     * Normalizar valor de sexo.
     */
    private function parseSexo(string $value): ?string
    {
        $value = mb_strtolower(trim($value));
        if (in_array($value, ['m', 'masculino', 'hombre', 'male'])) return 'M';
        if (in_array($value, ['f', 'femenino', 'mujer', 'female'])) return 'F';
        if (!empty($value)) return 'Otro';
        return null;
    }

    /**
     * Descargar plantilla CSV de ejemplo.
     */
    public function plantilla(string $tipo)
    {
        if ($tipo !== 'usuarios') {
            abort(404);
        }

        $headers = [
            'RUT', 'Nombre', 'Apellido Paterno', 'Apellido Materno', 'Email',
            'Cargo', 'Departamento', 'Centro de Costo', 'Tipo Nomina',
            'Razon Social', 'Fecha Nacimiento', 'Nacionalidad', 'Sexo',
            'Estado Civil', 'Fecha Ingreso', 'Telefono'
        ];

        $example = [
            '12.345.678-9', 'Juan', 'Pérez', 'González', 'juan.perez@empresa.cl',
            'Operador', 'Producción', 'CC-001', 'NORMAL',
            'Mi Empresa S.A.', '1990-01-15', 'Chilena', 'M',
            'Soltero/a', '2024-03-01', '+56912345678'
        ];

        $csv = implode(';', $headers) . "\n" . implode(';', $example) . "\n";

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="plantilla_usuarios.csv"');
    }
}
