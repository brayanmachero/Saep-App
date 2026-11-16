<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rrhh_document_batteries', function (Blueprint $table) {
            $table->string('estado')->default('BORRADOR');
            $table->json('document_details')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamp('activated_at')->nullable();
        });
        Schema::create('rrhh_document_battery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battery_id')->constrained('rrhh_document_batteries');
            $table->unsignedBigInteger('actor_id');
            $table->string('action');
            $table->json('detail');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rrhh_document_battery_events');
        Schema::table('rrhh_document_batteries', function (Blueprint $table) {
            $table->dropColumn(['estado','document_details','revision','activated_by','activated_at']);
        });
    }
};
