<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rrhh_document_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('rrhh_document_batch_items');
            $table->unsignedInteger('position');
            $table->string('category', 120);
            $table->string('path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('prepared_by');
            $table->string('state')->default('preparado');
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('signature_id')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamps();
            $table->unique(['item_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rrhh_document_deliveries');
    }
};
