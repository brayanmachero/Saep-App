<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formulario_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formulario_id')->constrained('formularios')->cascadeOnDelete();
            $table->integer('version');
            $table->json('schema_json');
            $table->foreignId('modificado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();
            $table->timestamps();

            $table->unique(['formulario_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formulario_versiones');
    }
};
