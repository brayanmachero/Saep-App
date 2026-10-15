<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_kizeo_centros', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('centro_costo_id');
            $t->string('list_id', 40);
            $t->string('item_id', 100);
            $t->timestamp('verified_at');
            $t->unique(['list_id', 'centro_costo_id'], 'inv_cc_list_center');
            $t->unique(['list_id', 'item_id'], 'inv_cc_list_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_kizeo_centros');
    }
};
