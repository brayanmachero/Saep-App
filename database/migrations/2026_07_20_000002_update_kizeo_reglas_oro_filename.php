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

        DB::table('kizeo_automation_rules')
            ->where('form_id', '1183405')
            ->where('export_id', '1777456')
            ->update([
                'filename_template' => '{fecha} - {nombre_trabajador_observado} - Registro {record_number}.pdf',
                'updated_at' => now(),
            ]);
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
                'filename_template' => '{fecha} - {nombre_del_observador} (Reglas de Oro).pdf',
                'updated_at' => now(),
            ]);
    }
};
