@extends('layouts.app')

@section('title', 'Documentación — Gestión de Usuarios')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-people-fill" style="color:var(--primary-color);"></i>
                Gestión de Usuarios
            </h2>
            <p class="page-subheading">Guía de administración de usuarios del sistema</p>
        </div>
        <a href="{{ route('documentacion.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Documentación
        </a>
    </div>

    {{-- Navegación interna --}}
    <div class="glass-card" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
        <strong style="font-size:.85rem;color:var(--text-muted);display:block;margin-bottom:.5rem;">Contenido</strong>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
            <a href="#descripcion" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Descripción</a>
            <a href="#campos" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Campos de Usuario</a>
            <a href="#crear" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Crear Usuario</a>
            <a href="#editar" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Editar / Desactivar</a>
            <a href="#filtros" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Filtros y Búsqueda</a>
            <a href="#talana" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Formato Talana</a>
        </div>
    </div>

    {{-- 1. Descripción --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="descripcion">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">1</span>
                Descripción General
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            El módulo de <strong>Gestión de Usuarios</strong> permite administrar todos los trabajadores y usuarios de la plataforma. 
            Los datos están alineados al formato de exportación de <strong>Talana</strong> (sistema de RRHH), incluyendo RUT, 
            cargo, departamento, centro de costo, tipo de empresa y datos personales.
        </p>
        <p style="line-height:1.6;margin:0;">
            Solo los usuarios con rol <strong>Super Admin</strong> o <strong>Prevencionista</strong> pueden acceder a este módulo.
        </p>
    </div>

    {{-- 2. Campos --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="campos">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">2</span>
                Campos de Usuario
            </h3>
        </div>

        <p style="line-height:1.6;margin:0 0 1rem;font-size:.9rem;">
            El formulario está organizado en tres secciones:
        </p>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid var(--primary-color);">
                <strong><i class="bi bi-person-fill" style="color:var(--primary-color);"></i> Datos Personales</strong>
                <p style="font-size:.85rem;color:var(--text-muted);margin:.5rem 0 0;line-height:1.6;">
                    Nombre*, Apellido Paterno, Apellido Materno, RUT, Email*, Teléfono, Fecha de Nacimiento, 
                    Nacionalidad, Sexo, Estado Civil.
                </p>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid #f59e0b;">
                <strong><i class="bi bi-briefcase-fill" style="color:#f59e0b;"></i> Datos Laborales</strong>
                <p style="font-size:.85rem;color:var(--text-muted);margin:.5rem 0 0;line-height:1.6;">
                    Cargo, Departamento, Centro de Costo, Tipo de Nómina (Normal / Transitorio), 
                    Razón Social, Fecha de Ingreso.
                </p>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid #10b981;">
                <strong><i class="bi bi-shield-lock-fill" style="color:#10b981;"></i> Acceso al Sistema</strong>
                <p style="font-size:.85rem;color:var(--text-muted);margin:.5rem 0 0;line-height:1.6;">
                    Rol*, Estado (Activo/Inactivo), Contraseña*.
                </p>
            </div>
        </div>

        <p style="font-size:.8rem;color:var(--text-muted);margin:1rem 0 0;">
            * Campos obligatorios. Los demás son opcionales y se pueden completar después o vía importación CSV.
        </p>
    </div>

    {{-- 3. Crear --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="crear">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">3</span>
                Crear un Usuario
            </h3>
        </div>
        <ol style="margin:0;padding-left:1.5rem;font-size:.9rem;color:var(--text-muted);line-height:2;">
            <li>Ir a <strong>Usuarios</strong> en el menú lateral.</li>
            <li>Clic en <strong>"Nuevo Usuario"</strong>.</li>
            <li>Completar al menos: <strong>Nombre</strong>, <strong>Email</strong>, <strong>Rol</strong> y <strong>Contraseña</strong>.</li>
            <li>Opcionalmente llenar los datos laborales (cargo, departamento, centro de costo, etc.).</li>
            <li>Clic en <strong>"Crear Usuario"</strong>.</li>
        </ol>
    </div>

    {{-- 4. Editar / Desactivar --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="editar">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">4</span>
                Editar y Desactivar
            </h3>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;">
                <h4 style="margin:0 0 .5rem;font-size:.95rem;color:var(--primary-color);">
                    <i class="bi bi-pencil-fill"></i> Editar
                </h4>
                <ul style="margin:0;padding-left:1.25rem;font-size:.85rem;color:var(--text-muted);line-height:1.6;">
                    <li>Clic en el ícono de lápiz en la tabla de usuarios.</li>
                    <li>Se pueden modificar todos los campos.</li>
                    <li>La contraseña se deja en blanco si no se quiere cambiar.</li>
                </ul>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;">
                <h4 style="margin:0 0 .5rem;font-size:.95rem;color:#ef4444;">
                    <i class="bi bi-person-x-fill"></i> Desactivar
                </h4>
                <ul style="margin:0;padding-left:1.25rem;font-size:.85rem;color:var(--text-muted);line-height:1.6;">
                    <li>Los usuarios no se eliminan, se <strong>desactivan</strong>.</li>
                    <li>Un usuario desactivado no puede iniciar sesión.</li>
                    <li>No puedes desactivar tu propio usuario.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- 5. Filtros --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="filtros">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">5</span>
                Filtros y Búsqueda
            </h3>
        </div>
        <p style="font-size:.9rem;color:var(--text-muted);margin:0 0 .75rem;line-height:1.5;">
            El listado de usuarios permite filtrar por:
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
            <span class="badge secondary" style="font-size:.8rem;">Buscar por nombre, email o RUT</span>
            <span class="badge secondary" style="font-size:.8rem;">Rol</span>
            <span class="badge secondary" style="font-size:.8rem;">Departamento</span>
            <span class="badge secondary" style="font-size:.8rem;">Cargo</span>
            <span class="badge secondary" style="font-size:.8rem;">Estado (Activo/Inactivo)</span>
        </div>
    </div>

    {{-- 6. Formato Talana --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="talana">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">6</span>
                Integración con Talana
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;font-size:.9rem;">
            Los campos de usuario están diseñados para coincidir con el formato de descarga de <strong>Talana</strong>.
            Para importar usuarios masivamente desde un archivo CSV, usa el módulo 
            <a href="{{ route('documentacion.show', 'importacion') }}" style="color:var(--primary-color);font-weight:600;">Importación de Datos</a>.
        </p>
        <p style="font-size:.85rem;color:var(--text-muted);margin:0;line-height:1.5;">
            La tabla del listado muestra las columnas principales del formato Talana: 
            <strong>Usuario, RUT, Cargo, Departamento, Centro de Costo, Razón Social, Rol y Estado</strong>.
        </p>
    </div>

    <div style="text-align:center;color:var(--text-muted);font-size:.8rem;padding:1rem 0;">
        Documentación v{{ $meta['version'] }} — Módulo {{ $meta['titulo'] }} — Plataforma SAEP
    </div>
</div>
@endsection
