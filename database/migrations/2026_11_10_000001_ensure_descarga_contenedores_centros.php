<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Asegura que los centros de Contenedores existan en centros_costo.
 *
 * Solo crea los que faltan: si ya hay un centro con el mismo nombre (sin
 * importar tildes ni mayúsculas), con o sin el sufijo de modalidad SUB/EST,
 * se respeta el existente y no se modifica. No elimina ni desactiva nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existentes = DB::table('centros_costo')->get(['id', 'codigo', 'nombre']);
        $nombres = $existentes->map(fn ($c) => $this->normalizar($c->nombre))->all();
        $codigos = $existentes->pluck('codigo')->map(fn ($c) => strtoupper((string) $c))->all();

        foreach ((array) config('descarga_contenedores.centros_requeridos', []) as $centro) {
            $nombre = trim($centro['nombre']);
            $normalizado = $this->normalizar($nombre);
            $modalidad = str_ends_with($normalizado, ' EST') ? 'EST' : 'SUB';
            $base = trim(preg_replace('/\s+(SUB|EST)$/', '', $normalizado));

            if (in_array($normalizado, $nombres, true) || in_array($base, $nombres, true)) {
                continue;
            }

            $codigo = strtoupper(trim($centro['codigo']));
            if (in_array($codigo, $codigos, true)) {
                $codigo = 'CONT-' . $codigo;
            }
            if (in_array($codigo, $codigos, true)) {
                continue;
            }

            DB::table('centros_costo')->insert([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'razon_social' => $modalidad === 'EST' ? 'TRANSITORIO' : 'NORMAL',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $nombres[] = $normalizado;
            $codigos[] = $codigo;
        }
    }

    public function down(): void
    {
        // Los centros pueden quedar referenciados por registros operativos: no se eliminan.
    }

    private function normalizar(?string $nombre): string
    {
        return Str::of((string) $nombre)->ascii()->upper()->squish()->toString();
    }
};
