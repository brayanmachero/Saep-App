@extends('layouts.app')

@section('title', 'Documentación — Charlas SST')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-megaphone-fill" style="color:var(--primary-color);"></i>
                Charlas SST
            </h2>
            <p class="page-subheading">Guía completa del módulo de charlas de seguridad</p>
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
            <a href="#tipos" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Tipos de Charla</a>
            <a href="#estados" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Estados</a>
            <a href="#crear" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Crear Charla</a>
            <a href="#firmas" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Proceso de Firma</a>
            <a href="#editar" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Editar / Eliminar</a>
            <a href="#pdf" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Exportar PDF</a>
            <a href="#roles" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Roles y Permisos</a>
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
            El módulo de <strong>Charlas SST</strong> permite gestionar charlas de seguridad, capacitaciones e inducciones 
            con un sistema completo de <strong>firma electrónica</strong> para trabajadores y relatores.
        </p>
        <p style="line-height:1.6;margin:0 0 1rem;">
            Cada charla genera un <strong>acta digital</strong> que incluye el contenido impartido, las firmas de todos los 
            participantes y un registro de auditoría con hash SHA-256, geolocalización, IP y timestamp — todo conforme a la 
            <strong>Ley 19.799</strong> de firma electrónica en Chile.
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.75rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-pen-fill" style="font-size:1.5rem;color:var(--primary-color);display:block;margin-bottom:.25rem;"></i>
                <span style="font-size:.85rem;font-weight:600;">Firma Digital</span>
                <span style="display:block;font-size:.75rem;color:var(--text-muted);">Canvas táctil y mouse</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-geo-alt-fill" style="font-size:1.5rem;color:#10b981;display:block;margin-bottom:.25rem;"></i>
                <span style="font-size:.85rem;font-weight:600;">Geolocalización</span>
                <span style="display:block;font-size:.75rem;color:var(--text-muted);">Ubicación al firmar</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-hash" style="font-size:1.5rem;color:#f59e0b;display:block;margin-bottom:.25rem;"></i>
                <span style="font-size:.85rem;font-weight:600;">Hash SHA-256</span>
                <span style="display:block;font-size:.75rem;color:var(--text-muted);">Integridad del documento</span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;text-align:center;">
                <i class="bi bi-file-earmark-pdf-fill" style="font-size:1.5rem;color:#ef4444;display:block;margin-bottom:.25rem;"></i>
                <span style="font-size:.85rem;font-weight:600;">Acta PDF</span>
                <span style="display:block;font-size:.75rem;color:var(--text-muted);">Exportación oficial</span>
            </div>
        </div>
    </div>

    {{-- 2. Tipos de Charla --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="tipos">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">2</span>
                Tipos de Charla
            </h3>
        </div>
        <div class="glass-table-container">
            <table class="glass-table" style="font-size:.9rem;">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Duración típica</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge info">Charla 5 Minutos</span></td>
                        <td>Charla diaria breve de seguridad antes del inicio de la jornada laboral.</td>
                        <td>5 – 15 min</td>
                    </tr>
                    <tr>
                        <td><span class="badge success">Capacitación</span></td>
                        <td>Sesión de formación técnica sobre procedimientos, equipos o normativas.</td>
                        <td>30 – 120 min</td>
                    </tr>
                    <tr>
                        <td><span class="badge warning">Inducción</span></td>
                        <td>Charla obligatoria para trabajadores nuevos o reintegros (ODI).</td>
                        <td>60 – 240 min</td>
                    </tr>
                    <tr>
                        <td><span class="badge secondary">Charla Especial</span></td>
                        <td>Cualquier otra actividad de prevención no clasificada arriba.</td>
                        <td>Variable</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 3. Estados --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="estados">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">3</span>
                Ciclo de Vida (Estados)
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            Cada charla progresa a través de los siguientes estados. Algunos cambios son <strong>automáticos</strong> 
            basados en las firmas:
        </p>

        {{-- Diagrama visual --}}
        <div style="display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:.5rem;margin-bottom:1.5rem;padding:1rem;background:var(--surface-bg);border-radius:.75rem;">
            <div style="text-align:center;">
                <span class="badge warning" style="font-size:.85rem;padding:.4rem .75rem;">PROGRAMADA</span>
                <span style="display:block;font-size:.7rem;color:var(--text-muted);margin-top:.25rem;">Al crear</span>
            </div>
            <i class="bi bi-arrow-right" style="color:var(--text-muted);"></i>
            <div style="text-align:center;">
                <span class="badge info" style="font-size:.85rem;padding:.4rem .75rem;">EN CURSO</span>
                <span style="display:block;font-size:.7rem;color:var(--text-muted);margin-top:.25rem;">1ª firma</span>
            </div>
            <i class="bi bi-arrow-right" style="color:var(--text-muted);"></i>
            <div style="text-align:center;">
                <span class="badge success" style="font-size:.85rem;padding:.4rem .75rem;">COMPLETADA</span>
                <span style="display:block;font-size:.7rem;color:var(--text-muted);margin-top:.25rem;">100% firmas</span>
            </div>
        </div>

        <div class="glass-table-container">
            <table class="glass-table" style="font-size:.9rem;">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Significado</th>
                        <th>Transición</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge warning">Programada</span></td>
                        <td>Charla creada, esperando que comience el proceso de firmas.</td>
                        <td>Automático al crear la charla.</td>
                    </tr>
                    <tr>
                        <td><span class="badge info">En Curso</span></td>
                        <td>Al menos un asistente ya firmó. En proceso de recolección de firmas.</td>
                        <td>Automático cuando el <strong>primer asistente</strong> firma.</td>
                    </tr>
                    <tr>
                        <td><span class="badge success">Completada</span></td>
                        <td>Todos los asistentes han firmado. Se registra fecha de dictado.</td>
                        <td>Automático cuando <strong>todos los asistentes</strong> firman.</td>
                    </tr>
                    <tr>
                        <td><span class="badge danger">Cancelada</span></td>
                        <td>La charla fue anulada.</td>
                        <td>Solo por cambio manual de estado por un administrador.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 4. Cómo Crear una Charla --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="crear">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">4</span>
                Cómo Crear una Charla
            </h3>
        </div>

        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            {{-- Paso 1 --}}
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <div style="width:32px;height:32px;background:var(--primary-color);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;">1</div>
                <div>
                    <strong>Ir a Charlas → Nueva Charla</strong>
                    <p style="margin:.25rem 0 0;color:var(--text-muted);font-size:.9rem;line-height:1.5;">
                        Desde el menú lateral, accede a <strong>Charlas SST</strong> y haz clic en el botón 
                        <span class="badge info" style="font-size:.75rem;">+ Nueva Charla</span>.
                    </p>
                </div>
            </div>

            {{-- Paso 2 --}}
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <div style="width:32px;height:32px;background:var(--primary-color);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;">2</div>
                <div>
                    <strong>Completar datos de la charla</strong>
                    <p style="margin:.25rem 0 0;color:var(--text-muted);font-size:.9rem;line-height:1.5;">
                        Rellena los campos obligatorios:
                    </p>
                    <ul style="margin:.5rem 0 0;padding-left:1.5rem;font-size:.9rem;color:var(--text-muted);">
                        <li><strong>Título</strong> — Nombre descriptivo de la charla</li>
                        <li><strong>Tipo</strong> — Charla 5 min, Capacitación, Inducción o Especial</li>
                        <li><strong>Fecha y hora</strong> — Cuándo se realizará</li>
                        <li><strong>Duración</strong> — Tiempo estimado en minutos</li>
                        <li><strong>Centro de costo</strong> y <strong>Lugar</strong> — Dónde se realiza</li>
                        <li><strong>Supervisor</strong> — Persona responsable</li>
                    </ul>
                </div>
            </div>

            {{-- Paso 3 --}}
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <div style="width:32px;height:32px;background:var(--primary-color);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;">3</div>
                <div>
                    <strong>Redactar el contenido / temario</strong>
                    <p style="margin:.25rem 0 0;color:var(--text-muted);font-size:.9rem;line-height:1.5;">
                        Escribe en el campo de contenido el <strong>temario o PTS</strong> (Procedimiento de Trabajo Seguro) 
                        que los asistentes deberán leer antes de firmar. Este texto aparecerá en el acta PDF.
                    </p>
                </div>
            </div>

            {{-- Paso 4 --}}
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <div style="width:32px;height:32px;background:var(--primary-color);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;">4</div>
                <div>
                    <strong>Agregar relatores</strong>
                    <p style="margin:.25rem 0 0;color:var(--text-muted);font-size:.9rem;line-height:1.5;">
                        Añade uno o más relatores indicando su <strong>rol</strong>:
                    </p>
                    <ul style="margin:.5rem 0 0;padding-left:1.5rem;font-size:.9rem;color:var(--text-muted);">
                        <li><strong>Relator</strong> — Quien dicta la charla</li>
                        <li><strong>Supervisor CPHS</strong> — Representante del Comité Paritario</li>
                        <li><strong>Instructor</strong> — Capacitador externo o especialista</li>
                    </ul>
                </div>
            </div>

            {{-- Paso 5 --}}
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <div style="width:32px;height:32px;background:var(--primary-color);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;">5</div>
                <div>
                    <strong>Seleccionar asistentes</strong>
                    <p style="margin:.25rem 0 0;color:var(--text-muted);font-size:.9rem;line-height:1.5;">
                        Marca los trabajadores que deben asistir. Puedes usar el <strong>buscador</strong> para filtrar 
                        por nombre. El contador mostrará cuántos has seleccionado.
                    </p>
                </div>
            </div>

            {{-- Paso 6 --}}
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <div style="width:32px;height:32px;background:#10b981;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;">
                    <i class="bi bi-check-lg" style="font-size:1rem;"></i>
                </div>
                <div>
                    <strong>Guardar</strong>
                    <p style="margin:.25rem 0 0;color:var(--text-muted);font-size:.9rem;line-height:1.5;">
                        Al hacer clic en <strong>"Crear Charla"</strong>, se crea con estado <span class="badge warning" style="font-size:.75rem;">Programada</span> 
                        y queda lista para el proceso de firmas.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- 5. Proceso de Firma --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="firmas">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">5</span>
                Proceso de Firma
            </h3>
        </div>

        <p style="line-height:1.6;margin:0 0 1.25rem;">
            Existen <strong>dos flujos de firma</strong> separados: uno para asistentes (trabajadores) y otro para relatores.
        </p>

        {{-- Firma Asistente --}}
        <div style="background:var(--surface-bg);border-radius:.75rem;padding:1.25rem;margin-bottom:1rem;border-left:4px solid var(--primary-color);">
            <h4 style="margin:0 0 .75rem;font-size:1rem;">
                <i class="bi bi-person-fill" style="color:var(--primary-color);"></i> Firma del Asistente
            </h4>
            <p style="font-size:.9rem;color:var(--text-muted);margin:0 0 .75rem;line-height:1.5;">
                El asistente accede desde la vista de detalle de la charla, donde ve el botón <strong>"Firmar"</strong> 
                junto a su nombre en la tabla de asistentes.
            </p>
            <ol style="margin:0;padding-left:1.5rem;font-size:.9rem;color:var(--text-muted);line-height:1.8;">
                <li><strong>Leer el contenido completo</strong> — Debe hacer scroll hasta el final del temario. El botón de firma se activa solo cuando se ha leído todo.</li>
                <li><strong>Dibujar su firma</strong> — Usando el canvas digital (funciona con mouse en PC y dedo en celular/tablet).</li>
                <li><strong>Aceptar la declaración legal</strong> — Checkbox que confirma que leyó y entendió el contenido bajo la Ley 19.799.</li>
                <li><strong>Enviar</strong> — Se registra la firma junto con IP, geolocalización, user-agent y un hash SHA-256 único.</li>
            </ol>
        </div>

        {{-- Firma Relator --}}
        <div style="background:var(--surface-bg);border-radius:.75rem;padding:1.25rem;border-left:4px solid #7c3aed;">
            <h4 style="margin:0 0 .75rem;font-size:1rem;">
                <i class="bi bi-mortarboard-fill" style="color:#7c3aed;"></i> Firma del Relator
            </h4>
            <p style="font-size:.9rem;color:var(--text-muted);margin:0 0 .75rem;line-height:1.5;">
                El relator accede desde la vista de detalle, donde ve el botón <strong>"Firmar como Relator"</strong>.
            </p>
            <ol style="margin:0;padding-left:1.5rem;font-size:.9rem;color:var(--text-muted);line-height:1.8;">
                <li><strong>Dibujar su firma</strong> — En el canvas digital.</li>
                <li><strong>Aceptar la declaración</strong> — Confirma que dictó la charla.</li>
                <li><strong>Enviar</strong> — Se registra con los mismos datos de auditoría que los asistentes.</li>
            </ol>
            <p style="font-size:.85rem;color:var(--text-muted);margin:.75rem 0 0;">
                <i class="bi bi-info-circle-fill" style="color:#7c3aed;"></i>
                <strong>Diferencia:</strong> El relator no necesita leer el contenido (scroll obligatorio) porque es quien lo dictó.
            </p>
        </div>

        {{-- Transiciones automáticas --}}
        <div style="margin-top:1.25rem;padding:1rem;background:rgba(16,185,129,0.08);border-radius:.5rem;border:1px solid rgba(16,185,129,0.2);">
            <strong style="font-size:.9rem;color:#10b981;">
                <i class="bi bi-lightning-fill"></i> Transiciones automáticas
            </strong>
            <ul style="margin:.5rem 0 0;padding-left:1.5rem;font-size:.85rem;color:var(--text-muted);line-height:1.6;">
                <li>Cuando el <strong>primer asistente</strong> firma → la charla pasa de <span class="badge warning" style="font-size:.7rem;">Programada</span> a <span class="badge info" style="font-size:.7rem;">En Curso</span></li>
                <li>Cuando el <strong>último asistente</strong> firma → la charla pasa a <span class="badge success" style="font-size:.7rem;">Completada</span> y se registra la fecha de dictado</li>
            </ul>
        </div>
    </div>

    {{-- 6. Editar / Eliminar --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="editar">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">6</span>
                Editar y Eliminar
            </h3>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;">
                <h4 style="margin:0 0 .5rem;font-size:.95rem;color:var(--primary-color);">
                    <i class="bi bi-pencil-fill"></i> Editar
                </h4>
                <ul style="margin:0;padding-left:1.25rem;font-size:.85rem;color:var(--text-muted);line-height:1.6;">
                    <li>Solo se puede editar si <strong>no está Completada ni Cancelada</strong></li>
                    <li>Los asistentes que ya firmaron <strong>no se pueden quitar</strong></li>
                    <li>Los relatores que ya firmaron <strong>no se pueden quitar</strong></li>
                    <li>Se pueden agregar nuevos asistentes y relatores</li>
                    <li>Se pueden quitar asistentes/relatores que estén <strong>Pendiente</strong></li>
                </ul>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;">
                <h4 style="margin:0 0 .5rem;font-size:.95rem;color:#ef4444;">
                    <i class="bi bi-trash-fill"></i> Eliminar
                </h4>
                <ul style="margin:0;padding-left:1.25rem;font-size:.85rem;color:var(--text-muted);line-height:1.6;">
                    <li>Solo se puede eliminar si <strong>no está Completada</strong></li>
                    <li>Se eliminan también todos los asistentes y relatores asociados</li>
                    <li><strong>Esta acción no se puede deshacer</strong></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- 7. Exportar PDF --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="pdf">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">7</span>
                Exportar PDF (Acta Oficial)
            </h3>
        </div>

        <p style="line-height:1.6;margin:0 0 1rem;font-size:.9rem;">
            Desde la vista de detalle o el listado, haz clic en el botón 
            <i class="bi bi-file-earmark-pdf-fill" style="color:#ef4444;"></i> <strong>PDF</strong> 
            para generar el <strong>Acta de Capacitación ODI</strong>.
        </p>

        <p style="line-height:1.6;margin:0 0 .75rem;font-size:.9rem;">El PDF incluye:</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;font-size:.85rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;">
                <strong>Sección 1 — Antecedentes</strong>
                <p style="margin:.25rem 0 0;color:var(--text-muted);">Título, lugar, fecha, duración, tipo, supervisor, estado.</p>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;">
                <strong>Sección 2 — Contenido PTS</strong>
                <p style="margin:.25rem 0 0;color:var(--text-muted);">Temario o procedimiento de trabajo seguro impartido.</p>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;">
                <strong>Sección 3 — Relatores</strong>
                <p style="margin:.25rem 0 0;color:var(--text-muted);">Nombre, rol, imagen de firma y fecha de cada relator.</p>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;">
                <strong>Sección 4 — Asistentes</strong>
                <p style="margin:.25rem 0 0;color:var(--text-muted);">Tabla con nombre, cargo, estado, fecha firma e imagen de firma.</p>
            </div>
        </div>

        <p style="margin:1rem 0 0;font-size:.85rem;color:var(--text-muted);">
            <i class="bi bi-shield-check" style="color:#10b981;"></i>
            El footer incluye <strong>validación de integridad</strong>: hash SHA-256, IP del firmante, 
            totales y referencia a la Ley 19.799. Folio formato <strong>SAEP-YYYY-XXXX</strong>.
        </p>
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
            <table class="glass-table" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th>Acción</th>
                        <th>Super Admin</th>
                        <th>Prevencionista</th>
                        <th>Supervisor</th>
                        <th>Trabajador</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Ver listado de charlas</td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                    </tr>
                    <tr>
                        <td>Crear charla</td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                    </tr>
                    <tr>
                        <td>Cambiar estado manual</td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#ef4444;"><i class="bi bi-x-circle-fill"></i></td>
                    </tr>
                    <tr>
                        <td>Firmar como asistente</td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;">Solo la propia</td>
                    </tr>
                    <tr>
                        <td>Firmar como relator</td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;">Solo la propia</td>
                        <td style="text-align:center;color:#10b981;">Solo la propia</td>
                    </tr>
                    <tr>
                        <td>Firmar por otro usuario</td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#10b981;"><i class="bi bi-check-circle-fill"></i></td>
                        <td style="text-align:center;color:#ef4444;"><i class="bi bi-x-circle-fill"></i></td>
                        <td style="text-align:center;color:#ef4444;"><i class="bi bi-x-circle-fill"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 9. Marco Legal --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="legal">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">9</span>
                Marco Legal
            </h3>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid var(--primary-color);">
                <strong style="font-size:.9rem;">Ley 19.799 — Documento y Firma Electrónica</strong>
                <p style="margin:.25rem 0 0;font-size:.85rem;color:var(--text-muted);line-height:1.5;">
                    Las firmas capturadas en SAEP constituyen <strong>firma electrónica simple</strong> conforme a esta ley. 
                    El sistema registra hash SHA-256, IP, geolocalización y timestamp como evidencia de integridad y no repudio.
                </p>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid #f59e0b;">
                <strong style="font-size:.9rem;">D.S. 40 — Obligación del Derecho a Saber (ODI)</strong>
                <p style="margin:.25rem 0 0;font-size:.85rem;color:var(--text-muted);line-height:1.5;">
                    El empleador debe informar a los trabajadores sobre los riesgos laborales, las medidas preventivas y los 
                    métodos de trabajo seguros. Las charlas de inducción y capacitación cumplen con esta obligación, y el acta 
                    PDF sirve como respaldo documental.
                </p>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid #10b981;">
                <strong style="font-size:.9rem;">Ley 16.744 — Seguro Social contra Accidentes del Trabajo</strong>
                <p style="margin:.25rem 0 0;font-size:.85rem;color:var(--text-muted);line-height:1.5;">
                    Establece la obligación del empleador de tomar todas las medidas necesarias para proteger la vida y salud 
                    de los trabajadores. Las charlas SST son un mecanismo de prevención exigido por esta normativa.
                </p>
            </div>
        </div>
    </div>

    {{-- Versión --}}
    <div style="text-align:center;color:var(--text-muted);font-size:.8rem;padding:1rem 0;">
        Documentación v{{ $meta['version'] }} — Módulo {{ $meta['titulo'] }} — Plataforma SAEP
    </div>
</div>
@endsection
