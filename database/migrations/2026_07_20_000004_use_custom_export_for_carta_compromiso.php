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
            ->where('form_id', '1179842')
            ->where('enabled', true)
            ->update([
                'export_id' => '1772192',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('kizeo_automation_rules')) {
            return;
        }

        DB::table('kizeo_automation_rules')
            ->where('form_id', '1179842')
            ->where('export_id', '1772192')
            ->update([
                'export_id' => null,
                'updated_at' => now(),
            ]);
    }
};
