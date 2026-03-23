<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->boolean('puede_crear_forms')->default(false);
            $table->boolean('puede_aprobar')->default(false);
            $table->boolean('puede_ver_dashboard')->default(false);
            $table->boolean('puede_admin_usuarios')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('azure_oid', 200)->unique()->nullable();
            $table->string('talana_id', 100)->nullable();
            $table->string('name', 200);
            $table->string('email', 200)->unique();
            $table->string('rut', 20)->nullable();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->onDelete('set null');
            $table->foreignId('rol_id')->constrained('roles');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_acceso')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('departamentos');
    }
};
