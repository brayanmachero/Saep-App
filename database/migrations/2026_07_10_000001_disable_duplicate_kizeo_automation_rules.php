<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_RULES = [
        [
            'form_id' => '1156826',
            'name' => 'Obs. Conducta CCU',
        ],
        [
            'form_id' => '1183405',
            'name' => 'PDR Obs. Reglas de ORO SAEP',
        ],
    ];

    public function up(): void
    {
        foreach (self::LEGACY_RULES as $rule) {
            DB::table('kizeo_automation_rules')
                ->where('form_id', $rule['form_id'])
                ->where('name', $rule['name'])
                ->whereNull('export_id')
                ->update([
                    'enabled' => false,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        foreach (self::LEGACY_RULES as $rule) {
            DB::table('kizeo_automation_rules')
                ->where('form_id', $rule['form_id'])
                ->where('name', $rule['name'])
                ->whereNull('export_id')
                ->update([
                    'enabled' => true,
                    'updated_at' => now(),
                ]);
        }
    }
};
