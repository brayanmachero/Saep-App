@extends('layouts.app')

@section('title', 'Charlas SST')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Charlas SST</h2>
            <p class="page-subheading">Gestión de charlas de prevención de riesgos</p>
        </div>
        <a href="{{ route('charlas.create') }}" class="btn-premium">
            <i class="bi bi-plus-circle-fill"></i> Nueva Charla
        </a>
    </div>

    <!-- Filtros -->
    <form method="GET" action="{{ route('charlas.index') }}" class="filter-form glass-card">
        <div class="filter-group">
            <label>Búsqueda</label>
            <input type="text" name="buscar" class="form-input" value="{{ request('buscar') }}" placeholder="Buscar por título...">
        </div>
        <div class="filter-group">
            <label>Estado</label>
            <select name="estado" class="form-input">
                <option value="">Todos</option>
                @foreach(['BORRADOR','PROGRAMADA','EN_CURSO','COMPLETADA','CANCELADA'] as $est)
                    <option value="{{ $est }}" {{ request('estado') === $est ? 'selected' : '' }}>{{ $est }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label>Tipo</label>
            <select name="tipo" class="form-input">
                <option value="">Todos</option>
                @foreach(['CHARLA_5MIN'=>'Charla 5 Min','CAPACITACION'=>'Capacitación','INDUCCION'=>'Inducción','CHARLA_ESPECIAL'=>'Charla Especial'] as $val => $lbl)
                    <option value="{{ $val }}" {{ request('tipo') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group" style="align-self:flex-end;">
            <button type="submit" class="btn-secondary">
                <i class="bi bi-search"></i> Buscar
            </button>
            <a href="{{ route('charlas.index') }}" class="btn-ghost">Limpiar</a>
        </div>
    </form>

    <!-- Tabla -->
    <div class="glass-card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid var(--surface-border);">
                    <th style="padding:0.85rem 1.25rem;text-align:left;font-size:0.8rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Título</th>
                    <th style="padding:0.85rem 1rem;text-align:left;font-size:0.8rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Tipo</th>
                    <th style="padding:0.85rem 1rem;text-align:left;font-size:0.8rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Fecha</th>
                    <th style="padding:0.85rem 1rem;text-align:left;font-size:0.8rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Supervisor</th>
                    <th style="padding:0.85rem 1rem;text-align:center;font-size:0.8rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Asistentes</th>
                    <th style="padding:0.85rem 1rem;text-align:left;font-size:0.8rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Estado</th>
                    <th style="padding:0.85rem 1.25rem;text-align:right;font-size:0.8rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($charlas as $charla)
                @php
                    $badgeColors = [
                        'BORRADOR'=>'secondary','PROGRAMADA'=>'warning',
                        'EN_CURSO'=>'info','COMPLETADA'=>'success','CANCELADA'=>'danger'
                    ];
                    $tipoLabel = [
                        'CHARLA_5MIN'=>'5 Min','CAPACITACION'=>'Capacitación',
                        'INDUCCION'=>'Inducción','CHARLA_ESPECIAL'=>'Especial'
                    ];
                @endphp
                <tr style="border-bottom:1px solid var(--surface-border);transition:background 0.15s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.03)'"
                    onmouseout="this.style.background=''">
                    <td style="padding:0.85rem 1.25rem;">
                        <a href="{{ route('charlas.show', $charla) }}"
                           style="font-weight:600;font-size:0.9rem;color:var(--text-main);text-decoration:none;">
                            {{ $charla->titulo }}
                        </a>
                        @if($charla->lugar)
                            <span style="display:block;font-size:0.75rem;color:var(--text-muted);">
                                <i class="bi bi-geo-alt"></i> {{ $charla->lugar }}
                            </span>
                        @endif
                    </td>
                    <td style="padding:0.85rem 1rem;font-size:0.85rem;">
                        {{ $tipoLabel[$charla->tipo] ?? $charla->tipo }}
                    </td>
                    <td style="padding:0.85rem 1rem;font-size:0.85rem;">
                        {{ $charla->fecha_programada->format('d/m/Y H:i') }}<br>
                        <span style="font-size:0.75rem;color:var(--text-muted);">{{ $charla->duracion_minutos }} min</span>
                    </td>
                    <td style="padding:0.85rem 1rem;font-size:0.85rem;">
                        {{ $charla->supervisor->name ?? '—' }}
                    </td>
                    <td style="padding:0.85rem 1rem;text-align:center;">
                        <span class="badge info" style="font-size:0.8rem;">{{ $charla->asistentes_count }}</span>
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <span class="badge {{ $badgeColors[$charla->estado] ?? 'secondary' }}">{{ $charla->estado }}</span>
                    </td>
                    <td style="padding:0.85rem 1.25rem;text-align:right;">
                        <div style="display:flex;gap:0.4rem;justify-content:flex-end;">
                            <a href="{{ route('charlas.show', $charla) }}" class="icon-btn" title="Ver">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                            <a href="{{ route('charlas.edit', $charla) }}" class="icon-btn" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            @if($charla->estado !== 'COMPLETADA')
                            <form method="POST" action="{{ route('charlas.destroy', $charla) }}"
                                  onsubmit="return confirm('¿Eliminar esta charla?')">
                                @csrf @method('DELETE')
                                <button class="icon-btn danger" type="submit" title="Eliminar">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="padding:3rem;text-align:center;color:var(--text-muted);">
                        <i class="bi bi-mic-mute" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                        No hay charlas registradas
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($charlas->hasPages())
        <div style="padding:1rem 1.25rem;border-top:1px solid var(--surface-border);">
            {{ $charlas->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
