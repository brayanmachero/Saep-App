@extends('layouts.app')

@section('title', $charla->titulo)

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">{{ $charla->titulo }}</h2>
            <p class="page-subheading">
                {{ $charla->tipo }} &bull; {{ $charla->fecha_programada->format('d/m/Y H:i') }}
            </p>
        </div>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <a href="{{ route('pdf.charla', $charla) }}" class="btn-secondary" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill"></i> Descargar PDF
            </a>
            <a href="{{ route('charlas.edit', $charla) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            <a href="{{ route('charlas.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    @php
        $badgeColors = [
            'BORRADOR'=>'secondary','PROGRAMADA'=>'warning',
            'EN_CURSO'=>'info','COMPLETADA'=>'success','CANCELADA'=>'danger'
        ];
    @endphp

    <div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:flex-start;">

        <!-- Principal -->
        <div>
            <!-- Info -->
            <div class="glass-card" style="margin-bottom:1.25rem;">
                <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin-bottom:1rem;">
                    <i class="bi bi-info-circle"></i> Información
                </h3>
                <div class="form-grid-2">
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Tipo</p>
                        <p style="font-size:0.9rem;font-weight:500;margin:0;">{{ $charla->tipo }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Fecha / Hora</p>
                        <p style="font-size:0.9rem;margin:0;">{{ $charla->fecha_programada->format('d/m/Y H:i') }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Lugar</p>
                        <p style="font-size:0.9rem;margin:0;">{{ $charla->lugar ?: '—' }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Duración</p>
                        <p style="font-size:0.9rem;margin:0;">{{ $charla->duracion_minutos }} minutos</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Supervisor / Relator</p>
                        <p style="font-size:0.9rem;margin:0;">{{ $charla->supervisor->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Creado por</p>
                        <p style="font-size:0.9rem;margin:0;">{{ $charla->creador->name ?? '—' }}</p>
                    </div>
                </div>

                @if($charla->contenido)
                <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--surface-border);">
                    <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.5rem;">Contenido</p>
                    <div style="font-size:0.9rem;line-height:1.6;white-space:pre-wrap;">{{ $charla->contenido }}</div>
                </div>
                @endif
            </div>

            <!-- Lista de asistentes -->
            <div class="glass-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                    <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;">
                        <i class="bi bi-people-fill"></i> Asistentes
                        <span style="font-size:0.85rem;font-weight:400;text-transform:none;">— {{ $charla->asistentes->count() }} convocados</span>
                    </h3>
                    @php
                        $firmados = $charla->asistentes->where('estado', 'FIRMADO')->count();
                        $total    = $charla->asistentes->count();
                    @endphp
                    @if($total > 0)
                    <div style="font-size:0.8rem;color:var(--text-muted);">
                        <span style="color:#16a34a;font-weight:600;">{{ $firmados }}</span> / {{ $total }} firmaron
                    </div>
                    @endif
                </div>

                @forelse($charla->asistentes as $asistente)
                <div style="display:flex;align-items:center;gap:0.75rem;padding:0.65rem 0;
                    border-bottom:1px solid var(--surface-border);
                    {{ $loop->last ? 'border-bottom:none;' : '' }}">
                    <div class="avatar" style="width:36px;height:36px;font-size:0.85rem;flex-shrink:0;">
                        {{ strtoupper(substr($asistente->usuario->name ?? 'U', 0, 1)) }}
                    </div>
                    <div style="flex:1;">
                        <span style="font-size:0.875rem;font-weight:500;">{{ $asistente->usuario->name ?? '—' }}</span>
                        <span style="display:block;font-size:0.75rem;color:var(--text-muted);">
                            {{ $asistente->usuario->rol->nombre ?? '' }}
                        </span>
                    </div>
                    @if($asistente->estado === 'FIRMADO')
                        <div style="text-align:right;">
                            <span class="badge success"><i class="bi bi-pen-fill"></i> Firmado</span>
                            <span style="display:block;font-size:0.72rem;color:var(--text-muted);">
                                {{ $asistente->fecha_firma?->format('d/m/Y H:i') }}
                            </span>
                        </div>
                        @if($asistente->firma_imagen && str_starts_with($asistente->firma_imagen, 'data:image'))
                            <img src="{{ $asistente->firma_imagen }}"
                                 style="height:40px;border:1px solid var(--surface-border);border-radius:6px;background:white;">
                        @endif
                    @else
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <span class="badge warning">Pendiente</span>
                            @if($asistente->usuario_id === auth()->id() && $charla->estado === 'EN_CURSO')
                                <a href="{{ route('charlas.firmar', [$charla, $asistente]) }}"
                                   class="btn-premium" style="font-size:0.78rem;padding:0.3rem 0.75rem;">
                                    <i class="bi bi-pen-fill"></i> Firmar
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
                @empty
                <p style="color:var(--text-muted);font-size:0.875rem;text-align:center;padding:1.5rem 0;">
                    No hay asistentes convocados
                </p>
                @endforelse
            </div>
        </div>

        <!-- Lateral -->
        <div>
            <!-- Estado -->
            <div class="glass-card" style="margin-bottom:1rem;">
                <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin-bottom:1rem;">
                    <i class="bi bi-activity"></i> Estado
                </h3>
                <div style="margin-bottom:1rem;">
                    <span class="badge {{ $badgeColors[$charla->estado] ?? 'secondary' }}" style="font-size:0.85rem;padding:0.4rem 0.9rem;">
                        {{ $charla->estado }}
                    </span>
                </div>

                @if(in_array(auth()->user()->rol->nombre ?? '', ['SUPER_ADMIN', 'PREVENCIONISTA']))
                <form method="POST" action="{{ route('charlas.estado', $charla) }}">
                    @csrf @method('PATCH')
                    <div class="form-group">
                        <label style="font-size:0.8rem;">Cambiar estado</label>
                        <select name="estado" class="form-input" style="font-size:0.85rem;">
                            @foreach(['BORRADOR','PROGRAMADA','EN_CURSO','COMPLETADA','CANCELADA'] as $est)
                                <option value="{{ $est }}" {{ $charla->estado === $est ? 'selected' : '' }}>{{ $est }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-secondary" style="width:100%;justify-content:center;font-size:0.85rem;">
                        <i class="bi bi-arrow-repeat"></i> Actualizar Estado
                    </button>
                </form>
                @endif
            </div>

            <!-- Estadísticas firma -->
            @if($charla->asistentes->count() > 0)
            <div class="glass-card" style="margin-bottom:1rem;">
                <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin-bottom:0.75rem;">
                    <i class="bi bi-bar-chart-fill"></i> Firmas
                </h3>
                @php
                    $total     = $charla->asistentes->count();
                    $firmados  = $charla->asistentes->where('estado', 'FIRMADO')->count();
                    $pct       = $total > 0 ? round($firmados / $total * 100) : 0;
                @endphp
                <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.5rem;">
                    <span style="color:var(--text-muted)">Progreso</span>
                    <strong>{{ $pct }}%</strong>
                </div>
                <div style="height:8px;background:rgba(107,114,128,0.15);border-radius:10px;overflow:hidden;">
                    <div style="height:100%;width:{{ $pct }}%;background:var(--primary-color);border-radius:10px;transition:width 0.4s;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--text-muted);margin-top:0.4rem;">
                    <span>{{ $firmados }} firmados</span>
                    <span>{{ $total - $firmados }} pendientes</span>
                </div>
            </div>
            @endif

            <!-- Acciones -->
            <div class="glass-card">
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <a href="{{ route('charlas.edit', $charla) }}" class="btn-secondary" style="justify-content:center;">
                        <i class="bi bi-pencil-fill"></i> Editar Charla
                    </a>
                    @if($charla->estado !== 'COMPLETADA')
                    <form method="POST" action="{{ route('charlas.destroy', $charla) }}"
                          onsubmit="return confirm('¿Eliminar esta charla?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-ghost danger" style="width:100%;justify-content:center;">
                            <i class="bi bi-trash-fill"></i> Eliminar
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
