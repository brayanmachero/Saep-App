<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reclutamiento_whatsapp_contactos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('telefono', 20)->unique();
            $table->string('email', 200)->nullable()->index();
            $table->string('origen', 40)->default('manual');
            $table->string('origen_detalle', 160)->nullable();
            $table->boolean('consentimiento_whatsapp')->default(false);
            $table->timestamp('consentimiento_aceptado_at')->nullable();
            $table->string('consentimiento_origen', 120)->nullable();
            $table->text('consentimiento_texto')->nullable();
            $table->string('consentimiento_ip', 45)->nullable();
            $table->text('consentimiento_user_agent')->nullable();
            $table->timestamp('consentimiento_revocado_at')->nullable();
            $table->string('motivo_revocacion', 200)->nullable();
            $table->timestamps();

            $table->index(['consentimiento_whatsapp', 'consentimiento_revocado_at'], 'rw_contactos_consentimiento_idx');
        });

        Schema::create('reclutamiento_whatsapp_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_meta', 200)->unique();
            $table->string('idioma', 20)->default('es');
            $table->string('categoria', 30)->default('utility');
            $table->string('estado', 30)->default('pendiente');
            $table->json('componentes')->nullable();
            $table->timestamp('sincronizada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reclutamiento_whatsapp_campanias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 160);
            $table->text('descripcion')->nullable();
            $table->foreignId('plantilla_id')->nullable()
                ->constrained('reclutamiento_whatsapp_plantillas')->nullOnDelete();
            $table->string('plantilla_nombre', 200);
            $table->string('plantilla_idioma', 20)->default('es');
            $table->string('categoria', 30)->default('utility');
            $table->string('estado', 30)->default('borrador');
            $table->json('filtro_destinatarios')->nullable();
            $table->unsignedInteger('destinatarios_estimados')->default(0);
            $table->unsignedInteger('enviados')->default(0);
            $table->unsignedInteger('entregados')->default(0);
            $table->unsignedInteger('leidos')->default(0);
            $table->unsignedInteger('fallidos')->default(0);
            $table->timestamp('programada_para')->nullable();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobada_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'programada_para'], 'rw_campanias_estado_programada_idx');
        });

        Schema::create('reclutamiento_whatsapp_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campania_id')->constrained('reclutamiento_whatsapp_campanias')->cascadeOnDelete();
            $table->foreignId('contacto_id')->constrained('reclutamiento_whatsapp_contactos')->cascadeOnDelete();
            $table->string('estado', 30)->default('pendiente');
            $table->string('meta_message_id', 255)->nullable()->unique();
            $table->json('parametros_plantilla')->nullable();
            $table->string('codigo_error', 100)->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->timestamp('respondido_at')->nullable();
            $table->timestamps();

            $table->unique(['campania_id', 'contacto_id'], 'rw_campania_contacto_unique');
            $table->index(['estado', 'created_at'], 'rw_destinatarios_estado_idx');
        });

        Schema::create('reclutamiento_whatsapp_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contacto_id')->constrained('reclutamiento_whatsapp_contactos')->cascadeOnDelete();
            $table->foreignId('campania_origen_id')->nullable()
                ->constrained('reclutamiento_whatsapp_campanias')->nullOnDelete();
            $table->string('estado', 30)->default('nueva');
            $table->foreignId('asignada_a')->nullable()->constrained('users')->nullOnDelete();
            $table->text('ultimo_mensaje_preview')->nullable();
            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->timestamp('ultimo_mensaje_entrante_at')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();

            $table->unique('contacto_id', 'rw_conversacion_contacto_unique');
            $table->index(['estado', 'asignada_a', 'ultimo_mensaje_at'], 'rw_conversaciones_bandeja_idx');
        });

        Schema::create('reclutamiento_whatsapp_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('reclutamiento_whatsapp_conversaciones')->cascadeOnDelete();
            $table->string('direccion', 20);
            $table->string('tipo', 30)->default('texto');
            $table->string('meta_message_id', 255)->nullable()->unique();
            $table->text('contenido')->nullable();
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 30)->default('recibido');
            $table->timestamp('ocurrido_at')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();

            $table->index(['conversacion_id', 'ocurrido_at'], 'rw_mensajes_conversacion_fecha_idx');
        });

        Schema::create('reclutamiento_whatsapp_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('reclutamiento_whatsapp_conversaciones')->cascadeOnDelete();
            $table->foreignId('asignada_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('asignada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 30);
            $table->timestamps();

            $table->index(['conversacion_id', 'created_at'], 'rw_asignaciones_conversacion_fecha_idx');
        });

        Schema::create('reclutamiento_whatsapp_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campania_id')->nullable()->constrained('reclutamiento_whatsapp_campanias')->nullOnDelete();
            $table->foreignId('contacto_id')->nullable()->constrained('reclutamiento_whatsapp_contactos')->nullOnDelete();
            $table->foreignId('destinatario_id')->nullable()->constrained('reclutamiento_whatsapp_destinatarios')->nullOnDelete();
            $table->string('meta_event_id', 255)->nullable()->index();
            $table->string('tipo', 50);
            $table->json('datos')->nullable();
            $table->timestamp('ocurrido_at')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'ocurrido_at'], 'rw_eventos_tipo_ocurrido_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclutamiento_whatsapp_eventos');
        Schema::dropIfExists('reclutamiento_whatsapp_asignaciones');
        Schema::dropIfExists('reclutamiento_whatsapp_mensajes');
        Schema::dropIfExists('reclutamiento_whatsapp_conversaciones');
        Schema::dropIfExists('reclutamiento_whatsapp_destinatarios');
        Schema::dropIfExists('reclutamiento_whatsapp_campanias');
        Schema::dropIfExists('reclutamiento_whatsapp_plantillas');
        Schema::dropIfExists('reclutamiento_whatsapp_contactos');
    }
};
