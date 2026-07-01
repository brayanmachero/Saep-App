<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RULES = [
        [
            'name' => 'PDR Observacion de conducta',
            'form_id' => '973786',
            'form_name' => 'PDR Observación de conducta',
            'sharepoint_folder' => 'Observaciones Conducta',
            'folder_template' => '{anio}/{mes} - {mes_nombre}/{centro_de_distribucion}',
            'filename_template' => '{fecha} - {nombre_del_observador} ({negativa_1}).pdf',
            'export_id' => '1438367',
        ],
        [
            'name' => 'Observacion de conducta CCU',
            'form_id' => '1156826',
            'form_name' => 'Obs. Conducta CCU',
            'sharepoint_folder' => 'Observaciones Conducta CCU',
            'folder_template' => '{anio}/{mes} - {mes_nombre}/{centro_de_distribucion}',
            'filename_template' => '{fecha} - {nombre_del_observador} ({negativa_1}).pdf',
            'export_id' => '1735214',
        ],
        [
            'name' => 'Observacion preventiva regla de oro',
            'form_id' => '1183405',
            'form_name' => 'PDR Obs. Reglas de ORO SAEP',
            'sharepoint_folder' => 'Observación preventiva regla de oro',
            'folder_template' => '{anio}/{mes} - {mes_nombre}/{centro_de_distribucion}',
            'filename_template' => '{fecha} - {nombre_del_observador} (Reglas de Oro).pdf',
            'export_id' => '1777456',
        ],
        [
            'name' => 'Retroalimentacion Colaboradores',
            'form_id' => '1179881',
            'form_name' => 'Retroalimentación Colaboradores',
            'sharepoint_folder' => 'Retroalimentación Colaboradores',
            'folder_template' => '{anio}/{mes} - {mes_nombre}/{centro_de_distribucion}',
            'filename_template' => '{fecha} - {nombre_del_observador} ({motivo_retroalimentacion}).pdf',
            'export_id' => '1772235',
        ],
    ];

    public function up(): void
    {
        foreach (self::RULES as $index => $rule) {
            DB::table('kizeo_automation_rules')->updateOrInsert(
                ['form_id' => $rule['form_id'], 'name' => $rule['name']],
                [
                    'form_name' => $rule['form_name'],
                    'enabled' => true,
                    'priority' => 10 + $index,
                    'conditions' => null,
                    'sharepoint_site' => null,
                    'sharepoint_folder' => $rule['sharepoint_folder'],
                    'folder_template' => $rule['folder_template'],
                    'filename_template' => $rule['filename_template'],
                    'export_id' => $rule['export_id'],
                    'continue_legacy' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('kizeo_automation_rules')
            ->whereIn('form_id', collect(self::RULES)->pluck('form_id')->all())
            ->whereIn('name', collect(self::RULES)->pluck('name')->all())
            ->delete();
    }
};
