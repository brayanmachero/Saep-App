<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kizeo_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140);
            $table->string('form_id', 80)->index();
            $table->string('form_name', 180)->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedSmallInteger('priority')->default(100)->index();
            $table->json('conditions')->nullable();
            $table->string('sharepoint_site', 120)->nullable();
            $table->string('sharepoint_folder', 500)->nullable();
            $table->string('folder_template', 500)->default('{anio}/{mes} - {mes_nombre}');
            $table->string('filename_template', 300)->default('{fecha} - {form_name} - {data_id}.pdf');
            $table->string('export_id', 80)->nullable();
            $table->boolean('continue_legacy')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'enabled', 'priority']);
        });

        Schema::create('kizeo_automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kizeo_automation_rule_id')
                ->nullable()
                ->constrained('kizeo_automation_rules')
                ->nullOnDelete();
            $table->string('form_id', 80)->index();
            $table->string('data_id', 80)->index();
            $table->enum('status', ['processing', 'success', 'error', 'ignored'])->default('processing')->index();
            $table->string('filename', 500)->nullable();
            $table->string('sharepoint_path', 700)->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'data_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kizeo_automation_runs');
        Schema::dropIfExists('kizeo_automation_rules');
    }
};
