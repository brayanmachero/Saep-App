<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cambiar enums a string para flexibilidad (MariaDB enum es case-sensitive)
        DB::statement("ALTER TABLE ley_karin MODIFY COLUMN tipo VARCHAR(50) NOT NULL DEFAULT 'ACOSO_LABORAL'");
        DB::statement("ALTER TABLE ley_karin MODIFY COLUMN estado VARCHAR(30) NOT NULL DEFAULT 'RECIBIDA'");

        Schema::table('ley_karin', function (Blueprint $table) {
            $table->string('canal', 50)->nullable()->after('centro_costo_id');
            $table->boolean('confidencial')->default(true)->after('anonima');
            $table->date('fecha_plazo_investigacion')->nullable()->after('fecha_resolucion');
            $table->text('medidas_adoptadas')->nullable()->after('resultado_investigacion');
        });
    }

    public function down(): void
    {
        Schema::table('ley_karin', function (Blueprint $table) {
            $table->dropColumn(['canal', 'confidencial', 'fecha_plazo_investigacion', 'medidas_adoptadas']);
        });

        DB::statement("ALTER TABLE ley_karin MODIFY COLUMN tipo ENUM('ACOSO_LABORAL','ACOSO_SEXUAL','VIOLENCIA_EN_TRABAJO') NOT NULL DEFAULT 'ACOSO_LABORAL'");
        DB::statement("ALTER TABLE ley_karin MODIFY COLUMN estado ENUM('RECIBIDA','EN_INVESTIGACION','RESUELTA','ARCHIVADA') NOT NULL DEFAULT 'RECIBIDA'");
    }
};
