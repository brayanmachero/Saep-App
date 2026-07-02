<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('talana_trabajadores')) {
            Schema::create('talana_trabajadores', function (Blueprint $table) {
                $table->id();
                $table->string('talana_id', 100)->nullable()->index();
                $table->string('rut', 30)->nullable()->index();
                $table->string('nombre', 200);
                $table->string('apellido_paterno', 120)->nullable();
                $table->string('apellido_materno', 120)->nullable();
                $table->string('email', 200)->nullable()->index();
                $table->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
                $table->string('cargo_nombre', 200)->nullable();
                $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
                $table->string('departamento_nombre', 180)->nullable();
                $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->nullOnDelete();
                $table->string('centro_costo_nombre', 180)->nullable();
                $table->string('tipo_nomina', 40)->nullable();
                $table->string('razon_social', 200)->nullable();
                $table->date('fecha_nacimiento')->nullable();
                $table->date('fecha_ingreso')->nullable();
                $table->date('fecha_termino')->nullable();
                $table->string('telefono', 80)->nullable();
                $table->boolean('activo')->default(true)->index();
                $table->string('origen', 60)->default('talana_csv');
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->index(['activo', 'centro_costo_id'], 'talana_trabajadores_activo_centro_idx');
                $table->unique(['rut', 'email'], 'talana_trabajadores_rut_email_unique');
            });
        }

        if (Schema::hasTable('descarga_contenedor_participantes')
            && !Schema::hasColumn('descarga_contenedor_participantes', 'talana_trabajador_id')) {
            Schema::table('descarga_contenedor_participantes', function (Blueprint $table) {
                $table->foreignId('talana_trabajador_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('talana_trabajadores')
                    ->nullOnDelete();
            });
        }

        $this->backfillParticipantesFromUsers();
    }

    public function down(): void
    {
        if (Schema::hasTable('descarga_contenedor_participantes')
            && Schema::hasColumn('descarga_contenedor_participantes', 'talana_trabajador_id')) {
            Schema::table('descarga_contenedor_participantes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('talana_trabajador_id');
            });
        }

        Schema::dropIfExists('talana_trabajadores');
    }

    private function backfillParticipantesFromUsers(): void
    {
        if (!Schema::hasTable('descarga_contenedor_participantes')
            || !Schema::hasColumn('descarga_contenedor_participantes', 'talana_trabajador_id')
            || !Schema::hasTable('users')
            || !Schema::hasTable('talana_trabajadores')) {
            return;
        }

        $talanaPorRut = DB::table('talana_trabajadores')
            ->whereNotNull('rut')
            ->pluck('id', 'rut');
        $talanaPorEmail = DB::table('talana_trabajadores')
            ->whereNotNull('email')
            ->pluck('id', 'email');

        DB::table('descarga_contenedor_participantes')
            ->whereNotNull('user_id')
            ->whereNull('talana_trabajador_id')
            ->orderBy('id')
            ->chunkById(500, function ($participantes) use ($talanaPorRut, $talanaPorEmail) {
                $userIds = $participantes->pluck('user_id')->filter()->unique()->values();
                if ($userIds->isEmpty()) {
                    return;
                }

                $users = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->get(['id', 'rut', 'email'])
                    ->keyBy('id');

                foreach ($participantes as $participante) {
                    $user = $users->get($participante->user_id);
                    if (!$user) {
                        continue;
                    }

                    $rut = $user->rut ? strtoupper(preg_replace('/[^0-9kK]/', '', $user->rut)) : null;
                    $talanaId = $rut ? ($talanaPorRut[$rut] ?? null) : null;
                    $talanaId = $talanaId ?: ($user->email ? ($talanaPorEmail[$user->email] ?? null) : null);

                    if ($talanaId) {
                        DB::table('descarga_contenedor_participantes')
                            ->where('id', $participante->id)
                            ->update(['talana_trabajador_id' => $talanaId]);
                    }
                }
            });
    }
};
