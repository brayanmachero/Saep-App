<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        $superAdmin = Rol::firstOrCreate(['codigo' => 'SUPER_ADMIN'], [
            'nombre'              => 'Super Admin',
            'puede_crear_forms'   => true,
            'puede_aprobar'       => true,
            'puede_ver_dashboard' => true,
            'puede_admin_usuarios'=> true,
        ]);

        Rol::firstOrCreate(['codigo' => 'PREVENCIONISTA'], [
            'nombre'              => 'Prevencionista',
            'puede_crear_forms'   => true,
            'puede_aprobar'       => true,
            'puede_ver_dashboard' => true,
            'puede_admin_usuarios'=> false,
        ]);

        Rol::firstOrCreate(['codigo' => 'SUPERVISOR'], [
            'nombre'              => 'Supervisor',
            'puede_crear_forms'   => false,
            'puede_aprobar'       => true,
            'puede_ver_dashboard' => true,
            'puede_admin_usuarios'=> false,
        ]);

        Rol::firstOrCreate(['codigo' => 'TRABAJADOR'], [
            'nombre'              => 'Trabajador',
            'puede_crear_forms'   => false,
            'puede_aprobar'       => false,
            'puede_ver_dashboard' => false,
            'puede_admin_usuarios'=> false,
        ]);

        // Departamentos
        $adminDep = Departamento::firstOrCreate(['codigo' => 'ADMIN'], [
            'nombre'      => 'Administración',
            'descripcion' => 'Área de administración general',
            'activo'      => true,
        ]);

        Departamento::firstOrCreate(['codigo' => 'PREVENCION'], [
            'nombre'      => 'Prevención de Riesgos',
            'descripcion' => 'Seguridad y Salud Ocupacional',
            'activo'      => true,
        ]);

        Departamento::firstOrCreate(['codigo' => 'RRHH'], [
            'nombre'      => 'Recursos Humanos',
            'descripcion' => 'Gestión de personas',
            'activo'      => true,
        ]);

        Departamento::firstOrCreate(['codigo' => 'OPERACIONES'], [
            'nombre'      => 'Operaciones',
            'descripcion' => 'Operaciones y producción',
            'activo'      => true,
        ]);

        // Usuario Admin
        User::firstOrCreate(['email' => 'admin@saep.cl'], [
            'name'            => 'Administrador SAEP',
            'rut'             => '11.111.111-1',
            'departamento_id' => $adminDep->id,
            'rol_id'          => $superAdmin->id,
            'password'        => Hash::make('Saep2026!'),
            'activo'          => true,
        ]);

        // Asignar todos los módulos al rol SUPER_ADMIN
        $modulos = Modulo::all();
        foreach ($modulos as $modulo) {
            DB::table('rol_modulo')->updateOrInsert(
                ['rol_id' => $superAdmin->id, 'modulo_id' => $modulo->id],
                ['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true, 'puede_eliminar' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
