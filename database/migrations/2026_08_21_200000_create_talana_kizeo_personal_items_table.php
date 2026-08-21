<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talana_kizeo_personal_items')) {
            return;
        }

        Schema::create('talana_kizeo_personal_items', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 20);
            $table->string('kizeo_list_id', 40);
            $table->string('kizeo_item_id', 100)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->timestamp('sincronizado_en')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();

            $table->unique(['rut', 'kizeo_list_id'], 'talana_kizeo_personal_rut_list_uq');
            $table->unique(['kizeo_list_id', 'kizeo_item_id'], 'talana_kizeo_personal_list_item_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talana_kizeo_personal_items');
    }
};
