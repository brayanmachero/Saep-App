<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            [
                'clave'       => 'charla_report_activo',
                'valor'       => '1',
                'tipo'        => 'BOOLEAN',
                'categoria'   => 'notificaciones',
                'descripcion' => 'Enviar reporte semanal de Charlas SST',
                'editable'    => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'clave'       => 'charla_report_destinatarios',
                'valor'       => '',
                'tipo'        => 'TEXT',
                'categoria'   => 'notificaciones',
                'descripcion' => 'Destinatarios del reporte semanal de Charlas SST (separar con coma)',
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
            'charla_report_activo',
            'charla_report_destinatarios',
        ])->delete();
    }
};
