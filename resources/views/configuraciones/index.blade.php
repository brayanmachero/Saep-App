@extends('layouts.app')
@section('title','Configuración del Sistema')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Configuración del Sistema</h2>
            <p class="page-subheading">Parámetros globales de la plataforma SAEP</p>
        </div>
        <a href="{{ route('importacion.index') }}" class="btn-secondary">
            <i class="bi bi-cloud-upload-fill"></i> Importar Datos
        </a>
    </div>
    @include('partials._alerts')
    <form method="POST" action="{{ route('configuraciones.update') }}">
        @csrf @method('PUT')

        @php
            $grupos = $configuraciones->groupBy('categoria');
            $grupoLabels = [
                'general'        => ['🏢','Datos de la Empresa'],
                'email'          => ['📧','Configuración de Email'],
                'sst'            => ['🦺','Prevención de Riesgos (SST)'],
                'integraciones'  => ['📱','Integraciones Externas'],
                'seguridad'      => ['🔒','Seguridad'],
                'notificaciones' => ['🔔','Notificaciones y Destinatarios'],
            ];
        @endphp

        @foreach($grupos as $grupo => $items)
        <div class="glass-card" style="margin-bottom:1.5rem">
            <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem">
                <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:.5rem">
                    <span>{{ $grupoLabels[$grupo][0] ?? '⚙️' }}</span>
                    {{ $grupoLabels[$grupo][1] ?? ucfirst($grupo) }}
                </h3>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1rem">
                @foreach($items as $config)
                @if(!$config->editable) @continue @endif
                <div class="form-group">
                    <label style="font-weight:600">
                        {{ $config->descripcion ?: ucfirst(str_replace('_',' ',$config->clave)) }}
                    </label>
                    @if(strtoupper($config->tipo) === 'BOOLEAN')
                        <div style="display:flex;align-items:center;gap:.5rem;margin-top:.25rem">
                            <input type="hidden" name="config[{{ $config->clave }}]" value="0">
                            <input type="checkbox" name="config[{{ $config->clave }}]" value="1"
                                   id="cfg_{{ $config->clave }}"
                                   {{ $config->valor === '1' || $config->valor === 'true' ? 'checked' : '' }}
                                   style="width:18px;height:18px;cursor:pointer">
                            <label for="cfg_{{ $config->clave }}" style="cursor:pointer;margin:0;font-weight:400">
                                {{ $config->valor === '1' || $config->valor === 'true' ? 'Activado' : 'Desactivado' }}
                            </label>
                        </div>
                    @elseif(strtoupper($config->tipo) === 'TEXT' && strlen($config->valor ?? '') > 80)
                        <textarea name="config[{{ $config->clave }}]" class="form-input"
                                  rows="3">{{ old('config.'.$config->clave, $config->valor) }}</textarea>
                    @elseif(strtoupper($config->tipo) === 'PASSWORD')
                        <input type="password" name="config[{{ $config->clave }}]"
                               value="" placeholder="••••••••  (dejar vacío para no cambiar)"
                               class="form-input" autocomplete="off">
                    @else
                        <input type="{{ strtoupper($config->tipo) === 'NUMBER' ? 'number' : (strtoupper($config->tipo) === 'EMAIL' ? 'email' : 'text') }}"
                               name="config[{{ $config->clave }}]"
                               value="{{ old('config.'.$config->clave, $config->valor) }}"
                               class="form-input">
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:.5rem">
            <button type="submit" class="btn-premium">
                <i class="bi bi-floppy-fill"></i> Guardar Configuración
            </button>
        </div>
    </form>

    {{-- Email Template Preview Section --}}
    <div class="glass-card" style="margin-top:2rem">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem">
            <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:.5rem">
                <span>📨</span> Previsualización de Templates de Email SST
            </h3>
            <p style="margin:.35rem 0 0;font-size:.82rem;color:var(--text-muted)">
                Haga clic en un tipo de alerta para ver cómo se verá el email que reciben los destinatarios.
            </p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem">
            @php
                $tipos = [
                    'asignacion' => ['📋','Nueva Asignación','#0f1b4c','Se envía cuando se asigna una actividad a un responsable'],
                    'recordatorio' => ['🔔','Recordatorio','#6366f1','Se envía según la periodicidad de cada actividad'],
                    'vencimiento' => ['⏰','Próxima a Vencer','#f59e0b','Se envía días antes de la fecha de vencimiento'],
                    'vencida' => ['⚠️','Vencida','#dc2626','Se envía cuando la actividad superó su fecha límite'],
                    'seguimiento_pendiente' => ['📊','Seguimiento Pendiente','#ea580c','Se envía si el mes anterior quedó sin marcar'],
                ];
            @endphp
            @foreach($tipos as $tipoKey => $tipoInfo)
            <a href="{{ route('carta-gantt.email-preview', $tipoKey) }}" target="_blank"
               style="display:flex;flex-direction:column;gap:.5rem;padding:1rem;border-radius:10px;border:1px solid var(--surface-border);background:var(--surface-color);text-decoration:none;transition:all .2s;cursor:pointer"
               onmouseover="this.style.borderColor='{{ $tipoInfo[2] }}';this.style.transform='translateY(-2px)'"
               onmouseout="this.style.borderColor='';this.style.transform=''">
                <div style="display:flex;align-items:center;gap:.5rem">
                    <span style="font-size:1.3rem">{{ $tipoInfo[0] }}</span>
                    <span style="font-weight:700;font-size:.88rem;color:var(--text-main)">{{ $tipoInfo[1] }}</span>
                </div>
                <span style="font-size:.72rem;color:var(--text-muted);line-height:1.4">{{ $tipoInfo[3] }}</span>
                <span style="display:inline-flex;align-items:center;gap:.25rem;font-size:.72rem;font-weight:600;color:{{ $tipoInfo[2] }}">
                    <i class="bi bi-eye"></i> Ver Preview
                </span>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
