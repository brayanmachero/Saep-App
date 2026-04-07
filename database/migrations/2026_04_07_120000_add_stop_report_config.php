<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            [
                'clave'       => 'stop_report_activo',
                'valor'       => '0',
                'tipo'        => 'BOOLEAN',
                'categoria'   => 'notificaciones',
                'descripcion' => 'Enviar reporte semanal de Tarjeta STOP',
                'editable'    => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'clave'       => 'stop_report_destinatarios',
                'valor'       => '',
                'tipo'        => 'TEXT',
                'categoria'   => 'notificaciones',
                'descripcion' => 'Destinatarios del reporte semanal de Tarjeta STOP (separar con coma)',
                'editable'    => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($rows as $row) {
            DB::table('configuraciones')->updateOrInsert(
                ['clave' => $row['clave']],
                $row,
            );
        }
    }

    public function down(): void
    {
        DB::table('configuraciones')->whereIn('clave', [
            'stop_report_activo',
            'stop_report_destinatarios',
        ])->delete();
    }
};
