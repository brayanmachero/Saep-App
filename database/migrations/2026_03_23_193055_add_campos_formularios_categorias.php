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
        // Solo roles adicionales (categorias_formularios se crea en migración posterior)
        $roles = [
            ['codigo'=>'PREVENCIONISTA','nombre'=>'Prevencionista de Riesgos','puede_crear_forms'=>1,'puede_aprobar'=>1,'puede_ver_dashboard'=>1,'puede_admin_usuarios'=>0],
            ['codigo'=>'COORDINADOR',   'nombre'=>'Coordinador / Líder',       'puede_crear_forms'=>0,'puede_aprobar'=>1,'puede_ver_dashboard'=>1,'puede_admin_usuarios'=>0],
            ['codigo'=>'SUPERVISOR',    'nombre'=>'Supervisor de Operaciones', 'puede_crear_forms'=>0,'puede_aprobar'=>1,'puede_ver_dashboard'=>1,'puede_admin_usuarios'=>0],
            ['codigo'=>'JEFE',          'nombre'=>'Jefe de Departamento',      'puede_crear_forms'=>0,'puede_aprobar'=>1,'puede_ver_dashboard'=>1,'puede_admin_usuarios'=>0],
            ['codigo'=>'OPERARIO',      'nombre'=>'Operario / Trabajador',     'puede_crear_forms'=>0,'puede_aprobar'=>0,'puede_ver_dashboard'=>0,'puede_admin_usuarios'=>0],
        ];
        foreach ($roles as $r) {
            if (!\DB::table('roles')->where('codigo', $r['codigo'])->exists()) {
                \DB::table('roles')->insert(array_merge($r, ['created_at'=>now(),'updated_at'=>now()]));
            }
        }
    }

    public function down(): void
    {
        // seed no reversible
    }
};
