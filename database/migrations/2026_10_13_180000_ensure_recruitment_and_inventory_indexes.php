<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function definitions(): array
    {
        return [
            ['recruitment_catalog_job_role_centers', ['cargo_id', 'cost_center_external_id'], true, 'recruitment_role_center_unique_repair'],
            ['recruitment_catalog_job_role_centers', ['cost_center_external_id'], false, 'recruitment_cost_center_index_repair'],
            ['inventario_kizeo_imputacion_historial', ['aplicacion_id', 'created_at'], false, 'inv_kiz_imp_app_created_index_repair'],
        ];
    }

    public function up(): void
    {
        foreach ($this->definitions() as [$tableName, $columns, $unique, $name]) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            foreach (Schema::getIndexes($tableName) as $index) {
                if ($index['columns'] === $columns && (! $unique || $index['unique'])) {
                    continue 2;
                }
            }
            // MySQL rejects duplicate data rather than silently removing rows.
            Schema::table($tableName, function (Blueprint $table) use ($columns, $unique, $name) {
                if ($unique) {
                    $table->unique($columns, $name);
                } else {
                    $table->index($columns, $name);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->definitions()) as [$tableName, $columns, $unique, $name]) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            foreach (Schema::getIndexes($tableName) as $index) {
                if ($index['name'] === $name) {
                    Schema::table($tableName, function (Blueprint $table) use ($unique, $name) {
                        if ($unique) {
                            $table->dropUnique($name);
                        } else {
                            $table->dropIndex($name);
                        }
                    });
                    break;
                }
            }
        }
    }
};
