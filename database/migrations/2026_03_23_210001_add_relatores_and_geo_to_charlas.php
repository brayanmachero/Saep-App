<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add centro_costo_id to charlas
        Schema::table('charlas', function (Blueprint $table) {
            if (!Schema::hasColumn('charlas', 'centro_costo_id')) {
                $table->foreignId('centro_costo_id')
                      ->nullable()
                      ->constrained('centros_costo')
                      ->nullOnDelete()
                      ->after('archivos_adjuntos');
            }
        });

        // Add geo columns to charla_asistentes
        Schema::table('charla_asistentes', function (Blueprint $table) {
            if (!Schema::hasColumn('charla_asistentes', 'geolatitud')) {
                $table->decimal('geolatitud', 10, 7)->nullable()->after('user_agent');
                $table->decimal('geolongitud', 10, 7)->nullable()->after('geolatitud');
            }
        });

        // Create charla_relatores table
        if (!Schema::hasTable('charla_relatores')) {
            Schema::create('charla_relatores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('charla_id')->constrained('charlas')->cascadeOnDelete();
                $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
                $table->enum('rol_relator', ['RELATOR', 'SUPERVISOR_CPHS', 'INSTRUCTOR'])->default('RELATOR');
                $table->enum('estado', ['PENDIENTE', 'FIRMADO'])->default('PENDIENTE');
                $table->longText('firma_imagen')->nullable();
                $table->timestamp('fecha_firma')->nullable();
                $table->string('ip_address', 50)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('documento_hash', 128)->nullable();
                $table->decimal('geolatitud', 10, 7)->nullable();
                $table->decimal('geolongitud', 10, 7)->nullable();
                $table->timestamp('fecha_asignacion')->useCurrent();
                $table->unique(['charla_id', 'usuario_id'], 'uq_charla_relator');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('charla_relatores');

        Schema::table('charla_asistentes', function (Blueprint $table) {
            $table->dropColumn(['geolatitud', 'geolongitud']);
        });

        Schema::table('charlas', function (Blueprint $table) {
            $table->dropForeign(['centro_costo_id']);
            $table->dropColumn('centro_costo_id');
        });
    }
};
