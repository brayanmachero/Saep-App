<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kizeo_automation_rules')) {
            return;
        }

        $now = now();

        DB::table('kizeo_automation_rules')
            ->where('form_id', '1183405')
            ->whereNull('export_id')
            ->update([
                'enabled' => false,
                'updated_at' => $now,
            ]);

        $updated = DB::table('kizeo_automation_rules')
            ->where('form_id', '1183405')
            ->where('export_id', '1777456')
            ->update([
                'name' => 'PDR Obs Reglas de ORO SAEP',
                'form_name' => 'PDR Obs. Reglas de ORO SAEP',
                'enabled' => true,
                'sharepoint_folder' => 'PDR Obs Reglas de ORO SAEP',
                'folder_template' => '{anio}/{mes} - {mes_nombre}/{centro_de_distribucion}',
                'filename_template' => '{fecha} - {nombre_del_observador} (Reglas de Oro).pdf',
                'continue_legacy' => false,
                'updated_at' => $now,
            ]);

        if ($updated === 0) {
            DB::table('kizeo_automation_rules')->updateOrInsert(
                ['form_id' => '1183405', 'name' => 'PDR Obs Reglas de ORO SAEP'],
                [
                    'form_name' => 'PDR Obs. Reglas de ORO SAEP',
                    'enabled' => true,
                    'priority' => 12,
                    'conditions' => null,
                    'sharepoint_site' => null,
                    'sharepoint_folder' => 'PDR Obs Reglas de ORO SAEP',
                    'folder_template' => '{anio}/{mes} - {mes_nombre}/{centro_de_distribucion}',
                    'filename_template' => '{fecha} - {nombre_del_observador} (Reglas de Oro).pdf',
                    'export_id' => '1777456',
                    'continue_legacy' => false,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('kizeo_automation_rules')) {
            return;
        }

        DB::table('kizeo_automation_rules')
            ->where('form_id', '1183405')
            ->where('export_id', '1777456')
            ->update([
                'name' => 'Observacion preventiva regla de oro',
                'form_name' => 'PDR Obs. Reglas de ORO SAEP',
                'sharepoint_folder' => 'Observación preventiva regla de oro',
                'updated_at' => now(),
            ]);
    }
};
