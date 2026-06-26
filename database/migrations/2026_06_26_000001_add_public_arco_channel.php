<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->makeUserNullable();
        $this->extendTipoEnum();

        Schema::table('solicitudes_arco', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_arco', 'canal_origen')) {
                $table->string('canal_origen', 30)->default('interno')->after('user_id');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'titular_nombre')) {
                $table->string('titular_nombre')->nullable()->after('canal_origen');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'titular_email')) {
                $table->string('titular_email')->nullable()->after('titular_nombre');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'titular_rut')) {
                $table->string('titular_rut', 30)->nullable()->after('titular_email');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'titular_telefono')) {
                $table->string('titular_telefono', 50)->nullable()->after('titular_rut');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'titular_contexto')) {
                $table->string('titular_contexto', 60)->nullable()->after('titular_telefono');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'token_hash')) {
                $table->string('token_hash', 64)->nullable()->after('titular_contexto')->unique();
            }
            if (!Schema::hasColumn('solicitudes_arco', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable()->after('token_hash');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'consentimiento_version')) {
                $table->string('consentimiento_version', 20)->nullable()->after('token_expires_at');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'consentimiento_texto')) {
                $table->text('consentimiento_texto')->nullable()->after('consentimiento_version');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'consentimiento_aceptado_at')) {
                $table->timestamp('consentimiento_aceptado_at')->nullable()->after('consentimiento_texto');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'consentimiento_ip')) {
                $table->string('consentimiento_ip', 45)->nullable()->after('consentimiento_aceptado_at');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'consentimiento_user_agent')) {
                $table->text('consentimiento_user_agent')->nullable()->after('consentimiento_ip');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'bloqueo_temporal_activo')) {
                $table->boolean('bloqueo_temporal_activo')->default(false)->after('solicita_bloqueo_temporal');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'bloqueo_temporal_at')) {
                $table->timestamp('bloqueo_temporal_at')->nullable()->after('bloqueo_temporal_activo');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'bloqueo_temporal_motivo')) {
                $table->text('bloqueo_temporal_motivo')->nullable()->after('bloqueo_temporal_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_arco', function (Blueprint $table) {
            $columns = [
                'canal_origen',
                'titular_nombre',
                'titular_email',
                'titular_rut',
                'titular_telefono',
                'titular_contexto',
                'token_hash',
                'token_expires_at',
                'consentimiento_version',
                'consentimiento_texto',
                'consentimiento_aceptado_at',
                'consentimiento_ip',
                'consentimiento_user_agent',
                'bloqueo_temporal_activo',
                'bloqueo_temporal_at',
                'bloqueo_temporal_motivo',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('solicitudes_arco', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function makeUserNullable(): void
    {
        if (!Schema::hasColumn('solicitudes_arco', 'user_id')) {
            return;
        }

        try {
            Schema::table('solicitudes_arco', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (Throwable) {
            // The foreign key may already have been changed in another environment.
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE solicitudes_arco MODIFY user_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE solicitudes_arco ALTER COLUMN user_id DROP NOT NULL');
        } else {
            try {
                Schema::table('solicitudes_arco', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable()->change();
                });
            } catch (Throwable) {
                // SQLite test schemas may not support this change. The production path is MySQL.
            }
        }

        try {
            Schema::table('solicitudes_arco', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        } catch (Throwable) {
            // If the FK already exists, keep the current constraint.
        }
    }

    private function extendTipoEnum(): void
    {
        if (!Schema::hasColumn('solicitudes_arco', 'tipo')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE solicitudes_arco MODIFY tipo ENUM('acceso','rectificacion','supresion','oposicion','portabilidad','bloqueo') NOT NULL");
        }
    }
};
