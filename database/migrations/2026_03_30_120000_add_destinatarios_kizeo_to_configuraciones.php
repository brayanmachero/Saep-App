<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar destinatarios por formulario Kizeo
        $rows = [
            [
                'clave'       => 'kizeo_vehiculos_destinatarios',
                'valor'       => 'brayan@bmachero.com',
                'tipo'        => 'TEXT',
                'categoria'   => 'notificaciones',
                'descripcion' => 'Destinatarios del Acta de Vehículos (separar con coma)',
                'editable'    => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'clave'       => 'kizeo_vehiculos_activo',
                'valor'       => '1',
                'tipo'        => 'BOOLEAN',
                'categoria'   => 'notificaciones',
                'descripcion' => 'Enviar email al generar Acta de Vehículos',
                'editable'    => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($rows as $row) {
            DB::table('configuraciones')->updateOrInsert(
                ['clave' => $row['clave']],
                $row
            );
        }
    }

    public function down(): void
    {
        DB::table('configuraciones')->whereIn('clave', [
            'kizeo_vehiculos_destinatarios',
            'kizeo_vehiculos_activo',
        ])->delete();
    }
};
