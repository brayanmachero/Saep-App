<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reclutamiento_whatsapp_contactos', function (Blueprint $table) {
            if (!Schema::hasColumn('reclutamiento_whatsapp_contactos', 'consentimiento_finalidad')) {
                $table->string('consentimiento_finalidad', 80)->nullable();
            }
            if (!Schema::hasColumn('reclutamiento_whatsapp_contactos', 'consentimiento_version')) {
                $table->string('consentimiento_version', 50)->nullable();
            }
            if (!Schema::hasColumn('reclutamiento_whatsapp_contactos', 'consentimiento_evidencia_ref')) {
                $table->string('consentimiento_evidencia_ref', 500)->nullable();
            }
            if (!Schema::hasColumn('reclutamiento_whatsapp_contactos', 'consentimiento_verificado_at')) {
                $table->timestamp('consentimiento_verificado_at')->nullable();
            }
            if (!Schema::hasColumn('reclutamiento_whatsapp_contactos', 'consentimiento_verificado_por')) {
                $table->unsignedBigInteger('consentimiento_verificado_por')->nullable();
            }
            if (!Schema::hasColumn('reclutamiento_whatsapp_contactos', 'retencion_hasta')) {
                $table->date('retencion_hasta')->nullable();
            }
        });

        $foreignKeys = collect(Schema::getForeignKeys('reclutamiento_whatsapp_contactos'))->pluck('name');
        if (!$foreignKeys->contains('rw_contactos_verif_user_fk')) {
            Schema::table('reclutamiento_whatsapp_contactos', function (Blueprint $table) {
                $table->foreign('consentimiento_verificado_por', 'rw_contactos_verif_user_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        Schema::table('reclutamiento_whatsapp_contactos', function (Blueprint $table) {
            $table->index(
                ['consentimiento_whatsapp', 'consentimiento_revocado_at', 'retencion_hasta'],
                'rw_contactos_elegibilidad_idx'
            );
        });

        Schema::table('reclutamiento_whatsapp_campanias', function (Blueprint $table) {
            if (!Schema::hasColumn('reclutamiento_whatsapp_campanias', 'finalidad')) {
                $table->string('finalidad', 80)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reclutamiento_whatsapp_campanias', function (Blueprint $table) {
            $table->dropIndex(['finalidad']);
            $table->dropColumn('finalidad');
        });

        Schema::table('reclutamiento_whatsapp_contactos', function (Blueprint $table) {
            $table->dropIndex('rw_contactos_elegibilidad_idx');
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->dropForeign(['consentimiento_verificado_por']);
            } else {
                $table->dropForeign('rw_contactos_verif_user_fk');
            }
            $table->dropColumn([
                'consentimiento_finalidad',
                'consentimiento_version',
                'consentimiento_evidencia_ref',
                'consentimiento_verificado_at',
                'consentimiento_verificado_por',
                'retencion_hasta',
            ]);
        });
    }
};
