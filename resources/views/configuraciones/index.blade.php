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
</div>
@endsection
