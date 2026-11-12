<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('solicitudes_epp', function (Blueprint $t) {
            $t->id();
            $t->uuid('folio')->unique();
            $t->string('email')->index();
            $t->string('nombre');
            $t->foreignId('centro_id')->constrained('inventario_centros_costo')->restrictOnDelete();
            $t->string('centro');
            $t->string('mes', 7);
            $t->json('articulos');
            $t->text('insumos')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_epp');
    }
};
