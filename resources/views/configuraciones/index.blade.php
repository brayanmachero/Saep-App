@extends('layouts.app')
@section('title','Configuración del Sistema')
@section('content')

@php
    $grupos = $configuraciones->groupBy('categoria');
    $grupoMeta = [
        'general'        => ['bi-building',      'Empresa',          'Información corporativa y datos de identificación'],
        'email'          => ['bi-envelope-at',    'Correo',           'Configuración del remitente para emails del sistema'],
        'sst'            => ['bi-shield-check',   'SST',              'Prevención de Riesgos — alertas, vencimientos y recordatorios'],
        'integraciones'  => ['bi-plug',           'Integraciones',    'Conexiones con servicios externos (Kizeo, Talana)'],
        'seguridad'      => ['bi-lock',           'Seguridad',        'Control de acceso y políticas de autenticación'],
        'notificaciones' => ['bi-bell',           'Notificaciones',   'Destinatarios y automatizaciones de email'],
    ];
    $categoriaKeys = array_keys($grupoMeta);
    $activeCategoria = collect($categoriaKeys)->first(fn($k) => $grupos->has($k)) ?? $categoriaKeys[0];

    // Hint texts per config key for better UX
    $hints = [
        'empresa_nombre'              => 'Nombre legal de la empresa',
        'empresa_rut'                 => 'RUT con puntos y guión',
        'empresa_logo_url'            => 'URL pública del logotipo',
        'email_from'                  => 'Dirección de correo del remitente',
        'email_from_name'             => 'Nombre que aparece como remitente',
        'sst_notif_activa'            => 'Switch maestro — desactiva todas las alertas SST',
        'sst_notif_asignacion'        => 'Al asignar una nueva actividad',
        'sst_notif_recordatorio'      => 'Recordatorio periódico según la actividad',
        'sst_notif_seguimiento'       => 'Si el seguimiento del mes anterior no se completó',
        'sst_notif_vencimiento'       => 'Días antes de que venza la actividad',
        'sst_notif_vencida'           => 'Cuando la actividad supera su fecha límite',
        'sst_notif_cc_adicional'      => 'Emails separados por punto y coma (;)',
        'sst_notif_dias_antes_vencer' => 'Cuántos días antes del vencimiento alertar',
        'sst_notif_frecuencia_vencida'=> 'Cada cuántos días reenviar alerta de vencida',
        'sst_notif_max_dias_vencida'  => 'Máximo de días enviando alertas tras vencer',
        'integracion_talana_activa'   => 'Habilitar sincronización con Talana',
        'integracion_talana_api_key'  => 'API Key proporcionada por Talana',
        'integracion_kizeo_activa'    => 'Habilitar sincronización con Kizeo Forms',
        'integracion_kizeo_token'     => 'Token de autenticación de Kizeo',
        'seguridad_max_intentos_login'=> 'Intentos fallidos antes de bloquear',
        'seguridad_bloqueo_minutos'   => 'Minutos de bloqueo tras exceder intentos',
        'kizeo_vehiculos_activo'      => 'Activar reporte automático de vehículos',
        'kizeo_vehiculos_destinatarios'=> 'Emails separados por coma (,)',
        'charla_report_activo'        => 'Activar reporte semanal de charlas',
        'charla_report_destinatarios' => 'Emails separados por coma (,)',
        'stop_report_activo'          => 'Activar reporte semanal de Tarjeta STOP CCU',
        'stop_report_destinatarios'   => 'Emails separados por coma (,)',
        'stop_report_empresa'         => 'Empresa para filtrar reportes STOP (observador)',
        'stop_report_mensual_activo'  => 'Activar reporte mensual de Tarjeta STOP CCU',
        'stop_report_mensual_destinatarios' => 'Emails separados por coma (,)',
    ];

    $tipos_email = [
        'asignacion'            => ['bi-clipboard-plus',  'Nueva Asignación',       '#0f1b4c', 'Al asignar una actividad a un responsable'],
        'recordatorio'          => ['bi-bell',            'Recordatorio',            '#6366f1', 'Según la periodicidad de cada actividad'],
        'vencimiento'           => ['bi-clock-history',   'Próxima a Vencer',        '#f59e0b', 'Días antes de la fecha de vencimiento'],
        'vencida'               => ['bi-exclamation-triangle', 'Vencida',            '#dc2626', 'Cuando la actividad superó su fecha límite'],
        'seguimiento_pendiente' => ['bi-graph-up',        'Seguimiento Pendiente',   '#ea580c', 'Si el mes anterior quedó sin marcar'],
    ];
@endphp

<style>
    /* ── Settings Layout ── */
    .settings-layout {
        display: flex;
        gap: 0;
        min-height: calc(100vh - 140px);
        background: var(--surface-color);
        border-radius: 16px;
        border: 1px solid var(--surface-border);
        backdrop-filter: var(--glass-blur);
        overflow: hidden;
        box-shadow: var(--glass-shadow);
    }

    /* ── Sidebar ── */
    .settings-nav {
        width: 240px;
        min-width: 240px;
        border-right: 1px solid var(--surface-border);
        padding: 1.25rem 0;
        display: flex;
        flex-direction: column;
    }
    .settings-nav-header {
        padding: 0 1.25rem 1rem;
        border-bottom: 1px solid var(--surface-border);
        margin-bottom: .5rem;
    }
    .settings-nav-header h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-main);
    }
    .settings-nav-header p {
        margin: .2rem 0 0;
        font-size: .75rem;
        color: var(--text-muted);
    }
    .settings-nav-item {
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .65rem 1.25rem;
        cursor: pointer;
        font-size: .85rem;
        font-weight: 500;
        color: var(--text-muted);
        border-left: 3px solid transparent;
        transition: all .2s ease;
        user-select: none;
    }
    .settings-nav-item:hover {
        color: var(--text-main);
        background: rgba(15, 27, 76, .04);
    }
    .settings-nav-item.active {
        color: var(--primary-color);
        background: rgba(15, 27, 76, .06);
        border-left-color: var(--primary-color);
        font-weight: 600;
    }
    .settings-nav-item i {
        font-size: 1.05rem;
        width: 20px;
        text-align: center;
    }
    .settings-nav-item .nav-badge {
        margin-left: auto;
        font-size: .65rem;
        padding: .1rem .45rem;
        border-radius: 10px;
        background: var(--primary-color);
        color: #fff;
        font-weight: 600;
    }
    .settings-nav-footer {
        margin-top: auto;
        padding: .75rem 1.25rem 0;
        border-top: 1px solid var(--surface-border);
    }

    /* ── Panel ── */
    .settings-panel {
        flex: 1;
        padding: 1.75rem 2rem;
        overflow-y: auto;
        max-height: calc(100vh - 140px);
    }
    .settings-section {
        display: none;
    }
    .settings-section.active {
        display: block;
        animation: settingsFadeIn .25s ease;
    }
    @keyframes settingsFadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .section-header {
        margin-bottom: 1.75rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--surface-border);
    }
    .section-header h2 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .section-header p {
        margin: .35rem 0 0;
        font-size: .82rem;
        color: var(--text-muted);
    }

    /* ── Setting Row ── */
    .setting-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 1rem 0;
        border-bottom: 1px solid rgba(0,0,0,.04);
        gap: 1.5rem;
    }
    .setting-row:last-child {
        border-bottom: none;
    }
    .setting-info {
        flex: 1;
        min-width: 0;
    }
    .setting-label {
        font-weight: 600;
        font-size: .88rem;
        color: var(--text-main);
        margin-bottom: .15rem;
    }
    .setting-hint {
        font-size: .75rem;
        color: var(--text-muted);
        line-height: 1.4;
    }
    .setting-control {
        flex-shrink: 0;
        min-width: 220px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }
    .setting-control .form-input {
        width: 100%;
        max-width: 280px;
    }
    .setting-control textarea.form-input {
        max-width: 320px;
        min-height: 60px;
    }

    /* ── Toggle Switch ── */
    .toggle-switch {
        position: relative;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
        position: absolute;
    }
    .toggle-slider {
        position: absolute;
        inset: 0;
        background: #d1d5db;
        border-radius: 24px;
        cursor: pointer;
        transition: background .25s ease;
    }
    .toggle-slider::before {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        left: 3px;
        bottom: 3px;
        background: #fff;
        border-radius: 50%;
        transition: transform .25s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,.15);
    }
    .toggle-switch input:checked + .toggle-slider {
        background: var(--primary-color);
    }
    .toggle-switch input:checked + .toggle-slider::before {
        transform: translateX(20px);
    }
    .toggle-status {
        font-size: .75rem;
        font-weight: 500;
        margin-left: .5rem;
        min-width: 70px;
    }
    .toggle-status.on  { color: var(--primary-color); }
    .toggle-status.off { color: var(--text-muted); }

    /* ── Sub-section divider ── */
    .setting-subsection {
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--text-muted);
        padding: 1.25rem 0 .5rem;
        margin-top: .5rem;
    }

    /* ── Email Preview Cards ── */
    .preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: .75rem;
        margin-top: 1rem;
    }
    .preview-card {
        display: flex;
        flex-direction: column;
        gap: .4rem;
        padding: .85rem 1rem;
        border-radius: 10px;
        border: 1px solid var(--surface-border);
        background: var(--bg-color);
        text-decoration: none;
        transition: all .2s ease;
        cursor: pointer;
    }
    .preview-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,.08);
    }
    .preview-card-title {
        display: flex;
        align-items: center;
        gap: .4rem;
        font-weight: 600;
        font-size: .82rem;
        color: var(--text-main);
    }
    .preview-card-desc {
        font-size: .7rem;
        color: var(--text-muted);
        line-height: 1.4;
    }
    .preview-card-link {
        font-size: .7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: .2rem;
        margin-top: .25rem;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .settings-layout {
            flex-direction: column;
            min-height: auto;
        }
        .settings-nav {
            width: 100%;
            min-width: 100%;
            border-right: none;
            border-bottom: 1px solid var(--surface-border);
            padding: .75rem 0;
            flex-direction: row;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 0;
        }
        .settings-nav-header { display: none; }
        .settings-nav-footer { display: none; }
        .settings-nav-item {
            border-left: none;
            border-bottom: 3px solid transparent;
            padding: .5rem .85rem;
            white-space: nowrap;
            font-size: .78rem;
        }
        .settings-nav-item.active {
            border-bottom-color: var(--primary-color);
            border-left-color: transparent;
        }
        .settings-nav-item .nav-badge { display: none; }
        .settings-panel { padding: 1.25rem 1rem; max-height: none; }
        .setting-row { flex-direction: column; gap: .5rem; }
        .setting-control { min-width: 100%; justify-content: flex-start; }
        .setting-control .form-input { max-width: 100%; }
    }

    body.dark-mode .settings-nav-item:hover {
        background: rgba(255,255,255,.05);
    }
    body.dark-mode .settings-nav-item.active {
        background: rgba(59,108,245,.12);
    }
    body.dark-mode .setting-row {
        border-bottom-color: rgba(255,255,255,.06);
    }
    body.dark-mode .toggle-slider {
        background: #4b5563;
    }
    body.dark-mode .preview-card {
        background: rgba(255,255,255,.04);
    }
</style>

<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Configuración</h2>
            <p class="page-subheading">Administra los parámetros de la plataforma SAEP</p>
        </div>
        <a href="{{ route('importacion.index') }}" class="btn-secondary">
            <i class="bi bi-cloud-upload-fill"></i> Importar Datos
        </a>
    </div>
    @include('partials._alerts')

    <form method="POST" action="{{ route('configuraciones.update') }}">
        @csrf @method('PUT')
        <div class="settings-layout">
            {{-- ── Sidebar Navigation ── --}}
            <nav class="settings-nav">
                <div class="settings-nav-header">
                    <h3><i class="bi bi-gear"></i> Ajustes</h3>
                    <p>Selecciona una categoría</p>
                </div>

                @foreach($grupoMeta as $catKey => $catInfo)
                    @if($grupos->has($catKey))
                    <div class="settings-nav-item {{ $catKey === $activeCategoria ? 'active' : '' }}"
                         data-section="{{ $catKey }}" onclick="switchSection('{{ $catKey }}')">
                        <i class="bi {{ $catInfo[0] }}"></i>
                        <span>{{ $catInfo[1] }}</span>
                        @php $editableCount = $grupos[$catKey]->where('editable', true)->count(); @endphp
                        <span class="nav-badge">{{ $editableCount }}</span>
                    </div>
                    @endif
                @endforeach

                {{-- Email Previews nav item --}}
                <div class="settings-nav-item" data-section="previews" onclick="switchSection('previews')">
                    <i class="bi bi-envelope-paper"></i>
                    <span>Previews Email</span>
                </div>

                <div class="settings-nav-footer">
                    <button type="submit" class="btn-premium" style="width:100%">
                        <i class="bi bi-floppy-fill"></i> Guardar
                    </button>
                </div>
            </nav>

            {{-- ── Content Panel ── --}}
            <div class="settings-panel">

                @foreach($grupoMeta as $catKey => $catInfo)
                    @if(!$grupos->has($catKey)) @continue @endif
                    <div class="settings-section {{ $catKey === $activeCategoria ? 'active' : '' }}" id="section-{{ $catKey }}">
                        <div class="section-header">
                            <h2><i class="bi {{ $catInfo[0] }}"></i> {{ $catInfo[1] }}</h2>
                            <p>{{ $catInfo[2] }}</p>
                        </div>

                        @php
                            $catItems = $grupos[$catKey]->where('editable', true);
                            $booleans = $catItems->filter(fn($c) => strtoupper($c->tipo) === 'BOOLEAN');
                            $fields   = $catItems->filter(fn($c) => strtoupper($c->tipo) !== 'BOOLEAN');
                        @endphp

                        {{-- Boolean toggles first --}}
                        @if($booleans->count())
                        <div class="setting-subsection">Opciones activas</div>
                        @foreach($booleans as $config)
                            @php $isChecked = $config->valor === '1' || $config->valor === 'true'; @endphp
                            <div class="setting-row">
                                <div class="setting-info">
                                    <div class="setting-label">{{ $config->descripcion ?: ucfirst(str_replace('_',' ',$config->clave)) }}</div>
                                    <div class="setting-hint">{{ $hints[$config->clave] ?? $config->clave }}</div>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="config[{{ $config->clave }}]" value="0">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="config[{{ $config->clave }}]" value="1"
                                               id="cfg_{{ $config->clave }}"
                                               {{ $isChecked ? 'checked' : '' }}
                                               onchange="this.closest('.setting-control').querySelector('.toggle-status').className='toggle-status '+(this.checked?'on':'off');this.closest('.setting-control').querySelector('.toggle-status').textContent=this.checked?'Activado':'Desactivado'">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-status {{ $isChecked ? 'on' : 'off' }}">{{ $isChecked ? 'Activado' : 'Desactivado' }}</span>
                                </div>
                            </div>
                        @endforeach
                        @endif

                        {{-- Text / number / email / password fields --}}
                        @if($fields->count())
                        <div class="setting-subsection">Parámetros</div>
                        @foreach($fields as $config)
                            <div class="setting-row">
                                <div class="setting-info">
                                    <div class="setting-label">{{ $config->descripcion ?: ucfirst(str_replace('_',' ',$config->clave)) }}</div>
                                    <div class="setting-hint">{{ $hints[$config->clave] ?? $config->clave }}</div>
                                </div>
                                <div class="setting-control">
                                    @if(strtoupper($config->tipo) === 'PASSWORD')
                                        <input type="password" name="config[{{ $config->clave }}]"
                                               value="" placeholder="••••••••  (vacío = sin cambio)"
                                               class="form-input" autocomplete="off">
                                    @elseif(strtoupper($config->tipo) === 'TEXT' && strlen($config->valor ?? '') > 80)
                                        <textarea name="config[{{ $config->clave }}]" class="form-input"
                                                  rows="2">{{ old('config.'.$config->clave, $config->valor) }}</textarea>
                                    @else
                                        <input type="{{ strtoupper($config->tipo) === 'NUMBER' ? 'number' : (strtoupper($config->tipo) === 'EMAIL' ? 'email' : 'text') }}"
                                               name="config[{{ $config->clave }}]"
                                               value="{{ old('config.'.$config->clave, $config->valor) }}"
                                               class="form-input"
                                               @if(str_contains($config->clave, '_rut')) data-rut @endif>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @endif
                    </div>
                @endforeach

                {{-- ── Email Previews Section ── --}}
                <div class="settings-section" id="section-previews">
                    <div class="section-header">
                        <h2><i class="bi bi-envelope-paper"></i> Previews de Email</h2>
                        <p>Visualiza cómo se verán los emails que reciben los destinatarios antes de que se envíen.</p>
                    </div>

                    <div class="setting-subsection">Alertas SST — Carta Gantt</div>
                    <div class="preview-grid">
                        @foreach($tipos_email as $tipoKey => $tipoInfo)
                        <a href="{{ route('carta-gantt.email-preview', $tipoKey) }}" target="_blank"
                           class="preview-card"
                           onmouseover="this.style.borderColor='{{ $tipoInfo[2] }}'"
                           onmouseout="this.style.borderColor=''">
                            <div class="preview-card-title">
                                <i class="bi {{ $tipoInfo[0] }}" style="color:{{ $tipoInfo[2] }}"></i>
                                {{ $tipoInfo[1] }}
                            </div>
                            <div class="preview-card-desc">{{ $tipoInfo[3] }}</div>
                            <span class="preview-card-link" style="color:{{ $tipoInfo[2] }}">
                                <i class="bi bi-eye"></i> Ver Preview
                            </span>
                        </a>
                        @endforeach
                    </div>

                    @if(Route::has('charla-tracking.email-preview'))
                    <div class="setting-subsection" style="margin-top:1.5rem">Reporte Semanal — Charlas SST</div>
                    <div class="preview-grid">
                        <a href="{{ route('charla-tracking.email-preview') }}" target="_blank"
                           class="preview-card"
                           onmouseover="this.style.borderColor='#0f1b4c'" onmouseout="this.style.borderColor=''">
                            <div class="preview-card-title">
                                <i class="bi bi-bar-chart-line" style="color:#0f1b4c"></i>
                                Reporte Semanal
                            </div>
                            <div class="preview-card-desc">Resumen semanal de cumplimiento de charlas de seguridad.</div>
                            <span class="preview-card-link" style="color:#0f1b4c">
                                <i class="bi bi-eye"></i> Ver Preview
                            </span>
                        </a>
                    </div>
                    @endif

                    @if(Route::has('stop-dashboard.reporte.preview'))
                    <div class="setting-subsection" style="margin-top:1.5rem">Reporte Semanal — Tarjeta STOP CCU</div>
                    <div class="preview-grid">
                        <a href="{{ route('stop-dashboard.reporte.preview') }}" target="_blank"
                           class="preview-card"
                           onmouseover="this.style.borderColor='#3b82f6'" onmouseout="this.style.borderColor=''">
                            <div class="preview-card-title">
                                <i class="bi bi-hand-index-fill" style="color:#3b82f6"></i>
                                Reporte Semanal STOP
                            </div>
                            <div class="preview-card-desc">Observaciones de seguridad: positivas, negativas, tipos de falta y trabajadores destacados.</div>
                            <span class="preview-card-link" style="color:#3b82f6">
                                <i class="bi bi-eye"></i> Ver Preview
                            </span>
                        </a>
                        <a href="{{ route('stop-dashboard.reporte.preview', ['anio' => now()->format('Y')]) }}" target="_blank"
                           class="preview-card"
                           onmouseover="this.style.borderColor='#8b5cf6'" onmouseout="this.style.borderColor=''">
                            <div class="preview-card-title">
                                <i class="bi bi-calendar-range" style="color:#8b5cf6"></i>
                                Acumulado Anual STOP
                            </div>
                            <div class="preview-card-desc">Vista acumulada del año {{ now()->format('Y') }} completo.</div>
                            <span class="preview-card-link" style="color:#8b5cf6">
                                <i class="bi bi-eye"></i> Ver Preview
                            </span>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function switchSection(key) {
    document.querySelectorAll('.settings-nav-item').forEach(n => n.classList.remove('active'));
    document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
    document.querySelector(`.settings-nav-item[data-section="${key}"]`)?.classList.add('active');
    document.getElementById(`section-${key}`)?.classList.add('active');
}
</script>

@endsection
