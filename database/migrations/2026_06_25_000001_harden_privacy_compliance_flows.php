<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_arco', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_arco', 'causal_invocada')) {
                $table->string('causal_invocada', 200)->nullable()->after('datos_afectados');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'antecedentes')) {
                $table->text('antecedentes')->nullable()->after('causal_invocada');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'solicita_bloqueo_temporal')) {
                $table->boolean('solicita_bloqueo_temporal')->default(false)->after('antecedentes');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'estado_ejecucion')) {
                $table->string('estado_ejecucion', 40)->nullable()->after('motivo_rechazo');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'resultado_ejecucion')) {
                $table->json('resultado_ejecucion')->nullable()->after('estado_ejecucion');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'observacion_ejecucion')) {
                $table->text('observacion_ejecucion')->nullable()->after('resultado_ejecucion');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'fecha_ejecucion')) {
                $table->timestamp('fecha_ejecucion')->nullable()->after('observacion_ejecucion');
            }
            if (!Schema::hasColumn('solicitudes_arco', 'ejecutada_por')) {
                $table->foreignId('ejecutada_por')->nullable()->after('fecha_ejecucion')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            if (!Schema::hasColumn('postulantes_contratacion', 'consentimiento_datos')) {
                $table->boolean('consentimiento_datos')->default(false)->after('observaciones');
            }
            if (!Schema::hasColumn('postulantes_contratacion', 'consentimiento_version')) {
                $table->string('consentimiento_version', 20)->nullable()->after('consentimiento_datos');
            }
            if (!Schema::hasColumn('postulantes_contratacion', 'consentimiento_texto')) {
                $table->text('consentimiento_texto')->nullable()->after('consentimiento_version');
            }
            if (!Schema::hasColumn('postulantes_contratacion', 'consentimiento_aceptado_at')) {
                $table->timestamp('consentimiento_aceptado_at')->nullable()->after('consentimiento_texto');
            }
            if (!Schema::hasColumn('postulantes_contratacion', 'consentimiento_ip')) {
                $table->string('consentimiento_ip', 45)->nullable()->after('consentimiento_aceptado_at');
            }
            if (!Schema::hasColumn('postulantes_contratacion', 'consentimiento_user_agent')) {
                $table->text('consentimiento_user_agent')->nullable()->after('consentimiento_ip');
            }
            if (!Schema::hasColumn('postulantes_contratacion', 'anonimizado_at')) {
                $table->timestamp('anonimizado_at')->nullable()->after('consentimiento_user_agent');
            }
            if (!Schema::hasColumn('postulantes_contratacion', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('ley_karin', function (Blueprint $table) {
            if (!Schema::hasColumn('ley_karin', 'consentimiento_version')) {
                $table->string('consentimiento_version', 20)->nullable()->after('consentimiento_geolocalizacion');
            }
            if (!Schema::hasColumn('ley_karin', 'consentimiento_texto')) {
                $table->text('consentimiento_texto')->nullable()->after('consentimiento_version');
            }
            if (!Schema::hasColumn('ley_karin', 'consentimiento_aceptado_at')) {
                $table->timestamp('consentimiento_aceptado_at')->nullable()->after('consentimiento_texto');
            }
            if (!Schema::hasColumn('ley_karin', 'consentimiento_ip')) {
                $table->string('consentimiento_ip', 45)->nullable()->after('consentimiento_aceptado_at');
            }
            if (!Schema::hasColumn('ley_karin', 'consentimiento_user_agent')) {
                $table->text('consentimiento_user_agent')->nullable()->after('consentimiento_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ley_karin', function (Blueprint $table) {
            $columns = [
                'consentimiento_version',
                'consentimiento_texto',
                'consentimiento_aceptado_at',
                'consentimiento_ip',
                'consentimiento_user_agent',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('ley_karin', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            $columns = [
                'consentimiento_datos',
                'consentimiento_version',
                'consentimiento_texto',
                'consentimiento_aceptado_at',
                'consentimiento_ip',
                'consentimiento_user_agent',
                'anonimizado_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('postulantes_contratacion', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('postulantes_contratacion', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('solicitudes_arco', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudes_arco', 'ejecutada_por')) {
                $table->dropConstrainedForeignId('ejecutada_por');
            }

            $columns = [
                'causal_invocada',
                'antecedentes',
                'solicita_bloqueo_temporal',
                'estado_ejecucion',
                'resultado_ejecucion',
                'observacion_ejecucion',
                'fecha_ejecucion',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('solicitudes_arco', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
