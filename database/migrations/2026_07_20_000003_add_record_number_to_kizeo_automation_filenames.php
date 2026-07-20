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

        $templates = [
            [
                'form_id' => '973786',
                'export_id' => '1438367',
                'filename_template' => '{fecha} - {nombre_del_observador} ({negativa_1}) - Registro {record_number}.pdf',
            ],
            [
                'form_id' => '1156826',
                'export_id' => '1735214',
                'filename_template' => '{fecha} - {nombre_del_observador} ({negativa_1}) - Registro {record_number}.pdf',
            ],
            [
                'form_id' => '1179842',
                'export_id' => null,
                'filename_template' => '{fecha} - {nombre_trabajador_observado} ({negativa_1}) - Registro {record_number}.pdf',
            ],
            [
                'form_id' => '1179881',
                'export_id' => '1772235',
                'filename_template' => '{fecha} - {nombre_del_observador} ({motivo_retroalimentacion}) - Registro {record_number}.pdf',
            ],
        ];

        foreach ($templates as $template) {
            $query = DB::table('kizeo_automation_rules')
                ->where('form_id', $template['form_id'])
                ->where('enabled', true);

            $template['export_id'] === null
                ? $query->whereNull('export_id')
                : $query->where('export_id', $template['export_id']);

            $query->update([
                'filename_template' => $template['filename_template'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('kizeo_automation_rules')) {
            return;
        }

        $templates = [
            [
                'form_id' => '973786',
                'export_id' => '1438367',
                'filename_template' => '{fecha} - {nombre_del_observador} ({negativa_1}).pdf',
            ],
            [
                'form_id' => '1156826',
                'export_id' => '1735214',
                'filename_template' => '{fecha} - {nombre_del_observador} ({negativa_1}).pdf',
            ],
            [
                'form_id' => '1179842',
                'export_id' => null,
                'filename_template' => '{fecha} - {nombre_trabajador_observado} ({negativa_1}).pdf',
            ],
            [
                'form_id' => '1179881',
                'export_id' => '1772235',
                'filename_template' => '{fecha} - {nombre_del_observador} ({motivo_retroalimentacion}).pdf',
            ],
        ];

        foreach ($templates as $template) {
            $query = DB::table('kizeo_automation_rules')
                ->where('form_id', $template['form_id'])
                ->where('enabled', true);

            $template['export_id'] === null
                ? $query->whereNull('export_id')
                : $query->where('export_id', $template['export_id']);

            $query->update([
                'filename_template' => $template['filename_template'],
                'updated_at' => now(),
            ]);
        }
    }
};
