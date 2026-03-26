@extends('layouts.app')

@section('title', 'Documentación — Ley Karin')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-shield-exclamation" style="color:var(--primary-color);"></i>
                Ley Karin — Módulo de Denuncias
            </h2>
            <p class="page-subheading">Ley 21.643 · Acoso laboral, sexual y violencia en el trabajo</p>
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
            <a href="#tipos" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Tipos de Denuncia</a>
            <a href="#estados" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Estados</a>
            <a href="#canales" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Canales de Denuncia</a>
            <a href="#crear" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Registrar Denuncia</a>
            <a href="#investigacion" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Investigación</a>
            <a href="#plazos" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Plazos Legales</a>
            <a href="#roles" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Roles y Permisos</a>
            <a href="#confidencialidad" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Confidencialidad</a>
            <a href="#legal" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Marco Legal</a>
        </div>
    </div>

    {{-- 1. Descripción General --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="descripcion">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">1</span>
                Descripción General
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            El módulo <strong>Ley Karin</strong> implementa el canal de denuncias exigido por la <strong>Ley 21.643</strong> (vigente desde el 1 de agosto de 2024),
            que modifica el Código del Trabajo en materia de prevención, investigación y sanción del acoso laboral, sexual y la violencia en el trabajo.
        </p>
        <p style="line-height:1.6;margin:0 0 1rem;">
            El sistema permite registrar denuncias, asignar investigadores, controlar plazos legales y mantener
            un registro auditable de cada caso, respetando la <strong>confidencialidad</strong> que exige la ley.
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.75rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-shield-lock-fill" style="font-size:1.5rem;color:var(--primary-color);display:block;margin-bottom:.25rem;"></i>
                <span style="font-size:.85rem;font-weight:600;">Confidencialidad</span>
                <span style="display:block;font-size:.75rem;color:var(--text-muted);">Protección de identidad</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-incognito" style="font-size:1.5rem;color:#10b981;display:block;margin-bottom:.25rem;"></i>
                <span style="font-size:.85rem;font-weight:600;">Denuncia Anónima</span>
                <span style="display:block;font-size:.75rem;color:var(--text-muted);">Sin identificación obligatoria</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-calendar-check-fill" style="font-size:1.5rem;color:#f59e0b;display:block;margin-bottom:.25rem;"></i>
                <span style="font-size:.85rem;font-weight:600;">Control de Plazos</span>
                <span style="display:block;font-size:.75rem;color:var(--text-muted);">30 días hábiles máximo</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-folder2-open" style="font-size:1.5rem;color:#ef4444;display:block;margin-bottom:.25rem;"></i>
                <span style="font-size:.85rem;font-weight:600;">Folio Automático</span>
                <span style="display:block;font-size:.75rem;color:var(--text-muted);">Seguimiento LK-YYYY-NNNN</span>
            </div>
        </div>
    </div>

    {{-- 2. Tipos de Denuncia --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="tipos">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">2</span>
                Tipos de Denuncia
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">La ley establece tres conductas principales que deben ser prevenidas e investigadas:</p>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th>Clasificación</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge danger">Acoso Sexual</span></td>
                        <td><code>ACOSO_SEXUAL</code></td>
                        <td>Requerimientos de carácter sexual no consentidos que amenacen o perjudiquen la situación laboral u oportunidades del afectado.</td>
                        <td>Art. 2° inc. 2° CT</td>
                    </tr>
                    <tr>
                        <td><span class="badge warning">Acoso Laboral</span></td>
                        <td><code>ACOSO_LABORAL</code></td>
                        <td>Conducta de agresión u hostigamiento ejercida por el empleador o trabajadores, de forma reiterada, que menoscabe, maltrate o humille al trabajador.</td>
                        <td>Art. 2° inc. 3° CT</td>
                    </tr>
                    <tr>
                        <td><span class="badge danger">Violencia en el Trabajo</span></td>
                        <td><code>VIOLENCIA_EN_TRABAJO</code></td>
                        <td>Conductas ejercidas por terceros ajenos a la relación laboral (clientes, proveedores, usuarios) que afecten a los trabajadores durante la prestación de servicios.</td>
                        <td>Art. 2° inc. 4° CT</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 3. Estados del Expediente --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="estados">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">3</span>
                Estados del Expediente
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">Cada denuncia avanza por un ciclo de vida definido:</p>

        {{-- Flujo visual --}}
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:1.25rem;padding:1rem;background:var(--surface-bg);border-radius:.75rem;">
            <span class="badge info" style="font-size:.85rem;padding:.5rem .75rem;">Recibida</span>
            <i class="bi bi-arrow-right" style="color:var(--text-muted);"></i>
            <span class="badge warning" style="font-size:.85rem;padding:.5rem .75rem;">En Investigación</span>
            <i class="bi bi-arrow-right" style="color:var(--text-muted);"></i>
            <span class="badge success" style="font-size:.85rem;padding:.5rem .75rem;">Resuelta</span>
            <span style="margin-left:.75rem;color:var(--text-muted);font-size:.85rem;">ó</span>
            <span class="badge warning" style="font-size:.85rem;padding:.5rem .75rem;">Derivada a la DT</span>
            <span style="color:var(--text-muted);font-size:.85rem;">/</span>
            <span class="badge secondary" style="font-size:.85rem;padding:.5rem .75rem;">Archivada</span>
        </div>

        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Código</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge info">Recibida</span></td>
                        <td><code>RECIBIDA</code></td>
                        <td>Denuncia ingresada al sistema. Se asigna folio automático. Estado inicial por defecto.</td>
                    </tr>
                    <tr>
                        <td><span class="badge warning">En Investigación</span></td>
                        <td><code>EN_INVESTIGACION</code></td>
                        <td>Se asignó investigador y se está realizando la investigación formal. Comienza a correr el plazo.</td>
                    </tr>
                    <tr>
                        <td><span class="badge success">Resuelta</span></td>
                        <td><code>RESUELTA</code></td>
                        <td>La investigación concluyó y se emitió un resultado con medidas adoptadas.</td>
                    </tr>
                    <tr>
                        <td><span class="badge warning">Derivada a la DT</span></td>
                        <td><code>DERIVADA_DT</code></td>
                        <td>El caso fue derivado a la Dirección del Trabajo para investigación externa.</td>
                    </tr>
                    <tr>
                        <td><span class="badge secondary">Archivada</span></td>
                        <td><code>ARCHIVADA</code></td>
                        <td>El caso fue cerrado o archivado sin mayor tramitación.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 4. Canales de Denuncia --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="canales">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">4</span>
                Canales de Denuncia
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            La ley exige que el empleador disponga de canales accesibles para recibir denuncias. El sistema registra por qué vía fue recibida:
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-person-fill" style="font-size:1.25rem;color:var(--primary-color);"></i>
                <span style="display:block;font-size:.85rem;font-weight:600;">Presencial</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-envelope-fill" style="font-size:1.25rem;color:#6366f1;"></i>
                <span style="display:block;font-size:.85rem;font-weight:600;">Correo Electrónico</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-pencil-square" style="font-size:1.25rem;color:#f59e0b;"></i>
                <span style="display:block;font-size:.85rem;font-weight:600;">Escrito</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-globe" style="font-size:1.25rem;color:#10b981;"></i>
                <span style="display:block;font-size:.85rem;font-weight:600;">Formulario Web</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-telephone-fill" style="font-size:1.25rem;color:#0ea5e9;"></i>
                <span style="display:block;font-size:.85rem;font-weight:600;">Teléfono</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-incognito" style="font-size:1.25rem;color:#ef4444;"></i>
                <span style="display:block;font-size:.85rem;font-weight:600;">Anónimo</span>
            </div>
        </div>
    </div>

    {{-- 5. Registrar una Denuncia --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="crear">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">5</span>
                Registrar una Denuncia
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            Para registrar una nueva denuncia, acceda a <strong>Ley Karin → Nueva Denuncia</strong>. El formulario se organiza en las siguientes secciones:
        </p>
        <div style="display:grid;gap:1rem;">
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <span style="background:#f0f4ff;color:var(--primary-color);width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0;">1</span>
                <div>
                    <strong style="display:block;margin-bottom:.25rem;">Datos de la Denuncia</strong>
                    <span style="font-size:.9rem;color:var(--text-muted);">Fecha, tipo de denuncia, centro de costo y canal de recepción. Todos los campos obligatorios excepto el canal.</span>
                </div>
            </div>
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <span style="background:#f0f4ff;color:var(--primary-color);width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0;">2</span>
                <div>
                    <strong style="display:block;margin-bottom:.25rem;">Denunciante</strong>
                    <span style="font-size:.9rem;color:var(--text-muted);">Puede ser <strong>anónima</strong> (desmarca campos de identidad) o identificada. Se puede seleccionar un usuario interno o ingresar datos manualmente (para externos).</span>
                </div>
            </div>
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <span style="background:#f0f4ff;color:var(--primary-color);width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0;">3</span>
                <div>
                    <strong style="display:block;margin-bottom:.25rem;">Denunciado</strong>
                    <span style="font-size:.9rem;color:var(--text-muted);">Nombre y cargo de la persona denunciada. Campos opcionales que se pueden completar posteriormente.</span>
                </div>
            </div>
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <span style="background:#f0f4ff;color:var(--primary-color);width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0;">4</span>
                <div>
                    <strong style="display:block;margin-bottom:.25rem;">Descripción de los Hechos</strong>
                    <span style="font-size:.9rem;color:var(--text-muted);">Relato detallado de los hechos denunciados. Campo obligatorio y fundamental para la investigación.</span>
                </div>
            </div>
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <span style="background:#f0f4ff;color:var(--primary-color);width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0;">5</span>
                <div>
                    <strong style="display:block;margin-bottom:.25rem;">Investigación</strong>
                    <span style="font-size:.9rem;color:var(--text-muted);">Asignar investigador y definir plazo legal. El plazo se calcula como 30 días hábiles desde la fecha de denuncia.</span>
                </div>
            </div>
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <span style="background:#f0f4ff;color:var(--primary-color);width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0;">6</span>
                <div>
                    <strong style="display:block;margin-bottom:.25rem;">Confidencialidad</strong>
                    <span style="font-size:.9rem;color:var(--text-muted);">Marcar como confidencial restringe la visibilidad del expediente. Activado por defecto según lo que exige la ley.</span>
                </div>
            </div>
        </div>
        <div style="margin-top:1.25rem;padding:1rem;background:rgba(16,185,129,0.08);border-left:3px solid #10b981;border-radius:.5rem;">
            <strong style="display:block;margin-bottom:.25rem;">
                <i class="bi bi-info-circle-fill" style="color:#10b981;"></i> Folio Automático
            </strong>
            <span style="font-size:.9rem;color:var(--text-muted);">
                Al guardar, el sistema genera automáticamente un folio con formato <code>LK-2026-0001</code> que se muestra como confirmación.
            </span>
        </div>
    </div>

    {{-- 6. Investigación --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="investigacion">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">6</span>
                Proceso de Investigación
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            Una vez creada la denuncia, el flujo de investigación es el siguiente:
        </p>
        <div style="display:grid;gap:1rem;">
            <div style="padding:1rem;background:var(--surface-bg);border-radius:.75rem;border-left:3px solid #3b82f6;">
                <strong style="color:#3b82f6;">1. Recepción</strong>
                <p style="font-size:.9rem;margin:.25rem 0 0;color:var(--text-muted);">
                    La denuncia se registra con estado <span class="badge info">Recibida</span>. Se genera folio automático y se activa la confidencialidad.
                </p>
            </div>
            <div style="padding:1rem;background:var(--surface-bg);border-radius:.75rem;border-left:3px solid #f59e0b;">
                <strong style="color:#f59e0b;">2. Asignación de Investigador</strong>
                <p style="font-size:.9rem;margin:.25rem 0 0;color:var(--text-muted);">
                    Se asigna un investigador interno (o se deriva a la DT). Se establece el plazo legal de 30 días hábiles. El estado cambia a <span class="badge warning">En Investigación</span>.
                </p>
            </div>
            <div style="padding:1rem;background:var(--surface-bg);border-radius:.75rem;border-left:3px solid #8b5cf6;">
                <strong style="color:#8b5cf6;">3. Medidas Cautelares</strong>
                <p style="font-size:.9rem;margin:.25rem 0 0;color:var(--text-muted);">
                    Si corresponde, se registran medidas de protección para el denunciante (separación de funciones, cambio de lugar de trabajo, etc.).
                </p>
            </div>
            <div style="padding:1rem;background:var(--surface-bg);border-radius:.75rem;border-left:3px solid #10b981;">
                <strong style="color:#10b981;">4. Resultado y Resolución</strong>
                <p style="font-size:.9rem;margin:.25rem 0 0;color:var(--text-muted);">
                    Se registra el resultado de la investigación, las medidas adoptadas y la fecha de resolución. El estado pasa a <span class="badge success">Resuelta</span>.
                </p>
            </div>
        </div>
    </div>

    {{-- 7. Plazos Legales --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="plazos">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">7</span>
                Plazos Legales
            </h3>
        </div>
        <div style="padding:1rem;background:rgba(239,68,68,0.06);border-left:3px solid #ef4444;border-radius:.5rem;margin-bottom:1rem;">
            <strong style="color:#dc2626;"><i class="bi bi-exclamation-triangle-fill"></i> Plazo crítico de 30 días hábiles</strong>
            <p style="font-size:.9rem;margin:.5rem 0 0;color:var(--text-muted);">
                La investigación interna debe concluir en un plazo máximo de <strong>30 días hábiles</strong> contados desde la recepción de la denuncia. 
                De no concluir en plazo, el caso debe ser remitido a la Dirección del Trabajo.
            </p>
        </div>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Plazo</th>
                        <th>Acción</th>
                        <th>Consecuencia</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>3 días hábiles</strong></td>
                        <td>El empleador debe adoptar medidas de resguardo (medidas cautelares).</td>
                        <td>Separación de espacios, redistribución de jornada u otras medidas de protección.</td>
                    </tr>
                    <tr>
                        <td><strong>5 días hábiles</strong></td>
                        <td>Si el empleador decide no investigar internamente, debe remitir a la DT.</td>
                        <td>Derivación formal con toda la documentación.</td>
                    </tr>
                    <tr>
                        <td><strong>30 días hábiles</strong></td>
                        <td>Plazo máximo para concluir la investigación interna.</td>
                        <td>El sistema marca el plazo como <span class="badge danger">Vencido</span> si se excede.</td>
                    </tr>
                    <tr>
                        <td><strong>15 días hábiles</strong></td>
                        <td>Plazo para que el empleador aplique las medidas indicadas en el informe.</td>
                        <td>Desde la recepción del informe del investigador o de la DT.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 8. Roles y Permisos --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="roles">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">8</span>
                Roles y Permisos
            </h3>
        </div>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Acción</th>
                        <th>Super Admin</th>
                        <th>Prevencionista</th>
                        <th>Trabajador</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Ver listado de denuncias</td>
                        <td><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></td>
                        <td><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></td>
                        <td><i class="bi bi-x-circle-fill" style="color:#ef4444;"></i></td>
                    </tr>
                    <tr>
                        <td>Registrar nueva denuncia</td>
                        <td><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></td>
                        <td><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></td>
                        <td><i class="bi bi-x-circle-fill" style="color:#ef4444;"></i></td>
                    </tr>
                    <tr>
                        <td>Ver expediente completo</td>
                        <td><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></td>
                        <td><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></td>
                        <td><i class="bi bi-x-circle-fill" style="color:#ef4444;"></i></td>
                    </tr>
                    <tr>
                        <td>Editar / actualizar estado</td>
                        <td><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></td>
                        <td><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></td>
                        <td><i class="bi bi-x-circle-fill" style="color:#ef4444;"></i></td>
                    </tr>
                    <tr>
                        <td>Archivar caso</td>
                        <td><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></td>
                        <td><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></td>
                        <td><i class="bi bi-x-circle-fill" style="color:#ef4444;"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;padding:.75rem 1rem;background:var(--surface-bg);border-radius:.5rem;font-size:.85rem;color:var(--text-muted);">
            <i class="bi bi-info-circle"></i> Solo los roles <strong>SUPER_ADMIN</strong> y <strong>PREVENCIONISTA</strong> tienen acceso completo al módulo.
        </div>
    </div>

    {{-- 9. Confidencialidad --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="confidencialidad">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">9</span>
                Confidencialidad
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            La Ley 21.643 establece que el procedimiento de investigación debe ser <strong>estrictamente confidencial</strong>. 
            El sistema implementa las siguientes medidas:
        </p>
        <div style="display:grid;gap:.75rem;">
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <i class="bi bi-lock-fill" style="color:#dc2626;font-size:1.1rem;flex-shrink:0;margin-top:.15rem;"></i>
                <span style="font-size:.9rem;">Los expedientes marcados como <strong>confidenciales</strong> muestran un ícono de candado en el listado.</span>
            </div>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <i class="bi bi-incognito" style="color:#6366f1;font-size:1.1rem;flex-shrink:0;margin-top:.15rem;"></i>
                <span style="font-size:.9rem;">Las denuncias <strong>anónimas</strong> ocultan los datos del denunciante en la vista de detalle.</span>
            </div>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <i class="bi bi-shield-check" style="color:#10b981;font-size:1.1rem;flex-shrink:0;margin-top:.15rem;"></i>
                <span style="font-size:.9rem;">El acceso está restringido a <strong>Super Admin</strong> y <strong>Prevencionista</strong> exclusivamente.</span>
            </div>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <i class="bi bi-folder2-open" style="color:var(--primary-color);font-size:1.1rem;flex-shrink:0;margin-top:.15rem;"></i>
                <span style="font-size:.9rem;">Cada expediente tiene un <strong>folio único</strong> para seguimiento sin exponer datos personales.</span>
            </div>
        </div>
    </div>

    {{-- 10. Marco Legal --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="legal">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">10</span>
                Marco Legal
            </h3>
        </div>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Norma</th>
                        <th>Contenido</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Ley 21.643 (Ley Karin)</strong></td>
                        <td>Modifica el Código del Trabajo en materia de prevención, investigación y sanción del acoso laboral, sexual y violencia en el trabajo. Vigente desde el 1 de agosto de 2024.</td>
                    </tr>
                    <tr>
                        <td><strong>Art. 211-A a 211-E CT</strong></td>
                        <td>Procedimiento de investigación y sanción del acoso sexual. Plazos, obligaciones del empleador.</td>
                    </tr>
                    <tr>
                        <td><strong>Código del Trabajo Art. 2°</strong></td>
                        <td>Define acoso sexual (inc. 2°), acoso laboral (inc. 3°) y violencia en el trabajo por terceros (inc. 4°).</td>
                    </tr>
                    <tr>
                        <td><strong>D.S. 21 (Reglamento)</strong></td>
                        <td>Reglamento que regula la investigación y sanción. Establece el protocolo de prevención obligatorio.</td>
                    </tr>
                    <tr>
                        <td><strong>Ley 21.719</strong></td>
                        <td>Protección de datos personales. Los datos de las denuncias deben manejarse con especial cuidado por su carácter sensible.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;padding:.75rem 1rem;background:rgba(249,115,22,0.08);border-left:3px solid #f97316;border-radius:.5rem;font-size:.85rem;">
            <strong><i class="bi bi-exclamation-triangle-fill" style="color:#f97316;"></i> Obligaciones del empleador</strong>
            <ul style="margin:.5rem 0 0;padding-left:1.25rem;color:var(--text-muted);">
                <li>Contar con un <strong>protocolo de prevención</strong> del acoso y violencia.</li>
                <li>Informar semestralmente sobre los canales de denuncia.</li>
                <li>Implementar medidas de resguardo en 3 días hábiles.</li>
                <li>Concluir la investigación en 30 días hábiles o derivar a la DT.</li>
            </ul>
        </div>
    </div>

</div>
@endsection