<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_formularios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('descripcion', 500)->nullable();
            $table->string('icono', 50)->default('bi-folder');
            $table->string('color', 20)->default('#0d6efd');
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Seed categorías
        $cats = [
            ['Autos SAEP',            'Inspección vehicular',             'bi-truck',             '#0d6efd', 1],
            ['Bodega - SAEP',         'Control de entrega en bodega',     'bi-box-seam',          '#198754', 2],
            ['CCU Central',           'Formularios CCU Central',          'bi-building',          '#6f42c1', 3],
            ['CCU Modelo',            'Formularios CCU Modelo',           'bi-building',          '#6f42c1', 4],
            ['CCU Sur',               'Formularios CCU Sur',              'bi-building',          '#6f42c1', 5],
            ['Control de EPP',        'Formularios de control EPP',       'bi-shield-check',      '#fd7e14', 6],
            ['EPP DHL Global',        'EPP DHL Global Supply Chain',      'bi-box-arrow-right',   '#20c997', 7],
            ['LTS Walmart',           'EPP Walmart centros varios',       'bi-cart4',             '#0dcaf0', 8],
            ['MAERSK',                'Formularios MAERSK',               'bi-globe',             '#004080', 9],
            ['Medtronic',             'Formularios Medtronic',            'bi-heart-pulse',       '#dc3545', 10],
            ['Prevención de Riesgos', 'Formularios generales SST',        'bi-shield-fill-check', '#003366', 11],
            ['Sodimac',               'Formularios Sodimac',              'bi-shop-window',       '#ff5722', 12],
            ['Tottus',                'Formularios Tottus',               'bi-bag-check',         '#4caf50', 13],
            ['Otros',                 'Formularios varios',               'bi-folder',            '#6c757d', 14],
        ];
        foreach ($cats as $c) {
            \DB::table('categorias_formularios')->insert([
                'nombre'=>$c[0],'descripcion'=>$c[1],'icono'=>$c[2],'color'=>$c[3],'orden'=>$c[4],
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_formularios');
    }
};
