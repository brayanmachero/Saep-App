<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_catalog_job_role_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_id')->constrained('cargos')->cascadeOnDelete();
            $table->string('cost_center_external_id', 120);
            $table->timestamps();

            $table->unique(['cargo_id', 'cost_center_external_id']);
            $table->index('cost_center_external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_catalog_job_role_centers');
    }
};
