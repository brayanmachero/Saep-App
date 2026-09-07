<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FORM_ID = '1200400';
    private const RULE_NAME = 'PDR Investigación de Eventos SST';

    public function up(): void
    {
        if (! Schema::hasTable('kizeo_automation_rules')) {
            return;
        }

        DB::table('kizeo_automation_rules')->updateOrInsert(
            [
                'form_id' => self::FORM_ID,
                'name' => self::RULE_NAME,
            ],
            [
                'form_name' => 'PDR Inv. de Eventos SST',
                'enabled' => true,
                'priority' => 20,
                'conditions' => null,
                'sharepoint_site' => null,
                'sharepoint_folder' => 'Investigaciones de Eventos SST',
                'folder_template' => '{anio}/{mes} - {mes_nombre}/{cd}',
                // Se incorpora el registro Kizeo para que dos eventos del
                // mismo trabajador, tipo y fecha nunca se sobrescriban.
                'filename_template' => '{nombre_del_lesionado} - {tipo_de_incidente} - {fecha} - Registro {record_number}.pdf',
                'export_id' => '1809065',
                'continue_legacy' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('kizeo_automation_rules')) {
            return;
        }

        DB::table('kizeo_automation_rules')
            ->where('form_id', self::FORM_ID)
            ->where('name', self::RULE_NAME)
            ->delete();
    }
};
