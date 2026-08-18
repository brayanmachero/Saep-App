<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventario_kizeo_catalog_items')) {
            Schema::create('inventario_kizeo_catalog_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('variante_id')->constrained('inventario_variantes')->cascadeOnDelete();
                $table->string('kizeo_list_id', 40);
                $table->string('kizeo_item_id', 100)->nullable();
                $table->string('source_hash', 64)->nullable();
                $table->timestamp('sincronizado_en')->nullable();
                $table->text('ultimo_error')->nullable();
                $table->timestamps();

                // MySQL limita los nombres de índices a 64 caracteres.
                $table->unique(['variante_id', 'kizeo_list_id'], 'inv_kizeo_catalog_variant_list_uq');
                $table->unique(['kizeo_list_id', 'kizeo_item_id'], 'inv_kizeo_catalog_list_item_uq');
            });

            return;
        }

        // Una primera ejecución antigua pudo crear la tabla antes de fallar al
        // nombrar el segundo índice. Completarla permite reintentar migrate sin
        // eliminar ni reconstruir nada de producción.
        if (! $this->hasUniqueIndex(['kizeo_list_id', 'kizeo_item_id'])) {
            Schema::table('inventario_kizeo_catalog_items', function (Blueprint $table) {
                $table->unique(['kizeo_list_id', 'kizeo_item_id'], 'inv_kizeo_catalog_list_item_uq');
            });
        }
    }

    /** @param array<int, string> $columns */
    private function hasUniqueIndex(array $columns): bool
    {
        $indexes = collect(DB::select('SHOW INDEX FROM inventario_kizeo_catalog_items'))
            ->filter(fn ($index) => (int) $index->Non_unique === 0 && $index->Key_name !== 'PRIMARY')
            ->groupBy(fn ($index) => (string) $index->Key_name);

        return $indexes->contains(function ($index) use ($columns) {
            $indexedColumns = $index
                ->sortBy(fn ($item) => (int) $item->Seq_in_index)
                ->pluck('Column_name')
                ->values()
                ->all();

            return $indexedColumns === $columns;
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_kizeo_catalog_items');
    }
};
