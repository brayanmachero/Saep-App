@extends('layouts.app')
@section('title','Configuración del Sistema')
@section('content')
<div class="page-container" style="max-width:860px">
    <div class="page-header">
        <div>
            <h1>Configuración del Sistema</h1>
            <p style="color:var(--text-muted);margin:0">Parámetros globales de la plataforma SAEP</p>
        </div>
    </div>
    @include('partials._alerts')
    <form method="POST" action="{{ route('configuraciones.update') }}">
        @csrf @method('PUT')

        @php
            $grupos = $configuraciones->groupBy('grupo');
            $grupoLabels = [
                'general'       => ['🏢','Datos de la Empresa'],
                'email'         => ['📧','Configuración de Email'],
                'sst'           => ['🦺','Prevención de Riesgos (SST)'],
                'kizeo'         => ['📱','Integración Kizeo Forms'],
                'notificaciones'=> ['🔔','Notificaciones'],
            ];
        @endphp

        @foreach($grupos as $grupo => $items)
        <div class="glass-card" style="margin-bottom:1.5rem">
            <div style="border-bottom:1px solid var(--border-color);padding-bottom:.75rem;margin-bottom:1.25rem">
                <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:.5rem">
                    <span>{{ $grupoLabels[$grupo][0] ?? '⚙️' }}</span>
                    {{ $grupoLabels[$grupo][1] ?? ucfirst($grupo) }}
                </h3>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1rem">
                @foreach($items as $config)
                <div class="form-group">
                    <label style="font-weight:600">
                        {{ $config->label }}
                        @if($config->descripcion)
                            <span style="font-weight:400;color:var(--text-muted);font-size:.8rem;display:block">
                                {{ $config->descripcion }}
                            </span>
                        @endif
                    </label>
                    @if($config->tipo === 'boolean')
                        <div style="display:flex;align-items:center;gap:.5rem;margin-top:.25rem">
                            <input type="hidden" name="config[{{ $config->clave }}]" value="0">
                            <input type="checkbox" name="config[{{ $config->clave }}]" value="1"
                                   id="cfg_{{ $config->clave }}"
                                   {{ $config->valor === '1' ? 'checked' : '' }}
                                   style="width:18px;height:18px;cursor:pointer">
                            <label for="cfg_{{ $config->clave }}" style="cursor:pointer;margin:0;font-weight:400">
                                {{ $config->valor === '1' ? 'Activado' : 'Desactivado' }}
                            </label>
                        </div>
                    @elseif($config->tipo === 'text')
                        <textarea name="config[{{ $config->clave }}]" class="form-control"
                                  rows="3">{{ old('config.'.$config->clave, $config->valor) }}</textarea>
                    @else
                        <input type="{{ $config->tipo === 'integer' ? 'number' : 'text' }}"
                               name="config[{{ $config->clave }}]"
                               value="{{ old('config.'.$config->clave, $config->valor) }}"
                               class="form-control">
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
