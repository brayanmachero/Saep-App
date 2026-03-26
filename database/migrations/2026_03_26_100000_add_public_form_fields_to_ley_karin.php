<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ley_karin', function (Blueprint $table) {
            $table->string('denunciante_email', 200)->nullable()->after('denunciante_rut');
            $table->decimal('denunciante_latitud', 10, 7)->nullable()->after('denunciante_email');
            $table->decimal('denunciante_longitud', 10, 7)->nullable()->after('denunciante_latitud');
            $table->boolean('consentimiento_datos')->default(false)->after('confidencial');
            $table->boolean('consentimiento_geolocalizacion')->default(false)->after('consentimiento_datos');
        });
    }

    public function down(): void
    {
        Schema::table('ley_karin', function (Blueprint $table) {
            $table->dropColumn([
                'denunciante_email',
                'denunciante_latitud',
                'denunciante_longitud',
                'consentimiento_datos',
                'consentimiento_geolocalizacion',
            ]);
        });
    }
};
