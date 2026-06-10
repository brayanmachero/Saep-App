<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comercial_clientes', function (Blueprint $table) {
            $table->string('rut')->nullable()->change();
            $table->string('email')->nullable()->change();
        });

        Schema::table('comercial_centros_costo', function (Blueprint $table) {
            $table->string('codigo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('comercial_clientes', function (Blueprint $table) {
            $table->string('rut')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('comercial_centros_costo', function (Blueprint $table) {
            $table->string('codigo')->nullable(false)->change();
        });
    }
};
