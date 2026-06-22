<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comercial_cotizaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('comercial_cotizaciones', 'pdf_final_path')) {
                $table->string('pdf_final_path')->nullable()->after('fecha_cancelacion');
            }

            if (! Schema::hasColumn('comercial_cotizaciones', 'pdf_final_hash')) {
                $table->string('pdf_final_hash', 64)->nullable()->after('pdf_final_path');
            }

            if (! Schema::hasColumn('comercial_cotizaciones', 'pdf_final_generado_at')) {
                $table->timestamp('pdf_final_generado_at')->nullable()->after('pdf_final_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comercial_cotizaciones', function (Blueprint $table) {
            $columnas = array_filter([
                Schema::hasColumn('comercial_cotizaciones', 'pdf_final_generado_at') ? 'pdf_final_generado_at' : null,
                Schema::hasColumn('comercial_cotizaciones', 'pdf_final_hash') ? 'pdf_final_hash' : null,
                Schema::hasColumn('comercial_cotizaciones', 'pdf_final_path') ? 'pdf_final_path' : null,
            ]);

            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });
    }
};
