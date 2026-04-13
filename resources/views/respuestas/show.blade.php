@extends('layouts.app')

@section('title', 'Formulario #REQ-' . str_pad($respuesta->id, 4, '0', STR_PAD_LEFT))

@section('content')
<div class="page-container">

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
            <a href="{{ url()->previous() }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr;gap:1.25rem;align-items:flex-start;" class="resp-show-grid">

        <!-- Info compacta horizontal -->
        <div class="glass-card" style="padding:.85rem 1.1rem;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;font-size:.85rem;">
                <div>
                    <span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;">Solicitante</span>
                    <p style="margin:.15rem 0 0;font-weight:600;">{{ $respuesta->usuario->name ?? '—' }}</p>
                </div>
                <div>
                    <span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;">Departamento</span>
                    <p style="margin:.15rem 0 0;font-weight:500;">{{ $respuesta->usuario->departamento->nombre ?? '—' }}</p>
                </div>
                <div>
                    <span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;">Formulario</span>
                    <p style="margin:.15rem 0 0;font-weight:500;">{{ $respuesta->formulario->nombre ?? '—' }} (v{{ $respuesta->version_form }})</p>
                </div>
                <div>
                    <span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;">Fecha envío</span>
                    <p style="margin:.15rem 0 0;font-weight:500;">{{ $respuesta->created_at->format('d/m/Y H:i') }}</p>
                </div>
                @if($respuesta->fecha_resolucion)
                <div>
                    <span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;">Resolución</span>
                    <p style="margin:.15rem 0 0;font-weight:500;">{{ \Carbon\Carbon::parse($respuesta->fecha_resolucion)->format('d/m/Y H:i') }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Datos del formulario -->
        <div>
            <div class="glass-card" style="margin-bottom:1.25rem;">
                <h3 style="font-size:0.875rem;color:var(--text-muted);margin-bottom:1.25rem;text-transform:uppercase;letter-spacing:0.05em;">
                    <i class="bi bi-clipboard-data"></i> Datos Ingresados
                </h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.5rem 1.5rem;">
                @foreach($schema as $field)
                    @if($field['type'] === 'divider')
                        <div style="grid-column:1/-1;">
                            <hr style="border-color:var(--surface-border);margin:1rem 0 .5rem;">
                            <p style="color:var(--text-muted);font-size:0.82rem;text-align:center;">{{ $field['label'] }}</p>
                        </div>
                    @elseif($field['type'] === 'textarea' || $field['type'] === 'signature')
                        <div style="grid-column:1/-1;margin-bottom:.75rem;">
                            <label style="display:block;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.25rem;">
                                {{ $field['label'] }}
                            </label>
                            @php $val = $datos[$field['id']] ?? null; @endphp
                            @if($field['type'] === 'signature')
                                @if($val)
                                    <img src="{{ $val }}" alt="Firma" style="max-width:200px;border:1px solid var(--surface-border);border-radius:8px;">
                                @else
                                    <span style="color:var(--text-muted);font-style:italic;font-size:.85rem;">Sin firma</span>
                                @endif
                            @else
                                <p style="font-size:0.9rem;margin:0;white-space:pre-line;">{{ $val ?? '—' }}</p>
                            @endif
                        </div>
                    @else
                        <div style="margin-bottom:.75rem;">
                            <label style="display:block;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.25rem;">
                                {{ $field['label'] }}
                            </label>
                            @php $val = $datos[$field['id']] ?? null; @endphp
                            @if($field['type'] === 'file')
                                @if($val && is_array($val) && isset($val['path']))
                                    <a href="{{ asset('storage/' . $val['path']) }}" target="_blank"
                                       style="display:inline-flex;align-items:center;gap:.3rem;font-size:.82rem;color:var(--accent-color);text-decoration:none;">
                                        <i class="bi bi-download"></i> {{ $val['name'] ?? 'Descargar' }}
                                        @if(isset($val['size']))
                                            <small style="color:var(--text-muted);">({{ number_format($val['size']/1024, 0) }} KB)</small>
                                        @endif
                                    </a>
                                @elseif($val)
                                    <a href="{{ $val }}" target="_blank" style="font-size:.85rem;color:var(--accent-color);text-decoration:none;">
                                        <i class="bi bi-download"></i> Descargar
                                    </a>
                                @else
                                    <span style="color:var(--text-muted);font-style:italic;font-size:.85rem;">Sin archivo</span>
                                @endif
                            @elseif(is_array($val))
                                <span style="font-size:0.9rem;">{{ implode(', ', $val) }}</span>
                            @else
                                <span style="font-size:0.9rem;">{{ $val ?? '—' }}</span>
                            @endif
                        </div>
                    @endif
                @endforeach
                </div>
            </div>

            <!-- Historial de aprobaciones -->
            @if($respuesta->aprobaciones->count() > 0)
            <div class="glass-card">
                <h3 style="font-size:0.875rem;color:var(--text-muted);margin-bottom:1rem;text-transform:uppercase;letter-spacing:0.05em;">
                    <i class="bi bi-clock-history"></i> Historial
                </h3>
                <div style="display:flex;flex-direction:column;gap:0.65rem;">
                    @foreach($respuesta->aprobaciones->sortByDesc('fecha') as $apr)
                    @php
                        $aprMap = ['Aprobado'=>'success','Rechazado'=>'danger','Revisión'=>'warning','Comentario'=>''];
                    @endphp
                    <div style="display:flex;gap:0.65rem;padding:0.65rem;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid var(--surface-border);">
                        <div class="avatar" style="width:30px;height:30px;font-size:0.7rem;flex-shrink:0;">
                            {{ strtoupper(substr($apr->aprobador->name ?? 'U', 0, 1)) }}
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.25rem;margin-bottom:0.2rem;">
                                <strong style="font-size:0.82rem;">{{ $apr->aprobador->name ?? 'N/A' }}</strong>
                                <div style="display:flex;align-items:center;gap:0.4rem;">
                                    <span class="badge {{ $aprMap[$apr->accion] ?? '' }}" style="font-size:.7rem;">{{ $apr->accion }}</span>
                                    <span style="font-size:0.7rem;color:var(--text-muted);">
                                        {{ \Carbon\Carbon::parse($apr->fecha)->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                            </div>
                            @if($apr->comentario)
                                <p style="font-size:0.82rem;color:var(--text-muted);margin:0;">{{ $apr->comentario }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Acción de Aprobación -->
        @if(auth()->user()->rol && auth()->user()->rol->puede_aprobar && $respuesta->estado === 'Pendiente')
        <div class="glass-card">
            <h3 style="font-size:0.875rem;color:var(--text-muted);margin-bottom:1rem;text-transform:uppercase;letter-spacing:0.05em;">
                <i class="bi bi-shield-check"></i> Acción de Aprobación
            </h3>
            <form method="POST" action="{{ route('respuestas.estado', $respuesta) }}">
                @csrf @method('PATCH')
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:.82rem;">Decisión *</label>
                        <select name="estado" class="form-input" required style="font-size:.85rem;">
                            <option value="">Seleccionar</option>
                            <option value="Aprobado">✅ Aprobar</option>
                            <option value="Rechazado">❌ Rechazar</option>
                            <option value="Revisión">🔁 Solicitar Revisión</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:.82rem;">Comentario</label>
                        <textarea name="comentario" class="form-input" rows="2"
                            placeholder="Comentario opcional..." style="font-size:.85rem;"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-premium" style="width:100%;margin-top:.75rem;justify-content:center;">
                    <i class="bi bi-send-fill"></i> Enviar Decisión
                </button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
