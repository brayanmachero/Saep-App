<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de módulos del sistema
        Schema::create('modulos', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('nombre', 120);
            $table->string('descripcion', 300)->nullable();
            $table->string('icono', 60)->default('bi-circle');
            $table->string('grupo', 80)->default('General');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Tabla pivote: qué módulos puede ver cada rol
        Schema::create('rol_modulo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('modulo_id')->constrained('modulos')->onDelete('cascade');
            $table->boolean('puede_ver')->default(true);
            $table->boolean('puede_crear')->default(false);
            $table->boolean('puede_editar')->default(false);
            $table->boolean('puede_eliminar')->default(false);
            $table->timestamps();
            $table->unique(['rol_id', 'modulo_id']);
        });

        // Insertar módulos del sistema
        $modulos = [
            // --- General ---
            ['slug' => 'dashboard',            'nombre' => 'Panel Principal',         'icono' => 'bi-grid-1x2-fill',       'grupo' => 'General',           'orden' => 1],

            // --- Solicitudes ---
            ['slug' => 'formularios',          'nombre' => 'Formularios',             'icono' => 'bi-ui-checks-grid',      'grupo' => 'Solicitudes',       'orden' => 10],
            ['slug' => 'categorias_formularios','nombre' => 'Categorías Formularios', 'icono' => 'bi-tags-fill',           'grupo' => 'Solicitudes',       'orden' => 11],
            ['slug' => 'respuestas',           'nombre' => 'Solicitudes / Respuestas','icono' => 'bi-inbox-fill',          'grupo' => 'Solicitudes',       'orden' => 12],

            // --- Prevención SST ---
            ['slug' => 'kizeo_analytics',      'nombre' => 'Kizeo Analytics',         'icono' => 'bi-activity',            'grupo' => 'Prevención SST',    'orden' => 20],
            ['slug' => 'charlas',              'nombre' => 'Charlas SST',             'icono' => 'bi-mic-fill',            'grupo' => 'Prevención SST',    'orden' => 21],
            ['slug' => 'carta_gantt',          'nombre' => 'Carta Gantt',             'icono' => 'bi-bar-chart-steps',     'grupo' => 'Prevención SST',    'orden' => 22],
            ['slug' => 'visitas_sst',          'nombre' => 'Visitas / Inspecciones',  'icono' => 'bi-clipboard2-check',    'grupo' => 'Prevención SST',    'orden' => 23],
            ['slug' => 'auditorias_sst',       'nombre' => 'Auditorías SST',          'icono' => 'bi-shield-check',        'grupo' => 'Prevención SST',    'orden' => 24],
            ['slug' => 'accidentes_sst',       'nombre' => 'Accidentes SST',          'icono' => 'bi-exclamation-diamond-fill','grupo' => 'Prevención SST', 'orden' => 25],
            ['slug' => 'ley_karin',            'nombre' => 'Ley Karin (Gestión)',     'icono' => 'bi-shield-exclamation',  'grupo' => 'Prevención SST',    'orden' => 26],
            ['slug' => 'ley_karin_denuncia',   'nombre' => 'Canal de Denuncia',       'icono' => 'bi-megaphone-fill',      'grupo' => 'Prevención SST',    'orden' => 27],

            // --- Administración ---
            ['slug' => 'usuarios',             'nombre' => 'Gestión de Usuarios',     'icono' => 'bi-people-fill',         'grupo' => 'Administración',    'orden' => 30],
            ['slug' => 'departamentos',        'nombre' => 'Departamentos',           'icono' => 'bi-building',            'grupo' => 'Administración',    'orden' => 31],
            ['slug' => 'cargos',               'nombre' => 'Cargos',                  'icono' => 'bi-person-badge-fill',   'grupo' => 'Administración',    'orden' => 32],
            ['slug' => 'centros_costo',        'nombre' => 'Centros de Costo',        'icono' => 'bi-wallet2',             'grupo' => 'Administración',    'orden' => 33],

            // --- Sistema ---
            ['slug' => 'configuracion',        'nombre' => 'Configuración',           'icono' => 'bi-gear-fill',           'grupo' => 'Sistema',           'orden' => 40],
            ['slug' => 'permisos',             'nombre' => 'Permisos por Rol',        'icono' => 'bi-key-fill',            'grupo' => 'Sistema',           'orden' => 41],
            ['slug' => 'importacion',          'nombre' => 'Importar Datos',          'icono' => 'bi-cloud-upload-fill',   'grupo' => 'Sistema',           'orden' => 42],
            ['slug' => 'exportaciones',        'nombre' => 'Exportaciones',           'icono' => 'bi-download',            'grupo' => 'Sistema',           'orden' => 43],

            // --- Protección de Datos ---
            ['slug' => 'proteccion_datos',     'nombre' => 'Protección de Datos',     'icono' => 'bi-shield-lock-fill',    'grupo' => 'Protección Datos',  'orden' => 50],

            // --- Ayuda ---
            ['slug' => 'documentacion',        'nombre' => 'Documentación',           'icono' => 'bi-book-fill',           'grupo' => 'Ayuda',             'orden' => 60],
        ];

        $now = now();
        foreach ($modulos as $m) {
            DB::table('modulos')->insert(array_merge($m, [
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Asignar permisos por defecto (replica la lógica hardcodeada actual)
        $roles = DB::table('roles')->pluck('id', 'codigo')->toArray();
        $mods  = DB::table('modulos')->pluck('id', 'slug')->toArray();

        $permisos = [
            // SUPER_ADMIN: acceso total
            'SUPER_ADMIN' => [
                'dashboard' => [1,1,1,1], 'formularios' => [1,1,1,1], 'categorias_formularios' => [1,1,1,1],
                'respuestas' => [1,1,1,1], 'kizeo_analytics' => [1,1,1,1], 'charlas' => [1,1,1,1],
                'carta_gantt' => [1,1,1,1], 'visitas_sst' => [1,1,1,1], 'auditorias_sst' => [1,1,1,1],
                'accidentes_sst' => [1,1,1,1], 'ley_karin' => [1,1,1,1], 'usuarios' => [1,1,1,1],
                'departamentos' => [1,1,1,1], 'cargos' => [1,1,1,1], 'centros_costo' => [1,1,1,1],
                'configuracion' => [1,1,1,1], 'permisos' => [1,1,1,1], 'importacion' => [1,1,1,1],
                'exportaciones' => [1,1,1,1], 'proteccion_datos' => [1,1,1,1], 'documentacion' => [1,0,0,0],
            ],
            // PREVENCIONISTA: todo prevención + admin usuarios + protección datos
            'PREVENCIONISTA' => [
                'dashboard' => [1,0,0,0], 'formularios' => [1,1,1,1], 'categorias_formularios' => [1,1,1,1],
                'respuestas' => [1,1,1,0], 'kizeo_analytics' => [1,1,0,0], 'charlas' => [1,1,1,1],
                'carta_gantt' => [1,1,1,1], 'visitas_sst' => [1,1,1,1], 'auditorias_sst' => [1,1,1,1],
                'accidentes_sst' => [1,1,1,1], 'ley_karin' => [1,1,1,1], 'usuarios' => [1,1,1,1],
                'departamentos' => [1,1,1,1], 'cargos' => [1,1,1,1], 'centros_costo' => [1,1,1,1],
                'exportaciones' => [1,0,0,0], 'proteccion_datos' => [1,1,1,0], 'documentacion' => [1,0,0,0],
            ],
            // JEFE: prevención sin admin
            'JEFE' => [
                'dashboard' => [1,0,0,0], 'respuestas' => [1,1,0,0], 'kizeo_analytics' => [1,0,0,0],
                'charlas' => [1,1,1,0], 'carta_gantt' => [1,1,1,0], 'visitas_sst' => [1,1,1,0],
                'auditorias_sst' => [1,1,1,0], 'accidentes_sst' => [1,1,1,0],
                'exportaciones' => [1,0,0,0], 'documentacion' => [1,0,0,0],
            ],
            // COORDINADOR: similar a JEFE
            'COORDINADOR' => [
                'dashboard' => [1,0,0,0], 'respuestas' => [1,1,0,0], 'kizeo_analytics' => [1,0,0,0],
                'charlas' => [1,1,1,0], 'carta_gantt' => [1,1,1,0], 'visitas_sst' => [1,1,1,0],
                'auditorias_sst' => [1,1,1,0], 'accidentes_sst' => [1,1,1,0],
                'exportaciones' => [1,0,0,0], 'documentacion' => [1,0,0,0],
            ],
            // SUPERVISOR: similar
            'SUPERVISOR' => [
                'dashboard' => [1,0,0,0], 'respuestas' => [1,1,0,0], 'kizeo_analytics' => [1,0,0,0],
                'charlas' => [1,1,1,0], 'carta_gantt' => [1,1,1,0], 'visitas_sst' => [1,1,1,0],
                'auditorias_sst' => [1,1,1,0], 'accidentes_sst' => [1,1,1,0],
                'exportaciones' => [1,0,0,0], 'documentacion' => [1,0,0,0],
            ],
            // OPERARIO: solo lo básico
            'OPERARIO' => [
                'dashboard' => [1,0,0,0], 'respuestas' => [1,1,0,0], 'charlas' => [1,0,0,0],
                'ley_karin_denuncia' => [1,1,0,0], 'proteccion_datos' => [1,1,0,0], 'documentacion' => [1,0,0,0],
            ],
        ];

        foreach ($permisos as $rolCodigo => $moduloPerms) {
            if (!isset($roles[$rolCodigo])) continue;
            $rolId = $roles[$rolCodigo];
            foreach ($moduloPerms as $modSlug => $p) {
                if (!isset($mods[$modSlug])) continue;
                DB::table('rol_modulo')->insert([
                    'rol_id'          => $rolId,
                    'modulo_id'       => $mods[$modSlug],
                    'puede_ver'       => $p[0],
                    'puede_crear'     => $p[1],
                    'puede_editar'    => $p[2],
                    'puede_eliminar'  => $p[3],
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_modulo');
        Schema::dropIfExists('modulos');
    }
};
