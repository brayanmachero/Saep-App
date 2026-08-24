<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'inventario_resumen_trazabilidad_desde'],
            [
                'valor' => $now->toDateString(),
                'tipo' => 'DATE',
                'categoria' => 'inventario',
                'descripcion' => 'Fecha de inicio para los gráficos operativos del resumen de inventario. El saldo conserva todo el kardex, pero el tablero no mezcla la carga histórica anterior.',
                'editable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('configuraciones')
            ->where('clave', 'inventario_resumen_trazabilidad_desde')
            ->delete();
    }
};
