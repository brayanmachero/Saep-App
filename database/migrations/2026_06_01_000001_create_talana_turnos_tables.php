<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas para turnos rotativos y jornada calculada de Talana.
 *
 * talana_work_shift_person_ranges  — Asignación persona ↔ turno (workShiftPersonRange/)
 * talana_assignation_summaries     — Jornada calculada por día (assignationSummaryApi/)
 *
 * El campo workingDay de talana_assignation_summaries permite distinguir días
 * de descanso de ausencias reales en el reporte de asistencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Asignación persona ↔ turno ──────────────────────────────────────
        Schema::create('talana_work_shift_person_ranges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('talana_id')->unique()->comment('ID del registro en Talana');
            $table->unsignedBigInteger('persona_talana_id')->comment('person.id de Talana');
            $table->unsignedInteger('work_shift_id')->comment('workShift.id de Talana');
            $table->date('from_date')->comment('Inicio de la asignación');
            $table->date('to_date')->nullable()->comment('Fin de la asignación (NULL = indefinido)');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('persona_talana_id');
            $table->index(['persona_talana_id', 'from_date'], 'twspr_persona_from_idx');
        });

        // ── Jornada calculada por persona y día ────────────────────────────
        Schema::create('talana_assignation_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('talana_id')->unique()->comment('ID del registro en Talana');
            $table->unsignedBigInteger('persona_talana_id')->comment('person.id de Talana');
            $table->string('persona_rut', 30)->nullable();
            $table->string('persona_nombre', 200)->nullable();
            $table->date('fecha')->comment('Fecha de la jornada');
            $table->boolean('working_day')->default(true)->comment('¿Es día laborable según turno?');
            $table->string('absence_type', 60)->nullable()->comment('sin_registro, presente, etc.');
            $table->string('status', 60)->nullable()->comment('anomalia, presente, etc.');
            $table->dateTime('entrance_datetime')->nullable();
            $table->dateTime('exit_datetime')->nullable();
            $table->unsignedInteger('working_seconds')->nullable();
            $table->unsignedInteger('delay_seconds')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // Una sola jornada por persona por día
            $table->unique(['persona_talana_id', 'fecha']);
            $table->index('fecha');
            $table->index(['persona_talana_id', 'working_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talana_assignation_summaries');
        Schema::dropIfExists('talana_work_shift_person_ranges');
    }
};
