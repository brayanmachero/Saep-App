@extends('layouts.app')

@section('title', 'Documentación — Importación de Datos')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-cloud-upload-fill" style="color:var(--primary-color);"></i>
                Importación de Datos
            </h2>
            <p class="page-subheading">Guía para importar usuarios desde archivos CSV</p>
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
            <a href="#formato" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Formato CSV</a>
            <a href="#proceso" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Proceso de Importación</a>
            <a href="#mapeo" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Mapeo de Columnas</a>
            <a href="#reglas" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Reglas de Importación</a>
            <a href="#errores" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Errores Comunes</a>
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
            El módulo de <strong>Importación de Datos</strong> permite cargar usuarios masivamente desde archivos <strong>CSV</strong>,
            especialmente los generados por <strong>Talana</strong> (sistema de RRHH).
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;">
            <div style="text-align:center;background:var(--surface-bg);border-radius:.5rem;padding:.75rem;">
                <i class="bi bi-filetype-csv" style="font-size:1.5rem;color:var(--primary-color);"></i>
                <p style="margin:.25rem 0 0;font-size:.8rem;color:var(--text-muted);">Acepta CSV<br>con ; o ,</p>
            </div>
            <div style="text-align:center;background:var(--surface-bg);border-radius:.5rem;padding:.75rem;">
                <i class="bi bi-eye-fill" style="font-size:1.5rem;color:#f59e0b;"></i>
                <p style="margin:.25rem 0 0;font-size:.8rem;color:var(--text-muted);">Vista previa<br>antes de importar</p>
            </div>
            <div style="text-align:center;background:var(--surface-bg);border-radius:.5rem;padding:.75rem;">
                <i class="bi bi-arrow-repeat" style="font-size:1.5rem;color:#10b981;"></i>
                <p style="margin:.25rem 0 0;font-size:.8rem;color:var(--text-muted);">Actualiza usuarios<br>existentes</p>
            </div>
            <div style="text-align:center;background:var(--surface-bg);border-radius:.5rem;padding:.75rem;">
                <i class="bi bi-plus-circle-fill" style="font-size:1.5rem;color:#6366f1;"></i>
                <p style="margin:.25rem 0 0;font-size:.8rem;color:var(--text-muted);">Crea registros<br>automáticamente</p>
            </div>
        </div>
    </div>

    {{-- 2. Formato CSV --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="formato">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">2</span>
                Formato del Archivo CSV
            </h3>
        </div>
        <p style="font-size:.9rem;color:var(--text-muted);margin:0 0 1rem;line-height:1.5;">
            El sistema acepta archivos CSV con separador <strong>punto y coma (;)</strong> o <strong>coma (,)</strong>.
            La primera fila debe contener los encabezados. Se aceptan formatos de codificación UTF-8 y Latin-1.
        </p>

        <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;overflow-x:auto;">
            <table style="width:100%;font-size:.8rem;border-collapse:collapse;">
                <thead>
                    <tr style="background:var(--primary-color);color:white;">
                        <th style="padding:.5rem .75rem;text-align:left;">Campo</th>
                        <th style="padding:.5rem .75rem;text-align:left;">Encabezados Aceptados</th>
                        <th style="padding:.5rem .75rem;text-align:center;">Obligatorio</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;font-weight:600;">Nombre</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">name, nombre, nombres, primer nombre</td>
                        <td style="padding:.5rem .75rem;text-align:center;"><span style="color:#22c55e;">✓</span></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;font-weight:600;">Apellido Paterno</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">apellido_paterno, apellido paterno, primer apellido</td>
                        <td style="padding:.5rem .75rem;text-align:center;">—</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;font-weight:600;">Apellido Materno</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">apellido_materno, apellido materno, segundo apellido</td>
                        <td style="padding:.5rem .75rem;text-align:center;">—</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;font-weight:600;">RUT</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">rut, rut trabajador, identificacion</td>
                        <td style="padding:.5rem .75rem;text-align:center;">—</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;font-weight:600;">Email</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">email, correo, correo electronico, mail</td>
                        <td style="padding:.5rem .75rem;text-align:center;"><span style="color:#22c55e;">✓</span></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;font-weight:600;">Teléfono</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">telefono, celular, fono, telefono personal</td>
                        <td style="padding:.5rem .75rem;text-align:center;">—</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;font-weight:600;">Cargo</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">cargo, cargo actual, puesto</td>
                        <td style="padding:.5rem .75rem;text-align:center;">—</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;font-weight:600;">Departamento</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">departamento, area, seccion</td>
                        <td style="padding:.5rem .75rem;text-align:center;">—</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;font-weight:600;">Centro de Costo</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">centro_costo, centro de costo, cc</td>
                        <td style="padding:.5rem .75rem;text-align:center;">—</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;font-weight:600;">Razón Social</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">razon_social, razon social, empresa</td>
                        <td style="padding:.5rem .75rem;text-align:center;">—</td>
                    </tr>
                    <tr>
                        <td style="padding:.5rem .75rem;font-weight:600;">Fecha Ingreso</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">fecha_ingreso, fecha de ingreso, ingreso</td>
                        <td style="padding:.5rem .75rem;text-align:center;">—</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 3. Proceso --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="proceso">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">3</span>
                Proceso de Importación
            </h3>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <div style="flex-shrink:0;width:36px;height:36px;background:var(--primary-color);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;">1</div>
                <div>
                    <strong>Subir archivo CSV</strong>
                    <p style="font-size:.85rem;color:var(--text-muted);margin:.25rem 0 0;line-height:1.5;">
                        Ir a <strong>Configuraciones → Importar Datos</strong>. Seleccionar un archivo .csv y hacer clic en <strong>"Subir y Previsualizar"</strong>.
                    </p>
                </div>
            </div>
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <div style="flex-shrink:0;width:36px;height:36px;background:var(--primary-color);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;">2</div>
                <div>
                    <strong>Revisar vista previa</strong>
                    <p style="font-size:.85rem;color:var(--text-muted);margin:.25rem 0 0;line-height:1.5;">
                        Se muestran las primeras 20 filas del archivo con los datos que se importarán. 
                        Verificar que las columnas fueron detectadas correctamente.
                    </p>
                </div>
            </div>
            <div style="display:flex;gap:1rem;align-items:flex-start;">
                <div style="flex-shrink:0;width:36px;height:36px;background:var(--primary-color);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;">3</div>
                <div>
                    <strong>Confirmar importación</strong>
                    <p style="font-size:.85rem;color:var(--text-muted);margin:.25rem 0 0;line-height:1.5;">
                        Clic en <strong>"Importar Datos"</strong>. El sistema procesará todas las filas y mostrará un resumen con 
                        usuarios creados y actualizados.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Mapeo --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="mapeo">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">4</span>
                Mapeo Inteligente de Columnas
            </h3>
        </div>
        <p style="font-size:.9rem;color:var(--text-muted);margin:0 0 1rem;line-height:1.6;">
            El sistema <strong>detecta automáticamente</strong> las columnas del CSV comparando los encabezados con una lista de 
            alias predefinidos. No importa si el encabezado tiene tildes, mayúsculas o espacios: el sistema los normaliza.
        </p>
        <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;">
            <p style="font-size:.85rem;margin:0 0 .5rem"><strong>Ejemplo:</strong></p>
            <div style="font-family:monospace;font-size:.8rem;color:var(--text-muted);line-height:1.8;">
                <span style="color:#22c55e;">"Primer Nombre"</span> → se detecta como <strong>name</strong><br>
                <span style="color:#22c55e;">"Correo Electrónico"</span> → se detecta como <strong>email</strong><br>
                <span style="color:#22c55e;">"Cargo Actual"</span> → se detecta como <strong>cargo</strong><br>
                <span style="color:#22c55e;">"Centro de Costo"</span> → se detecta como <strong>centro_costo</strong>
            </div>
        </div>
    </div>

    {{-- 5. Reglas --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="reglas">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">5</span>
                Reglas de Importación
            </h3>
        </div>
        <ul style="margin:0;padding-left:1.5rem;font-size:.9rem;color:var(--text-muted);line-height:2;">
            <li>Si el <strong>RUT</strong> ya existe, se <strong>actualiza</strong> el usuario existente en vez de crear uno nuevo.</li>
            <li>Si el RUT no existe pero el <strong>email</strong> coincide, se actualiza por email.</li>
            <li>Si no existe ni RUT ni email, se <strong>crea un usuario nuevo</strong>.</li>
            <li>Los nuevos usuarios reciben la contraseña igual a su <strong>RUT</strong> y rol <strong>TRABAJADOR</strong>.</li>
            <li>Si un <strong>cargo, departamento o centro de costo</strong> no existe, se crea automáticamente.</li>
            <li>Las filas sin <strong>nombre</strong> o sin <strong>email</strong> se omiten.</li>
        </ul>
    </div>

    {{-- 6. Errores comunes --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="errores">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">6</span>
                Errores Comunes
            </h3>
        </div>
        <div style="display:flex;flex-direction:column;gap:.75rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid #ef4444;">
                <strong style="font-size:.9rem;">"No se detectaron columnas"</strong>
                <p style="font-size:.8rem;color:var(--text-muted);margin:.25rem 0 0;">
                    Los encabezados del CSV no coinciden con ningún alias conocido. Verificar que los nombres de las columnas sean similares a los listados en la sección de formato.
                </p>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid #f59e0b;">
                <strong style="font-size:.9rem;">"El archivo no es un CSV válido"</strong>
                <p style="font-size:.8rem;color:var(--text-muted);margin:.25rem 0 0;">
                    Asegurarse de que el archivo tenga extensión .csv y no sea un Excel (.xlsx). Si se exporta desde Excel, usar "Guardar como → CSV (delimitado por comas)".
                </p>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid #6366f1;">
                <strong style="font-size:.9rem;">Caracteres raros (ñ, tildes)</strong>
                <p style="font-size:.8rem;color:var(--text-muted);margin:.25rem 0 0;">
                    El sistema detecta automáticamente la codificación (UTF-8, Latin-1). Si persisten problemas, guardar el CSV en UTF-8.
                </p>
            </div>
        </div>
    </div>

    <div style="text-align:center;color:var(--text-muted);font-size:.8rem;padding:1rem 0;">
        Documentación v{{ $meta['version'] }} — Módulo {{ $meta['titulo'] }} — Plataforma SAEP
    </div>
</div>
@endsection
