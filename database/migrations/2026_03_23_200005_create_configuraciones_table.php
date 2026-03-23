<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->text('valor')->nullable();
            $table->enum('tipo', ['TEXT', 'NUMBER', 'BOOLEAN', 'JSON', 'EMAIL', 'PASSWORD'])->default('TEXT');
            $table->string('categoria', 100)->default('general');
            $table->string('descripcion', 500)->nullable();
            $table->boolean('editable')->default(true);
            $table->timestamps();
        });

        // Seed de configuraciones base
        $configs = [
            ['empresa_nombre',              'SAEP - Servicios de Asesorías a Empresas', 'TEXT',    'general',        'Nombre de la empresa'],
            ['empresa_rut',                 '76.XXX.XXX-X',                             'TEXT',    'general',        'RUT de la empresa'],
            ['empresa_logo_url',            null,                                        'TEXT',    'general',        'URL del logo corporativo'],
            ['email_from',                  'noreply@saep.cl',                          'EMAIL',   'email',          'Email remitente'],
            ['email_from_name',             'SAEP Platform',                            'TEXT',    'email',          'Nombre remitente'],
            ['integracion_talana_activa',   'false',                                    'BOOLEAN', 'integraciones',  'Integración con Talana habilitada'],
            ['integracion_talana_api_key',  null,                                        'PASSWORD','integraciones',  'API Key de Talana'],
            ['integracion_kizeo_activa',    'false',                                    'BOOLEAN', 'integraciones',  'Integración con Kizeo Forms habilitada'],
            ['integracion_kizeo_token',     null,                                        'PASSWORD','integraciones',  'Token de Kizeo Forms'],
            ['seguridad_max_intentos_login','5',                                         'NUMBER',  'seguridad',      'Máximo intentos de login'],
            ['seguridad_bloqueo_minutos',   '15',                                        'NUMBER',  'seguridad',      'Minutos de bloqueo por intentos fallidos'],
            ['notificaciones_email',        'true',                                      'BOOLEAN', 'notificaciones', 'Envío de emails activo'],
            ['notificaciones_aprobacion',   'true',                                      'BOOLEAN', 'notificaciones', 'Notificar cuando se requiere aprobación'],
        ];
        foreach ($configs as $c) {
            \DB::table('configuraciones')->insert([
                'clave'       => $c[0],
                'valor'       => $c[1],
                'tipo'        => $c[2],
                'categoria'   => $c[3],
                'descripcion' => $c[4],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
