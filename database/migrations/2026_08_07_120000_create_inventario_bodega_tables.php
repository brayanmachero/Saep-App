<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 160);
            $table->string('tipo', 30)->default('BODEGA');
            $table->string('descripcion', 300)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'tipo']);
        });

        Schema::create('inventario_proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 180);
            $table->string('rut', 30)->nullable()->unique();
            $table->string('contacto', 160)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['nombre', 'activo']);
        });

        Schema::create('inventario_productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 220);
            $table->string('tipo', 80)->nullable();
            $table->string('categoria', 120)->nullable();
            $table->string('subcategoria', 120)->nullable();
            $table->string('unidad_medida', 30)->default('Unidad');
            $table->decimal('stock_minimo', 14, 3)->default(0);
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['activo', 'categoria']);
            $table->index('nombre');
        });

        Schema::create('inventario_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('inventario_productos')->cascadeOnDelete();
            $table->string('codigo', 100)->nullable()->unique();
            $table->string('talla', 80)->default('ESTANDAR');
            $table->string('descripcion', 180)->nullable();
            $table->decimal('stock_minimo', 14, 3)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['producto_id', 'talla']);
            $table->index(['activo', 'talla']);
        });

        Schema::create('inventario_ingresos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->foreignId('ubicacion_id')->constrained('inventario_ubicaciones')->restrictOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('inventario_proveedores')->nullOnDelete();
            $table->string('tipo_documento', 40)->default('FACTURA');
            $table->string('numero_documento', 100)->nullable();
            $table->date('fecha_documento')->nullable();
            $table->date('fecha_recepcion');
            $table->string('observacion', 500)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ubicacion_id', 'fecha_recepcion']);
            $table->index(['tipo_documento', 'numero_documento']);
        });

        Schema::create('inventario_ingreso_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingreso_id')->constrained('inventario_ingresos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('inventario_productos')->restrictOnDelete();
            $table->foreignId('variante_id')->constrained('inventario_variantes')->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_unitario', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['producto_id', 'variante_id']);
        });

        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 45)->unique();
            $table->string('tipo', 40);
            $table->string('origen', 40)->default('MANUAL');
            $table->foreignId('ubicacion_id')->constrained('inventario_ubicaciones')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('inventario_productos')->restrictOnDelete();
            $table->foreignId('variante_id')->constrained('inventario_variantes')->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_unitario', 14, 2)->nullable();
            $table->string('grupo_traslado', 50)->nullable();
            $table->string('referencia_tipo', 80)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('documento_tipo', 40)->nullable();
            $table->string('documento_numero', 100)->nullable();
            $table->string('destinatario_nombre', 200)->nullable();
            $table->string('destinatario_rut', 30)->nullable();
            $table->string('centro_costo', 180)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->timestamp('ocurrido_en');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('registrado_por_nombre', 200)->nullable();
            $table->foreignId('reverso_de_id')->nullable()->constrained('inventario_movimientos')->nullOnDelete();
            $table->timestamps();

            $table->index(['ubicacion_id', 'variante_id']);
            $table->index(['tipo', 'ocurrido_en']);
            $table->index(['referencia_tipo', 'referencia_id']);
            $table->index('grupo_traslado');
        });

        Schema::create('inventario_conteos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->foreignId('ubicacion_id')->constrained('inventario_ubicaciones')->restrictOnDelete();
            $table->date('fecha_corte');
            $table->string('estado', 30)->default('BORRADOR');
            $table->string('observacion', 500)->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable();
            $table->timestamps();

            $table->index(['ubicacion_id', 'fecha_corte']);
            $table->index('estado');
        });

        Schema::create('inventario_conteo_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteo_id')->constrained('inventario_conteos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('inventario_productos')->restrictOnDelete();
            $table->foreignId('variante_id')->constrained('inventario_variantes')->restrictOnDelete();
            $table->decimal('cantidad_sistema', 14, 3)->default(0);
            $table->decimal('cantidad_fisica', 14, 3)->nullable();
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->unique(['conteo_id', 'variante_id']);
        });

        $now = now();
        DB::table('modulos')->updateOrInsert(
            ['slug' => 'inventario_bodega'],
            [
                'nombre' => 'Inventario',
                'descripcion' => 'Control de stock, ingresos, movimientos y conteos de Bodega.',
                'icono' => 'bi-boxes',
                'grupo' => 'Bodega',
                'orden' => 5,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $moduleId = DB::table('modulos')->where('slug', 'inventario_bodega')->value('id');
        if (! $moduleId) {
            return;
        }

        foreach ([
            'SUPER_ADMIN' => [true, true, true, true],
            'BODEGA_ENTREGAS' => [true, true, true, false],
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
        $moduleId = DB::table('modulos')->where('slug', 'inventario_bodega')->value('id');
        if ($moduleId) {
            DB::table('rol_modulo')->where('modulo_id', $moduleId)->delete();
            DB::table('modulos')->where('id', $moduleId)->delete();
        }

        Schema::dropIfExists('inventario_conteo_lineas');
        Schema::dropIfExists('inventario_conteos');
        Schema::dropIfExists('inventario_movimientos');
        Schema::dropIfExists('inventario_ingreso_items');
        Schema::dropIfExists('inventario_ingresos');
        Schema::dropIfExists('inventario_variantes');
        Schema::dropIfExists('inventario_productos');
        Schema::dropIfExists('inventario_proveedores');
        Schema::dropIfExists('inventario_ubicaciones');
    }
};
