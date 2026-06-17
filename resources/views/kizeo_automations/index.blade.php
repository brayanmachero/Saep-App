@extends('layouts.app')
@section('title', 'Automatizaciones Kizeo')

@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Automatizaciones Kizeo</h2>
            <p class="page-subheading">Reglas de guardado automático en SharePoint</p>
        </div>
        <a href="{{ route('kizeo-automations.create') }}" class="btn-premium">
            <i class="bi bi-plus-circle"></i> Nueva regla
        </a>
    </div>

    @include('partials._alerts')

    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-diagram-3-fill"></i></div>
            <div class="stat-info"><h3>{{ $stats['rules'] }}</h3><p>Reglas</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-toggle-on"></i></div>
            <div class="stat-info"><h3>{{ $stats['active'] }}</h3><p>Activas</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:#6366f1;"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-info"><h3>{{ $stats['runs_today'] }}</h3><p>Ejecuciones hoy</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-info"><h3>{{ $stats['errors_today'] }}</h3><p>Errores hoy</p></div>
        </div>
    </div>

    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Regla</th>
                        <th>Formulario</th>
                        <th>Destino</th>
                        <th>Estado</th>
                        <th>Última ejecución</th>
                        <th style="width:150px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        @php $lastRun = $rule->latestRun; @endphp
                        <tr>
                            <td>
                                <strong>{{ $rule->name }}</strong>
                                <div style="font-size:.75rem;color:var(--text-muted)">Prioridad {{ $rule->priority }}</div>
                            </td>
                            <td>
                                <code>{{ $rule->form_id }}</code>
                                <div style="font-size:.75rem;color:var(--text-muted)">{{ $rule->form_name ?: 'Sin nombre' }}</div>
                            </td>
                            <td style="max-width:280px;">
                                <div style="font-size:.82rem">{{ $rule->sharepoint_site ?: 'Sitio por defecto' }}</div>
                                <code style="font-size:.72rem;white-space:normal;">{{ trim(($rule->sharepoint_folder ? $rule->sharepoint_folder.'/' : '').$rule->folder_template, '/') }}</code>
                            </td>
                            <td>
                                @if($rule->enabled)
                                    <span class="badge badge-success"><i class="bi bi-check-circle"></i> Activa</span>
                                @else
                                    <span class="badge badge-secondary"><i class="bi bi-pause-circle"></i> Pausada</span>
                                @endif
                            </td>
                            <td>
                                @if($lastRun)
                                    <span class="badge {{ $lastRun->status === 'success' ? 'badge-success' : ($lastRun->status === 'error' ? 'badge-danger' : 'badge-secondary') }}">
                                        {{ strtoupper($lastRun->status) }}
                                    </span>
                                    <div style="font-size:.75rem;color:var(--text-muted)">{{ $lastRun->created_at->format('d/m/Y H:i') }}</div>
                                @else
                                    <span style="color:var(--text-muted)">Sin ejecuciones</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;gap:.35rem;align-items:center;">
                                    <a href="{{ route('kizeo-automations.edit', $rule) }}" class="icon-btn" title="Editar">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form method="POST" action="{{ route('kizeo-automations.toggle', $rule) }}">
                                        @csrf @method('PATCH')
                                        <button class="icon-btn" title="{{ $rule->enabled ? 'Pausar' : 'Activar' }}">
                                            <i class="bi {{ $rule->enabled ? 'bi-pause-fill' : 'bi-play-fill' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('kizeo-automations.destroy', $rule) }}" onsubmit="return confirm('¿Eliminar esta regla?')">
                                        @csrf @method('DELETE')
                                        <button class="icon-btn" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted)">
                                No hay reglas configuradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rules->hasPages())
            <div style="padding:16px;display:flex;justify-content:center;">
                {{ $rules->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
