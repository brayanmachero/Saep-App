<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rrhh_document_batteries', function (Blueprint $table) {
            $table->string('kind', 20)->default('independent');
            $table->json('parent_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rrhh_document_batteries', fn (Blueprint $table) => $table->dropColumn(['kind', 'parent_ids']));
    }
};
