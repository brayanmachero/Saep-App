<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_interno', 30)->nullable()->unique();
            $table->string('patente', 16)->unique();
            $table->string('nombre', 120)->nullable();
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 120)->nullable();
            $table->string('tipo', 40)->default('AUTOMOVIL');
            $table->unsignedTinyInteger('capacidad')->nullable();
            $table->string('sede', 120)->nullable();
            $table->string('color', 60)->nullable();
            $table->enum('estado', ['DISPONIBLE', 'MANTENIMIENTO', 'FUERA_SERVICIO'])->default('DISPONIBLE');
            $table->boolean('reservas_habilitadas')->default(true);
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['estado', 'reservas_habilitadas']);
            $table->index('sede');
        });

        Schema::create('solicitantes_reserva_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200)->unique();
            $table->string('email', 200)->nullable()->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'email']);
        });

        Schema::create('reservas_vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 32)->nullable()->unique();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('solicitante_oid', 100)->nullable()->index();
            $table->string('solicitante_email', 200)->index();
            $table->string('solicitante_nombre', 200);
            $table->string('solicitante_telefono', 50)->nullable();
            $table->dateTime('inicio');
            $table->dateTime('termino');
            $table->string('destino', 300)->nullable();
            $table->text('motivo');
            $table->unsignedTinyInteger('pasajeros')->nullable();
            $table->enum('estado', ['CONFIRMADA', 'CANCELADA', 'EN_USO', 'DEVUELTA', 'VENCIDA'])->default('CONFIRMADA');
            $table->timestamp('cancelada_at')->nullable();
            $table->string('cancelada_por_email', 200)->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestamp('recordatorio_enviado_at')->nullable();
            $table->timestamp('vencimiento_notificado_at')->nullable();
            $table->timestamps();

            $table->index(['vehiculo_id', 'estado', 'inicio', 'termino'], 'reservas_vehiculo_cruce_idx');
            $table->index(['estado', 'inicio']);
            $table->index(['estado', 'termino']);
        });

        Schema::create('reserva_vehiculo_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_vehiculo_id')->constrained('reservas_vehiculos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_oid', 100)->nullable();
            $table->string('actor_email', 200)->nullable();
            $table->string('actor_nombre', 200)->nullable();
            $table->string('accion', 80);
            $table->string('resumen', 500);
            $table->json('cambios')->nullable();
            $table->timestamps();

            $table->index(['reserva_vehiculo_id', 'created_at']);
        });

        $now = now();

        foreach ([
            ['codigo_interno' => '41', 'patente' => 'CGVC-41', 'marca' => 'Fiat', 'modelo' => 'Fiorino'],
            ['codigo_interno' => '38', 'patente' => 'PSHD-38', 'marca' => 'Chevrolet', 'modelo' => 'N400'],
            ['codigo_interno' => '67', 'patente' => 'SYGT-67', 'marca' => 'Chevrolet', 'modelo' => 'N400'],
            ['codigo_interno' => '56', 'patente' => 'SFKF-56', 'marca' => 'Chevrolet', 'modelo' => 'SAIL'],
            ['codigo_interno' => '72', 'patente' => 'SFKF-72', 'marca' => 'Chevrolet', 'modelo' => 'SAIL'],
        ] as $vehiculo) {
            DB::table('vehiculos')->updateOrInsert(
                ['patente' => $vehiculo['patente']],
                [
                    ...$vehiculo,
                    'nombre' => 'Movil '.$vehiculo['codigo_interno'],
                    'tipo' => 'AUTOMOVIL',
                    'estado' => 'DISPONIBLE',
                    'reservas_habilitadas' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach ([
            'Carlos Fernando Mardones Miranda',
            'Felipe Gonzalo Huerta Gormaz',
            'Jahdelsy Luciels Gutierrez Martinez',
            'Jose Alberto Crespo Contreras',
            'Juan Orlando Mendoza Gomez',
            'Osvaldo Hernan Ahumada Figueroa',
            'Amaya Sanchez',
            'Jean Corrales',
            'Sebastian Foster',
        ] as $nombre) {
            DB::table('solicitantes_reserva_vehiculo')->updateOrInsert(
                ['nombre' => $nombre],
                ['activo' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        DB::table('modulos')->updateOrInsert(
            ['slug' => 'gestion_vehiculos'],
            [
                'nombre' => 'Vehiculos',
                'descripcion' => 'Flota y reservas operativas de vehiculos de empresa.',
                'icono' => 'bi-car-front-fill',
                'grupo' => 'Bodega',
                'orden' => 20,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('roles')->updateOrInsert(
            ['codigo' => 'BODEGA_VEHICULOS'],
            [
                'nombre' => 'Bodega - Vehiculos',
                'puede_crear_forms' => false,
                'puede_aprobar' => false,
                'puede_ver_dashboard' => true,
                'puede_admin_usuarios' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $moduleId = DB::table('modulos')->where('slug', 'gestion_vehiculos')->value('id');
        if (! $moduleId) {
            return;
        }

        foreach ([
            'SUPER_ADMIN' => [true, true, true, true],
            'BODEGA_ENTREGAS' => [true, true, true, false],
            'BODEGA_VEHICULOS' => [true, true, true, true],
        ] as $roleCode => [$canView, $canCreate, $canEdit, $canDelete]) {
            $roleId = DB::table('roles')->where('codigo', $roleCode)->value('id');
            if (! $roleId) {
                continue;
            }

            DB::table('rol_modulo')->updateOrInsert(
                ['rol_id' => $roleId, 'modulo_id' => $moduleId],
                [
                    'puede_ver' => $canView,
                    'puede_crear' => $canCreate,
                    'puede_editar' => $canEdit,
                    'puede_eliminar' => $canDelete,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        $moduleId = DB::table('modulos')->where('slug', 'gestion_vehiculos')->value('id');
        if ($moduleId) {
            DB::table('rol_modulo')->where('modulo_id', $moduleId)->delete();
            DB::table('modulos')->where('id', $moduleId)->delete();
        }

        Schema::dropIfExists('reserva_vehiculo_eventos');
        Schema::dropIfExists('reservas_vehiculos');
        Schema::dropIfExists('solicitantes_reserva_vehiculo');
        Schema::dropIfExists('vehiculos');
    }
};
