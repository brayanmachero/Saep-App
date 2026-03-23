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
        Schema::create('templates_pdf', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->longText('html_template');
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('formularios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100)->unique();
            $table->string('nombre', 200);
            $table->string('descripcion', 1000)->nullable();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->onDelete('set null');
            $table->longText('schema_json');
            $table->integer('version')->default(1);
            $table->boolean('activo')->default(true);
            $table->boolean('requiere_aprobacion')->default(false);
            $table->foreignId('aprobador_rol_id')->nullable()->constrained('roles')->onDelete('set null');
            $table->boolean('genera_pdf')->default(false);
            $table->foreignId('template_pdf_id')->nullable()->constrained('templates_pdf')->onDelete('set null');
            $table->string('fuente_trabajadores', 50)->default('talana');
            $table->foreignId('creado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::table('templates_pdf', function (Blueprint $table) {
            $table->foreignId('formulario_id')->nullable()->constrained('formularios')->onDelete('set null');
        });

        Schema::create('respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formulario_id')->constrained('formularios')->onDelete('cascade');
            $table->integer('version_form');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('talana_trabajador_id', 100)->nullable()->index();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->onDelete('set null');
            $table->enum('estado', ['Borrador', 'Pendiente', 'Aprobado', 'Rechazado', 'Revisión'])->default('Pendiente')->index();
            $table->longText('datos_json');
            $table->string('comentario_solicitante', 2000)->nullable();
            $table->string('pdf_url', 1000)->nullable();
            $table->string('kizeo_form_id', 200)->nullable();
            $table->string('kizeo_record_id', 200)->nullable();
            $table->timestamp('fecha_envio')->useCurrent()->index();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->timestamps();
        });

        Schema::create('aprobaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_id')->constrained('respuestas')->onDelete('cascade');
            $table->foreignId('aprobador_id')->constrained('users')->onDelete('cascade');
            $table->enum('accion', ['Aprobado', 'Rechazado', 'Revisión', 'Comentario']);
            $table->string('comentario', 2000)->nullable();
            $table->timestamp('fecha')->useCurrent();
            $table->timestamps();
        });

        Schema::create('documentos_kizeo', function (Blueprint $table) {
            $table->id();
            $table->string('kizeo_form_id', 200);
            $table->string('kizeo_record_id', 200)->unique();
            $table->string('tipo_documento', 200)->nullable();
            $table->string('talana_trabajador_id', 100)->nullable()->index();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('blob_url', 1000)->nullable();
            $table->string('blob_nombre', 500)->nullable();
            $table->string('estado_dt', 100)->nullable();
            $table->boolean('vigente')->default(true)->index();
            $table->timestamp('fecha_documento')->nullable();
            $table->timestamp('fecha_vencimiento')->nullable();
            $table->timestamp('fecha_ingreso')->useCurrent();
            $table->longText('metadata_json')->nullable();
            $table->timestamps();
        });

        Schema::create('log_integraciones', function (Blueprint $table) {
            $table->id();
            $table->string('servicio', 50);
            $table->string('endpoint', 500);
            $table->string('metodo', 10);
            $table->integer('status_code')->nullable();
            $table->boolean('exitoso')->nullable();
            $table->string('mensaje_error', 2000)->nullable();
            $table->integer('duracion_ms')->nullable();
            $table->timestamp('fecha')->useCurrent();
            $table->timestamps();
        });

        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_id')->nullable()->constrained('respuestas')->onDelete('cascade');
            $table->string('destinatario_email', 200);
            $table->string('tipo', 100);
            $table->string('asunto', 500)->nullable();
            $table->boolean('enviado')->default(false);
            $table->timestamp('fecha_envio')->nullable();
            $table->string('error', 1000)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
        Schema::dropIfExists('log_integraciones');
        Schema::dropIfExists('documentos_kizeo');
        Schema::dropIfExists('aprobaciones');
        Schema::dropIfExists('respuestas');
        
        Schema::table('templates_pdf', function (Blueprint $table) {
            $table->dropForeign(['formulario_id']);
        });

        Schema::dropIfExists('formularios');
        Schema::dropIfExists('templates_pdf');
    }
};
