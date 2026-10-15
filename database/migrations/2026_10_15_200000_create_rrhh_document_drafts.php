<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rrhh_document_batteries', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 150);
            $t->unsignedInteger('version');
            $t->json('documentos');
            $t->json('reglas');
            $t->unsignedBigInteger('created_by');
            $t->timestamps();
            $t->unique(['nombre', 'version']);
        });
        Schema::create('rrhh_document_batches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('nombre', 150);
            $t->unsignedBigInteger('battery_id');
            $t->json('battery_snapshot');
            $t->json('filtros');
            $t->unsignedBigInteger('created_by');
            $t->string('estado')->default('BORRADOR');
            $t->unsignedInteger('trabajadores');
            $t->unsignedInteger('documentos');
            $t->unsignedInteger('observados');
            $t->timestamps();
        });
        Schema::create('rrhh_document_batch_items', function (Blueprint $t) {
            $t->id();
            $t->uuid('batch_id');
            $t->unsignedBigInteger('contrato_id');
            $t->json('snapshot');
            $t->json('observaciones');
            $t->unique(['batch_id', 'contrato_id']);
            $t->foreign('batch_id')->references('id')->on('rrhh_document_batches');
        });
        DB::table('modulos')->updateOrInsert(['slug' => 'gestion_documental'], ['nombre' => 'Gestión documental masiva', 'descripcion' => 'Preparación y validación de lotes documentales RRHH', 'icono' => 'bi-files', 'grupo' => 'RRHH', 'orden' => 29, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rrhh_document_batch_items');
        Schema::dropIfExists('rrhh_document_batches');
        Schema::dropIfExists('rrhh_document_batteries');
        $id = DB::table('modulos')->where('slug', 'gestion_documental')->value('id');
        DB::table('rol_modulo')->where('modulo_id', $id)->delete();
        DB::table('modulos')->where('id', $id)->delete();
    }
};
