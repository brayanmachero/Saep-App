<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas_bodega', function (Blueprint $table) {
            $table->id();
            $table->string('kizeo_data_id', 32)->unique();
            $table->unsignedInteger('kizeo_record_number')->nullable();
            $table->timestamp('kizeo_created_at')->nullable();
            $table->timestamp('kizeo_updated_at')->nullable();
            $table->string('registrado_por', 200)->nullable();
            $table->string('centro', 180)->nullable();
            $table->string('rut', 30)->nullable();
            $table->string('nombre', 200)->nullable();
            $table->date('fecha_pedido')->nullable();
            $table->unsignedSmallInteger('lineas_count')->default(0);
            $table->unsignedInteger('unidades_total')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('fecha_pedido');
            $table->index('centro');
            $table->index('nombre');
            $table->index('rut');
        });

        Schema::create('entrega_bodega_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_bodega_id')->constrained('entregas_bodega')->cascadeOnDelete();
            $table->unsignedSmallInteger('linea');
            $table->string('articulo', 200)->nullable();
            $table->string('talla', 80)->nullable();
            $table->unsignedInteger('cantidad')->default(0);
            $table->timestamps();

            $table->unique(['entrega_bodega_id', 'linea']);
            $table->index('articulo');
            $table->index('talla');
        });

        $now = now();
        DB::table('modulos')->updateOrInsert(
            ['slug' => 'entregas_bodega_dashboard'],
            [
                'nombre' => 'Entregas de Bodega',
                'descripcion' => 'Dashboard de entregas de EPP sincronizadas desde Kizeo.',
                'icono' => 'bi-box-seam-fill',
                'grupo' => 'Bodega',
                'orden' => 10,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('roles')->updateOrInsert(
            ['codigo' => 'BODEGA_ENTREGAS'],
            [
                'nombre' => 'Bodega - Entregas',
                'puede_crear_forms' => false,
                'puede_aprobar' => false,
                'puede_ver_dashboard' => true,
                'puede_admin_usuarios' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $moduleId = DB::table('modulos')->where('slug', 'entregas_bodega_dashboard')->value('id');
        if (! $moduleId) {
            return;
        }

        foreach ([
            'SUPER_ADMIN' => [true, true, true, true],
            'BODEGA_ENTREGAS' => [true, false, true, false],
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
        $moduleId = DB::table('modulos')->where('slug', 'entregas_bodega_dashboard')->value('id');
        if ($moduleId) {
            DB::table('rol_modulo')->where('modulo_id', $moduleId)->delete();
            DB::table('modulos')->where('id', $moduleId)->delete();
        }

        Schema::dropIfExists('entrega_bodega_items');
        Schema::dropIfExists('entregas_bodega');
    }
};
