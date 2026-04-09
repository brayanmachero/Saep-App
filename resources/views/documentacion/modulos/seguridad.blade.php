@extends('layouts.app')

@section('title', 'Documentación — Seguridad y Perfil de Usuario')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-shield-lock-fill" style="color:var(--primary-color);"></i>
                Seguridad y Perfil de Usuario
            </h2>
            <p class="page-subheading">Gestión de perfil, contraseñas, notificaciones y auditoría de datos</p>
        </div>
        <a href="{{ route('documentacion.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Documentación
        </a>
    </div>

    {{-- Navegación interna --}}
    <div class="glass-card" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
        <strong style="font-size:.85rem;color:var(--text-muted);display:block;margin-bottom:.5rem;">Contenido</strong>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
            <a href="#perfil" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Mi Perfil</a>
            <a href="#foto" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Foto de Perfil</a>
            <a href="#password" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Cambio de Contraseña</a>
            <a href="#provisoria" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Contraseñas Provisorias</a>
            <a href="#reset" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Recuperar Contraseña</a>
            <a href="#notificaciones" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Notificaciones</a>
            <a href="#soft-deletes" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Eliminación Segura</a>
            <a href="#seguridad" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Políticas de Seguridad</a>
        </div>
    </div>

    {{-- 1. Mi Perfil --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="perfil">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">1</span>
                Mi Perfil
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            Cada usuario tiene acceso a su propia página de perfil donde puede visualizar y gestionar su información personal.
            Se accede desde el <strong>ícono de perfil</strong> en la barra superior o haciendo clic en su nombre en la barra lateral.
        </p>
        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid var(--primary-color);">
                <strong><i class="bi bi-eye-fill" style="color:var(--primary-color);"></i> Información visible (solo lectura)</strong>
                <p style="font-size:.85rem;color:var(--text-muted);margin:.5rem 0 0;line-height:1.6;">
                    Nombre completo, RUT, correo electrónico, rol, departamento, cargo, centro de costo,
                    fecha de ingreso, tipo de nómina y último acceso. Estos campos solo pueden ser modificados por un administrador.
                </p>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid #10b981;">
                <strong><i class="bi bi-pencil-fill" style="color:#10b981;"></i> Información editable</strong>
                <p style="font-size:.85rem;color:var(--text-muted);margin:.5rem 0 0;line-height:1.6;">
                    <strong>Teléfono:</strong> El usuario puede actualizar su número de contacto.<br>
                    <strong>Foto de perfil:</strong> Se puede subir, cambiar o eliminar una imagen de perfil.<br>
                    <strong>Contraseña:</strong> El usuario puede cambiar su contraseña de acceso.
                </p>
            </div>
        </div>
    </div>

    {{-- 2. Foto de Perfil --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="foto">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">2</span>
                Foto de Perfil
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            La foto de perfil se muestra en la barra lateral, el header y en toda interacción dentro del sistema.
        </p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                <thead>
                    <tr style="background:var(--surface-bg);text-align:left;">
                        <th style="padding:.6rem .75rem;font-weight:600;color:var(--text-muted);border-bottom:2px solid var(--border-color);">Característica</th>
                        <th style="padding:.6rem .75rem;font-weight:600;color:var(--text-muted);border-bottom:2px solid var(--border-color);">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Formatos aceptados</td><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">JPG, JPEG, PNG, WebP</td></tr>
                    <tr><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Tamaño máximo</td><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">2 MB</td></tr>
                    <tr><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Almacenamiento</td><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">storage/app/public/fotos_perfil/</td></tr>
                    <tr><td style="padding:.6rem .75rem;">Avatar por defecto</td><td style="padding:.6rem .75rem;">Iniciales del nombre sobre fondo degradado navy</td></tr>
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;background:#FEF9F0;border:1px solid #fde68a;border-radius:.5rem;padding:.75rem 1rem;">
            <p style="margin:0;font-size:.85rem;color:#92400e;">
                <i class="bi bi-info-circle-fill" style="color:#f59e0b;"></i>
                Al subir una nueva foto, la anterior se elimina automáticamente del servidor para optimizar el almacenamiento.
            </p>
        </div>
    </div>

    {{-- 3. Cambio de Contraseña --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="password">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">3</span>
                Cambio de Contraseña
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            Desde la página de perfil, el usuario puede cambiar su contraseña ingresando la contraseña actual y la nueva.
        </p>
        <div style="background:var(--surface-bg);border-radius:.5rem;padding:1.25rem;margin-bottom:1rem;">
            <strong style="display:block;margin-bottom:.75rem;">Requisitos de la nueva contraseña:</strong>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
                <div style="display:flex;align-items:center;gap:.35rem;font-size:.85rem;">
                    <i class="bi bi-check-circle-fill" style="color:#10b981;"></i> Mínimo 8 caracteres
                </div>
                <div style="display:flex;align-items:center;gap:.35rem;font-size:.85rem;">
                    <i class="bi bi-check-circle-fill" style="color:#10b981;"></i> Al menos una letra mayúscula
                </div>
                <div style="display:flex;align-items:center;gap:.35rem;font-size:.85rem;">
                    <i class="bi bi-check-circle-fill" style="color:#10b981;"></i> Al menos una letra minúscula
                </div>
                <div style="display:flex;align-items:center;gap:.35rem;font-size:.85rem;">
                    <i class="bi bi-check-circle-fill" style="color:#10b981;"></i> Al menos un número
                </div>
            </div>
        </div>
        <p style="font-size:.85rem;color:var(--text-muted);line-height:1.6;">
            La validación se realiza en tiempo real con indicadores visuales mientras el usuario escribe.
            Al completar el cambio, la sesión se mantiene activa y se muestra un mensaje de confirmación.
        </p>
    </div>

    {{-- 4. Contraseñas Provisorias --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="provisoria">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:#f59e0b;color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">4</span>
                Contraseñas Provisorias y Primer Inicio de Sesión
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            Al crear un nuevo usuario, el sistema genera automáticamente una <strong>contraseña provisoria aleatoria</strong> 
            (3 letras mayúsculas + 3 dígitos + 3 caracteres alfanuméricos) y la envía por correo electrónico.
        </p>
        <div style="display:flex;flex-direction:column;gap:.75rem;">
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <div style="width:28px;height:28px;border-radius:50%;background:rgba(15,27,76,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span style="font-weight:700;font-size:.8rem;color:var(--primary-color);">1</span>
                </div>
                <p style="margin:0;font-size:.9rem;line-height:1.5;">El administrador crea el usuario (no requiere ingresar contraseña manualmente).</p>
            </div>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <div style="width:28px;height:28px;border-radius:50%;background:rgba(15,27,76,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span style="font-weight:700;font-size:.8rem;color:var(--primary-color);">2</span>
                </div>
                <p style="margin:0;font-size:.9rem;line-height:1.5;">El usuario recibe un correo formal de bienvenida con sus credenciales (correo + contraseña provisoria).</p>
            </div>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <div style="width:28px;height:28px;border-radius:50%;background:rgba(15,27,76,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span style="font-weight:700;font-size:.8rem;color:var(--primary-color);">3</span>
                </div>
                <p style="margin:0;font-size:.9rem;line-height:1.5;">Al iniciar sesión por primera vez, el sistema <strong>redirige obligatoriamente</strong> a la página de perfil con un aviso para cambiar la contraseña.</p>
            </div>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <div style="width:28px;height:28px;border-radius:50%;background:rgba(15,27,76,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span style="font-weight:700;font-size:.8rem;color:var(--primary-color);">4</span>
                </div>
                <p style="margin:0;font-size:.9rem;line-height:1.5;">Hasta que no cambie la contraseña, no podrá navegar a ninguna otra sección del sistema.</p>
            </div>
        </div>
        <div style="margin-top:1rem;background:#fef2f2;border:1px solid #fecaca;border-radius:.5rem;padding:.75rem 1rem;">
            <p style="margin:0;font-size:.85rem;color:#dc2626;">
                <i class="bi bi-exclamation-triangle-fill" style="color:#ef4444;"></i>
                <strong>Seguridad:</strong> El middleware <code>force.password</code> bloquea el acceso a todas las rutas excepto el perfil,
                cambio de contraseña y cierre de sesión mientras la contraseña provisoria esté activa.
            </p>
        </div>
    </div>

    {{-- 5. Recuperar Contraseña --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="reset">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">5</span>
                Recuperación de Contraseña
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            Si un usuario olvida su contraseña, puede restablecerla desde la pantalla de inicio de sesión
            haciendo clic en <strong>"¿Olvidaste tu contraseña?"</strong>.
        </p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                <thead>
                    <tr style="background:var(--surface-bg);text-align:left;">
                        <th style="padding:.6rem .75rem;font-weight:600;color:var(--text-muted);border-bottom:2px solid var(--border-color);">Parámetro</th>
                        <th style="padding:.6rem .75rem;font-weight:600;color:var(--text-muted);border-bottom:2px solid var(--border-color);">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Expiración del enlace</td><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">60 minutos</td></tr>
                    <tr><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Uso del token</td><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Único (se elimina al usarse)</td></tr>
                    <tr><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Protección anti-fuerza bruta</td><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Máximo 3 solicitudes por minuto</td></tr>
                    <tr><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Almacenamiento del token</td><td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Hash bcrypt en tabla password_reset_tokens</td></tr>
                    <tr><td style="padding:.6rem .75rem;">Revelación de información</td><td style="padding:.6rem .75rem;">No revela si el correo existe en el sistema</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 6. Notificaciones --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="notificaciones">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:#3b82f6;color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">6</span>
                Sistema de Notificaciones In-App
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            El ícono de campana (<i class="bi bi-bell-fill" style="color:var(--primary-color);"></i>) en la barra superior 
            muestra las notificaciones no leídas del usuario. El sistema utiliza las notificaciones nativas de Laravel
            almacenadas en base de datos.
        </p>
        <div style="display:flex;flex-direction:column;gap:.75rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid #3b82f6;">
                <strong>Características</strong>
                <ul style="margin:.5rem 0 0;padding-left:1.25rem;font-size:.85rem;line-height:1.8;color:var(--text-muted);">
                    <li>Badge con conteo de notificaciones no leídas</li>
                    <li>Dropdown con las últimas 20 notificaciones</li>
                    <li>Tipos visuales: <span style="color:#3b82f6;">info</span>, <span style="color:#10b981;">success</span>, <span style="color:#f59e0b;">warning</span>, <span style="color:#ef4444;">danger</span></li>
                    <li>Clic en notificación: marca como leída y redirige a la URL asociada</li>
                    <li>Botón "Marcar todas" para limpiar todas las notificaciones</li>
                    <li>Actualización automática cada 60 segundos</li>
                    <li>Formato de tiempo relativo (Ahora, 5 min, 2 h, 1 d)</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- 7. Eliminación Segura --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="soft-deletes">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:#10b981;color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">7</span>
                Eliminación Segura (Soft Deletes)
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            Los registros críticos del sistema no se eliminan permanentemente de la base de datos. En su lugar, se marcan
            con una fecha de eliminación (<code>deleted_at</code>) y dejan de aparecer en consultas normales, permitiendo
            su recuperación si es necesario.
        </p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                <thead>
                    <tr style="background:var(--surface-bg);text-align:left;">
                        <th style="padding:.6rem .75rem;font-weight:600;color:var(--text-muted);border-bottom:2px solid var(--border-color);">Modelo / Tabla</th>
                        <th style="padding:.6rem .75rem;font-weight:600;color:var(--text-muted);border-bottom:2px solid var(--border-color);">Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);"><code>User</code> / users</td>
                        <td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Usuarios del sistema</td>
                    </tr>
                    <tr>
                        <td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);"><code>LeyKarin</code> / ley_karin</td>
                        <td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Casos de Ley Karin (denuncias por acoso)</td>
                    </tr>
                    <tr>
                        <td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);"><code>Respuesta</code> / respuestas</td>
                        <td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Respuestas a formularios (solicitudes)</td>
                    </tr>
                    <tr>
                        <td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);"><code>Charla</code> / charlas</td>
                        <td style="padding:.6rem .75rem;border-bottom:1px solid var(--border-color);">Charlas de seguridad y capacitaciones</td>
                    </tr>
                    <tr>
                        <td style="padding:.6rem .75rem;"><code>AccidenteSst</code> / accidentes_sst</td>
                        <td style="padding:.6rem .75rem;">Registros de accidentes laborales</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 8. Políticas de Seguridad --}}
    <div class="glass-card" id="seguridad">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:#ef4444;color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">8</span>
                Políticas de Seguridad Implementadas
            </h3>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;">
                <strong style="display:flex;align-items:center;gap:.35rem;margin-bottom:.5rem;">
                    <i class="bi bi-shield-fill-check" style="color:#10b981;"></i> Protección de Login
                </strong>
                <ul style="margin:0;padding-left:1.25rem;font-size:.85rem;line-height:1.8;color:var(--text-muted);">
                    <li>Límite de 5 intentos por minuto</li>
                    <li>Página 429 personalizada con cuenta regresiva</li>
                    <li>Regeneración de sesión al autenticarse</li>
                    <li>Invalidación de sesión al cerrar sesión</li>
                </ul>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;">
                <strong style="display:flex;align-items:center;gap:.35rem;margin-bottom:.5rem;">
                    <i class="bi bi-key-fill" style="color:#f59e0b;"></i> Gestión de Contraseñas
                </strong>
                <ul style="margin:0;padding-left:1.25rem;font-size:.85rem;line-height:1.8;color:var(--text-muted);">
                    <li>Almacenamiento con hash bcrypt</li>
                    <li>Complejidad mínima obligatoria (mayúsculas, minúsculas, números)</li>
                    <li>Cambio forzado en primer login</li>
                    <li>Contraseñas provisorias aleatorias</li>
                </ul>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;">
                <strong style="display:flex;align-items:center;gap:.35rem;margin-bottom:.5rem;">
                    <i class="bi bi-people-fill" style="color:var(--primary-color);"></i> Control de Acceso (RBAC)
                </strong>
                <ul style="margin:0;padding-left:1.25rem;font-size:.85rem;line-height:1.8;color:var(--text-muted);">
                    <li>3 middlewares: CheckRole, CheckPermission, CheckModulo</li>
                    <li>Permisos: puede_ver, crear, editar, eliminar por módulo</li>
                    <li>Consentimiento obligatorio de datos personales</li>
                </ul>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;">
                <strong style="display:flex;align-items:center;gap:.35rem;margin-bottom:.5rem;">
                    <i class="bi bi-envelope-fill" style="color:#3b82f6;"></i> Comunicaciones
                </strong>
                <ul style="margin:0;padding-left:1.25rem;font-size:.85rem;line-height:1.8;color:var(--text-muted);">
                    <li>Email de bienvenida con credenciales provisorias</li>
                    <li>Email de restablecimiento con token hasheado</li>
                    <li>Templates profesionales con marca SAEP</li>
                    <li>Enviados vía Resend (notificaciones@bmachero.com)</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Changelog --}}
    <div class="glass-card" style="margin-top:1.5rem;">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1rem;">
            <h3 style="margin:0;font-size:1rem;display:flex;align-items:center;gap:.5rem;">
                <i class="bi bi-clock-history" style="color:var(--text-muted);"></i> Changelog
            </h3>
        </div>
        <div style="font-size:.85rem;line-height:1.8;color:var(--text-muted);">
            <p style="margin:0;"><strong>v1.0</strong> — 09/04/2026</p>
            <ul style="margin:.25rem 0 0;padding-left:1.5rem;">
                <li>Página de perfil con información personal, foto y cambio de contraseña</li>
                <li>Generación automática de contraseñas provisorias al crear usuarios</li>
                <li>Middleware force.password para obligar cambio en primer login</li>
                <li>Validación de complejidad de contraseñas (8 chars, mayúsculas, minúsculas, números)</li>
                <li>Sistema de notificaciones in-app con campana funcional y dropdown</li>
                <li>Soft Deletes en modelos críticos (User, LeyKarin, Respuesta, Charla, AccidenteSst)</li>
                <li>Recuperación de contraseña por email con token hasheado y expiración</li>
                <li>Página 429 estilizada para límite de intentos</li>
                <li>Templates de email profesionales con marca SAEP</li>
            </ul>
        </div>
    </div>

</div>
@endsection
