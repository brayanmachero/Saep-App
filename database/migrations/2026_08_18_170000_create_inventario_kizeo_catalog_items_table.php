<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_kizeo_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('inventario_variantes')->cascadeOnDelete();
            $table->string('kizeo_list_id', 40);
            $table->string('kizeo_item_id', 100)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->timestamp('sincronizado_en')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();

            $table->unique(['variante_id', 'kizeo_list_id']);
            $table->unique(['kizeo_list_id', 'kizeo_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_kizeo_catalog_items');
    }
};
