<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $existing = DB::table('comercial_parametros')
            ->where('clave', 'SUELDO_MINIMO')
            ->first();

        if (! $existing) {
            DB::table('comercial_parametros')->insert([
                'clave' => 'SUELDO_MINIMO',
                'nombre' => 'Sueldo Mínimo Legal',
                'descripcion' => 'Ingreso mínimo mensual vigente desde 01-01-2026 para trabajadores mayores de 18 y hasta 65 años.',
                'valor' => '539000',
                'tipo' => 'integer',
                'editable' => true,
                'categoria' => 'GOBIERNO',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        if (in_array((string) $existing->valor, ['', '0'], true)) {
            DB::table('comercial_parametros')
                ->where('id', $existing->id)
                ->update([
                    'valor_anterior' => $existing->valor,
                    'valor' => '539000',
                    'descripcion' => 'Ingreso mínimo mensual vigente desde 01-01-2026 para trabajadores mayores de 18 y hasta 65 años.',
                    'version' => ((int) $existing->version) + 1,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('comercial_parametros')
            ->where('clave', 'SUELDO_MINIMO')
            ->where('valor', '539000')
            ->update([
                'valor' => '0',
                'updated_at' => now(),
            ]);
    }
};
