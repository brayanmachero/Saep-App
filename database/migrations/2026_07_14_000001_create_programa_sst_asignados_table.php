<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programa_sst_asignados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_sst_id')->constrained('programas_sst')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['programa_sst_id', 'user_id']);
            $table->index('user_id');
        });

        DB::table('programas_sst')
            ->whereNotNull('responsable_id')
            ->select(['id', 'responsable_id'])
            ->orderBy('id')
            ->chunk(200, function ($programas) {
                $now = now();
                $rows = $programas->map(fn ($programa) => [
                    'programa_sst_id' => $programa->id,
                    'user_id' => $programa->responsable_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('programa_sst_asignados')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_sst_asignados');
    }
};
