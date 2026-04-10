@extends('layouts.app')

@section('title', $charla->titulo)

@section('content')
<div class="page-container">

    @include('partials._alerts')

    @php
        $badge  = $charla->estadoBadge;
        $progress = $charla->firmaProgress;
        $pct    = $progress['percent'];
        $myRelator = $charla->relatores->firstWhere('usuario_id', auth()->id());
        $myAsistente = $charla->asistentes->firstWhere('usuario_id', auth()->id());
        $canEdit = !in_array($charla->estado, ['COMPLETADA','CANCELADA']);
        $tipoLabel = ['CHARLA_5MIN'=>'Charla 5 Min','CAPACITACION'=>'Capacitación','INDUCCION'=>'Inducción','CHARLA_ESPECIAL'=>'Charla Especial'];
    @endphp

    <!-- Header -->
    <div class="page-header" style="align-items:flex-start;">
        <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.4rem;">
                <h2 class="page-heading" style="margin:0;">{{ $charla->titulo }}</h2>
                <span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                <span style="font-size:0.78rem;padding:3px 10px;border-radius:6px;background:rgba(0,86,179,0.12);color:#0056b3;font-weight:700;">
                    {{ $tipoLabel[$charla->tipo] ?? $charla->tipo }}
                </span>
            </div>
            <p style="color:var(--text-muted);font-size:0.88rem;margin:0;">
                <i class="bi bi-calendar3"></i> {{ $charla->fecha_programada->format('d/m/Y H:i') }}
                @if($charla->lugar)
                &nbsp;&bull;&nbsp;<i class="bi bi-geo-alt"></i> {{ $charla->lugar }}
                @endif
                &nbsp;&bull;&nbsp;<i class="bi bi-clock"></i> {{ $charla->duracion_minutos }} min
            </p>
        </div>
        <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
            <a href="{{ route('pdf.charla', $charla) }}" class="btn-secondary" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill" style="color:#dc2626;"></i> PDF
            </a>
            @if($canEdit && auth()->user()->tieneAcceso('charlas', 'puede_editar'))
            <a href="{{ route('charlas.edit', $charla) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            @endif
            <a href="{{ route('charlas.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- Info grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.25rem;">
        @if($charla->centroCosto)
        <div class="glass-card" style="padding:0.9rem 1.1rem;">
            <p style="font-size:0.72rem;color:var(--text-muted);margin:0 0 3px;text-transform:uppercase;font-weight:700;">Centro de Costo</p>
            <p style="font-size:0.9rem;font-weight:600;margin:0;">{{ $charla->centroCosto->nombre }}</p>
        </div>
        @endif
        @if($charla->supervisor)
        <div class="glass-card" style="padding:0.9rem 1.1rem;">
            <p style="font-size:0.72rem;color:var(--text-muted);margin:0 0 3px;text-transform:uppercase;font-weight:700;">Supervisor</p>
            <p style="font-size:0.9rem;font-weight:600;margin:0;">{{ $charla->supervisor->name }}</p>
        </div>
        @endif
        <div class="glass-card" style="padding:0.9rem 1.1rem;">
            <p style="font-size:0.72rem;color:var(--text-muted);margin:0 0 3px;text-transform:uppercase;font-weight:700;">Creado por</p>
            <p style="font-size:0.9rem;font-weight:600;margin:0;">{{ $charla->creador->name }}</p>
        </div>
        <div class="glass-card" style="padding:0.9rem 1.1rem;">
            <p style="font-size:0.72rem;color:var(--text-muted);margin:0 0 3px;text-transform:uppercase;font-weight:700;">Firmas</p>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <div style="flex:1;height:6px;background:rgba(255,255,255,0.08);border-radius:99px;">
                    <div style="height:100%;width:{{ $pct }}%;background:{{ $pct==100?'#16a34a':'#0056b3' }};border-radius:99px;transition:width 0.4s;"></div>
                </div>
                <span style="font-size:0.8rem;font-weight:700;">{{ $progress['firmados'] }}/{{ $progress['total'] }}</span>
            </div>
        </div>
    </div>

    <!-- Cambiar estado -->
    @if(($canEdit || $charla->estado === 'EN_CURSO') && auth()->user()->tieneAcceso('charlas', 'puede_editar'))
    <div class="glass-card" style="margin-bottom:1.25rem;">
        <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
            <i class="bi bi-arrow-repeat"></i> Cambiar Estado
        </h3>
        <form method="POST" action="{{ route('charlas.estado', $charla) }}" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
            @csrf
            @method('PATCH')
            <select name="estado" class="form-input" style="width:auto;">
                @foreach(['BORRADOR'=>'Borrador','PROGRAMADA'=>'Programada','EN_CURSO'=>'En Curso','COMPLETADA'=>'Completada','CANCELADA'=>'Cancelada'] as $v=>$l)
                    <option value="{{ $v }}" {{ $charla->estado===$v?'selected':'' }}>{{ $l }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secondary"><i class="bi bi-check2"></i> Actualizar Estado</button>
        </form>
    </div>
    @endif

    <!-- Mi firma como asistente -->
    @if($myAsistente && $myAsistente->estado === 'PENDIENTE')
    <div class="glass-card" style="margin-bottom:1.25rem;border-left:3px solid #0056b3;">
        <p style="font-size:0.9rem;font-weight:600;margin:0 0 0.6rem;color:#0056b3;">
            <i class="bi bi-pen-fill"></i> Debes firmar tu asistencia
        </p>
        <a href="{{ route('charlas.firmar', [$charla, $myAsistente]) }}" class="btn-premium">
            <i class="bi bi-pen"></i> Firmar Asistencia
        </a>
    </div>
    @endif

    <!-- Mi firma como relator -->
    @if($myRelator && $myRelator->estado === 'PENDIENTE')
    <div class="glass-card" style="margin-bottom:1.25rem;border-left:3px solid #7c3aed;">
        <p style="font-size:0.9rem;font-weight:600;margin:0 0 0.6rem;color:#7c3aed;">
            <i class="bi bi-person-badge-fill"></i> Debes firmar como {{ $myRelator->rolLabel }}
        </p>
        <a href="{{ route('charlas.firmarRelator', [$charla, $myRelator]) }}" class="btn-premium" style="background:#7c3aed;">
            <i class="bi bi-pen"></i> Firmar como Relator
        </a>
    </div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

        <!-- Relatores panel -->
        <div class="glass-card">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
                <i class="bi bi-person-badge-fill" style="color:#0056b3;"></i> Relatores / Instructores
            </h3>
            @forelse($charla->relatores as $rel)
            <div style="display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0;border-bottom:1px solid var(--surface-border);">
                <div class="avatar" style="width:36px;height:36px;flex-shrink:0;">{{ strtoupper(substr($rel->usuario->name,0,1)) }}</div>
                <div style="flex:1;min-width:0;">
                    <p style="font-weight:600;font-size:0.88rem;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $rel->usuario->name }}</p>
                    <p style="font-size:0.72rem;color:var(--text-muted);margin:0;">{{ $rel->rolLabel }}</p>
                </div>
                @if($rel->estado === 'FIRMADO')
                    <span style="font-size:0.72rem;padding:3px 8px;border-radius:6px;background:rgba(22,163,74,0.15);color:#16a34a;flex-shrink:0;">
                        <i class="bi bi-check-circle-fill"></i> Firmado
                    </span>
                @else
                    @if($rel->usuario_id === auth()->id())
                        <a href="{{ route('charlas.firmarRelator', [$charla, $rel]) }}"
                           style="font-size:0.72rem;padding:3px 10px;border-radius:6px;background:rgba(124,58,237,0.15);color:#7c3aed;flex-shrink:0;text-decoration:none;font-weight:700;">
                            <i class="bi bi-pen"></i> Firmar
                        </a>
                    @else
                        <span style="font-size:0.72rem;padding:3px 8px;border-radius:6px;background:rgba(217,119,6,0.12);color:#d97706;flex-shrink:0;">Pendiente</span>
                    @endif
                @endif
            </div>
            @empty
            <p style="color:var(--text-muted);font-size:0.85rem;">Sin relatores asignados</p>
            @endforelse
        </div>

        <!-- Contenido -->
        <div class="glass-card">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.75rem;font-weight:700;">
                <i class="bi bi-file-text"></i> Contenido
            </h3>
            @if($charla->contenido)
            <div style="font-size:0.85rem;line-height:1.6;white-space:pre-wrap;max-height:220px;overflow-y:auto;">{{ $charla->contenido }}</div>
            @else
            <p style="color:var(--text-muted);font-size:0.85rem;">Sin contenido registrado</p>
            @endif
        </div>
    </div>

    <!-- Asistentes panel -->
    <div class="glass-card" style="margin-top:1.25rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;font-weight:700;margin:0;">
                <i class="bi bi-people-fill"></i> Asistentes
                <span style="font-size:0.75rem;padding:2px 8px;border-radius:6px;background:rgba(0,86,179,0.12);color:#0056b3;margin-left:6px;">
                    {{ $progress['firmados'] }}/{{ $progress['total'] }} firmados
                </span>
            </h3>
        </div>
        @if($charla->asistentes->count())
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <th style="padding:0.6rem 0.75rem;text-align:left;font-size:0.72rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">#</th>
                        <th style="padding:0.6rem 0.75rem;text-align:left;font-size:0.72rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">Nombre</th>
                        <th style="padding:0.6rem 0.75rem;text-align:left;font-size:0.72rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">Cargo</th>
                        <th style="padding:0.6rem 0.75rem;text-align:center;font-size:0.72rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">Estado</th>
                        <th style="padding:0.6rem 0.75rem;text-align:left;font-size:0.72rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">Fecha Firma</th>
                        <th style="padding:0.6rem 0.75rem;text-align:right;font-size:0.72rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($charla->asistentes as $i => $asistente)
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:0.6rem 0.75rem;font-size:0.8rem;color:var(--text-muted);">{{ $i+1 }}</td>
                        <td style="padding:0.6rem 0.75rem;">
                            <div style="display:flex;align-items:center;gap:0.6rem;">
                                <div class="avatar" style="width:28px;height:28px;font-size:0.7rem;flex-shrink:0;">{{ strtoupper(substr($asistente->usuario->name,0,1)) }}</div>
                                <span style="font-size:0.88rem;font-weight:600;">{{ $asistente->usuario->name }}</span>
                            </div>
                        </td>
                        <td style="padding:0.6rem 0.75rem;font-size:0.8rem;color:var(--text-muted);">{{ $asistente->usuario->rol->nombre ?? '—' }}</td>
                        <td style="padding:0.6rem 0.75rem;text-align:center;">
                            @if($asistente->estado === 'FIRMADO')
                                <span style="font-size:0.72rem;padding:3px 8px;border-radius:6px;background:rgba(22,163,74,0.15);color:#16a34a;font-weight:700;">
                                    <i class="bi bi-check-circle-fill"></i> Firmado
                                </span>
                            @else
                                <span style="font-size:0.72rem;padding:3px 8px;border-radius:6px;background:rgba(217,119,6,0.12);color:#d97706;font-weight:700;">Pendiente</span>
                            @endif
                        </td>
                        <td style="padding:0.6rem 0.75rem;font-size:0.8rem;color:var(--text-muted);">
                            {{ $asistente->fecha_firma ? \Carbon\Carbon::parse($asistente->fecha_firma)->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td style="padding:0.6rem 0.75rem;text-align:right;">
                            @if($asistente->estado === 'PENDIENTE' && $asistente->usuario_id === auth()->id())
                                <a href="{{ route('charlas.firmar', [$charla, $asistente]) }}"
                                   style="font-size:0.75rem;padding:4px 10px;border-radius:6px;background:rgba(0,86,179,0.15);color:#0056b3;text-decoration:none;font-weight:700;">
                                    <i class="bi bi-pen"></i> Firmar
                                </a>
                            @elseif($asistente->documento_hash)
                                <span style="font-size:0.65rem;font-family:monospace;color:var(--text-muted);"
                                    title="{{ $asistente->documento_hash }}">
                                    {{ substr($asistente->documento_hash, 0, 12) }}...
                                </span>
                            @else
                                <span style="color:var(--text-muted);">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p style="color:var(--text-muted);font-size:0.85rem;">Sin asistentes registrados</p>
        @endif
    </div>

</div>
@endsection

