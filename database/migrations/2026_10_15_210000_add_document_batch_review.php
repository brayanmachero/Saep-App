<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rrhh_document_batch_items', function (Blueprint $t) {
            $t->boolean('excluido')->default(false);
        });
        Schema::create('rrhh_document_batch_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('batch_id');
            $t->unsignedBigInteger('actor_id');
            $t->string('accion');
            $t->json('detalle');
            $t->timestamp('created_at');
            $t->foreign('batch_id')->references('id')->on('rrhh_document_batches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rrhh_document_batch_events');
        Schema::table('rrhh_document_batch_items', fn (Blueprint $t) => $t->dropColumn('excluido'));
    }
};
