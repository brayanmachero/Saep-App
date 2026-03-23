@extends('layouts.app')

@section('title', 'Solicitud #REQ-' . str_pad($respuesta->id, 4, '0', STR_PAD_LEFT))

@section('content')
<div class="page-container" style="max-width:900px;">

    @include('partials._alerts')

    @php
        $badgeMap = ['Pendiente'=>'warning','Aprobado'=>'success','Rechazado'=>'danger','Borrador'=>'','Revisión'=>'warning'];
        $badgeCls = $badgeMap[$respuesta->estado] ?? '';
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                #REQ-{{ str_pad($respuesta->id, 4, '0', STR_PAD_LEFT) }}
                <span class="badge {{ $badgeCls }}" style="font-size:0.9rem;">{{ $respuesta->estado }}</span>
            </h2>
            <p class="page-subheading">{{ $respuesta->formulario->nombre ?? '' }}</p>
        </div>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <a href="{{ route('pdf.respuesta', $respuesta) }}" class="btn-secondary" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill"></i> Descargar PDF
            </a>
            <a href="{{ route('respuestas.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:flex-start;">
        <!-- Datos del formulario -->
        <div>
            <div class="glass-card" style="margin-bottom:1.5rem;">
                <h3 style="font-size:0.95rem;color:var(--text-muted);margin-bottom:1.5rem;text-transform:uppercase;letter-spacing:0.05em;">
                    <i class="bi bi-clipboard-data"></i> Datos Ingresados
                </h3>
                @foreach($schema as $field)
                    @if($field['type'] === 'divider')
                        <hr style="border-color:var(--surface-border);margin:1.25rem 0;">
                        <p style="color:var(--text-muted);font-size:0.85rem;text-align:center;">{{ $field['label'] }}</p>
                    @else
                        <div style="margin-bottom:1.25rem;">
                            <label style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.35rem;">
                                {{ $field['label'] }}
                            </label>
                            @php $val = $datos[$field['id']] ?? null; @endphp
                            @if($field['type'] === 'signature')
                                @if($val)
                                    <img src="{{ $val }}" alt="Firma" style="max-width:200px;border:1px solid var(--surface-border);border-radius:8px;">
                                @else
                                    <span style="color:var(--text-muted);font-style:italic;">Sin firma</span>
                                @endif
                            @elseif($field['type'] === 'file')
                                @if($val)
                                    <a href="{{ $val }}" target="_blank" class="btn-secondary" style="width:fit-content;">
                                        <i class="bi bi-download"></i> Descargar archivo
                                    </a>
                                @else
                                    <span style="color:var(--text-muted);font-style:italic;">Sin archivo</span>
                                @endif
                            @elseif(is_array($val))
                                <span>{{ implode(', ', $val) }}</span>
                            @else
                                <span style="font-size:0.95rem;">{{ $val ?? '—' }}</span>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>

            <!-- Historial de aprobaciones -->
            @if($respuesta->aprobaciones->count() > 0)
            <div class="glass-card">
                <h3 style="font-size:0.95rem;color:var(--text-muted);margin-bottom:1.25rem;text-transform:uppercase;letter-spacing:0.05em;">
                    <i class="bi bi-clock-history"></i> Historial
                </h3>
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    @foreach($respuesta->aprobaciones->sortByDesc('fecha') as $apr)
                    @php
                        $aprMap = ['Aprobado'=>'success','Rechazado'=>'danger','Revisión'=>'warning','Comentario'=>''];
                    @endphp
                    <div style="display:flex;gap:0.75rem;padding:0.75rem;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid var(--surface-border);">
                        <div class="avatar" style="width:34px;height:34px;font-size:0.75rem;flex-shrink:0;">
                            {{ strtoupper(substr($apr->aprobador->name ?? 'U', 0, 1)) }}
                        </div>
                        <div style="flex:1;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                                <strong style="font-size:0.875rem;">{{ $apr->aprobador->name ?? 'N/A' }}</strong>
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <span class="badge {{ $aprMap[$apr->accion] ?? '' }}">{{ $apr->accion }}</span>
                                    <span style="font-size:0.75rem;color:var(--text-muted);">
                                        {{ \Carbon\Carbon::parse($apr->fecha)->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                            </div>
                            @if($apr->comentario)
                                <p style="font-size:0.85rem;color:var(--text-muted);margin:0;">{{ $apr->comentario }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Panel lateral: info + acción -->
        <div>
            <div class="glass-card" style="margin-bottom:1.25rem;">
                <h3 style="font-size:0.9rem;color:var(--text-muted);margin-bottom:1rem;text-transform:uppercase;letter-spacing:0.05em;">
                    <i class="bi bi-info-circle"></i> Información
                </h3>
                <div style="display:flex;flex-direction:column;gap:0.75rem;font-size:0.875rem;">
                    <div>
                        <span style="color:var(--text-muted);">Solicitante</span>
                        <p style="margin:0.2rem 0 0;font-weight:500;">{{ $respuesta->usuario->name ?? '—' }}</p>
                    </div>
                    <div>
                        <span style="color:var(--text-muted);">Departamento</span>
                        <p style="margin:0.2rem 0 0;font-weight:500;">{{ $respuesta->usuario->departamento->nombre ?? '—' }}</p>
                    </div>
                    <div>
                        <span style="color:var(--text-muted);">Formulario</span>
                        <p style="margin:0.2rem 0 0;font-weight:500;">{{ $respuesta->formulario->nombre ?? '—' }} (v{{ $respuesta->version_form }})</p>
                    </div>
                    <div>
                        <span style="color:var(--text-muted);">Fecha envío</span>
                        <p style="margin:0.2rem 0 0;font-weight:500;">{{ $respuesta->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    @if($respuesta->fecha_resolucion)
                    <div>
                        <span style="color:var(--text-muted);">Resolución</span>
                        <p style="margin:0.2rem 0 0;font-weight:500;">{{ \Carbon\Carbon::parse($respuesta->fecha_resolucion)->format('d/m/Y H:i') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            @if(auth()->user()->rol && auth()->user()->rol->puede_aprobar && $respuesta->estado === 'Pendiente')
            <div class="glass-card">
                <h3 style="font-size:0.9rem;color:var(--text-muted);margin-bottom:1rem;text-transform:uppercase;letter-spacing:0.05em;">
                    <i class="bi bi-shield-check"></i> Acción de Aprobación
                </h3>
                <form method="POST" action="{{ route('respuestas.estado', $respuesta) }}">
                    @csrf @method('PATCH')
                    <div class="form-group">
                        <label>Decisión *</label>
                        <select name="estado" class="form-input" required>
                            <option value="">Seleccionar</option>
                            <option value="Aprobado">✅ Aprobar</option>
                            <option value="Rechazado">❌ Rechazar</option>
                            <option value="Revisión">🔁 Solicitar Revisión</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Comentario</label>
                        <textarea name="comentario" class="form-input" rows="3"
                            placeholder="Comentario opcional..."></textarea>
                    </div>
                    <button type="submit" class="btn-premium" style="width:100%;">
                        <i class="bi bi-send-fill"></i> Enviar Decisión
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
