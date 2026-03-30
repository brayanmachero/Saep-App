<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            // SST Notification toggles
            ['sst_notif_activa',            'true',  'BOOLEAN', 'sst', 'Notificaciones SST habilitadas'],
            ['sst_notif_asignacion',        'true',  'BOOLEAN', 'sst', 'Notificar al asignar actividad'],
            ['sst_notif_vencimiento',       'true',  'BOOLEAN', 'sst', 'Notificar actividad próxima a vencer'],
            ['sst_notif_vencida',           'true',  'BOOLEAN', 'sst', 'Notificar actividad vencida'],
            ['sst_notif_recordatorio',      'true',  'BOOLEAN', 'sst', 'Enviar recordatorios por periodicidad'],
            ['sst_notif_seguimiento',       'true',  'BOOLEAN', 'sst', 'Notificar seguimiento pendiente del mes anterior'],
            ['sst_notif_cc_adicional',      '',      'TEXT',    'sst', 'Emails CC adicionales para alertas SST (separar con ;)'],
            ['sst_notif_dias_antes_vencer', '7',     'NUMBER',  'sst', 'Días antes de vencimiento para alertar'],
            ['sst_notif_frecuencia_vencida','3',     'NUMBER',  'sst', 'Cada cuántos días repetir alerta de actividad vencida'],
            ['sst_notif_max_dias_vencida',  '30',    'NUMBER',  'sst', 'Máximo días después de vencida para seguir alertando'],
        ];

        foreach ($configs as $c) {
            DB::table('configuraciones')->updateOrInsert(
                ['clave' => $c[0]],
                [
                    'valor'       => $c[1],
                    'tipo'        => $c[2],
                    'categoria'   => $c[3],
                    'descripcion' => $c[4],
                    'editable'    => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('configuraciones')->whereIn('clave', [
            'sst_notif_activa', 'sst_notif_asignacion', 'sst_notif_vencimiento',
            'sst_notif_vencida', 'sst_notif_recordatorio', 'sst_notif_seguimiento',
            'sst_notif_cc_adicional', 'sst_notif_dias_antes_vencer',
            'sst_notif_frecuencia_vencida', 'sst_notif_max_dias_vencida',
        ])->delete();
    }
};
