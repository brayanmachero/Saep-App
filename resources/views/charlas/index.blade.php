@extends('layouts.app')

@section('title', 'Charlas SST')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <!-- Header -->
    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-megaphone-fill" style="color:#0056b3"></i> Charlas SST</h2>
            <p class="page-subheading">Gestión de charlas, capacitaciones e inducciones de seguridad</p>
        </div>
        <a href="{{ route('charlas.create') }}" class="btn-premium">
            <i class="bi bi-plus-circle-fill"></i> Nueva Charla
        </a>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
        @php
        $statsCards = [
            ['label'=>'Total','value'=>$stats['total'],'icon'=>'bi-collection-fill','color'=>'#0056b3'],
            ['label'=>'Programadas','value'=>$stats['programadas'],'icon'=>'bi-calendar-check-fill','color'=>'#d97706'],
            ['label'=>'En Curso','value'=>$stats['en_curso'],'icon'=>'bi-play-circle-fill','color'=>'#0891b2'],
            ['label'=>'Completadas','value'=>$stats['completadas'],'icon'=>'bi-check-circle-fill','color'=>'#16a34a'],
            ['label'=>'Firmados '.($stats['asistentes']>0?$stats['firmados'].'/'.$stats['asistentes']:'0'),'value'=>$stats['asistentes']>0?round($stats['firmados']/$stats['asistentes']*100).'%':'0%','icon'=>'bi-pen-fill','color'=>'#7c3aed'],
        ];
        @endphp
        @foreach($statsCards as $card)
        <div class="glass-card" style="padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:{{ $card['color'] }}20;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi {{ $card['icon'] }}" style="font-size:1.15rem;color:{{ $card['color'] }}"></i>
            </div>
            <div>
                <p style="font-size:1.4rem;font-weight:800;margin:0;color:var(--text-main);">{{ $card['value'] }}</p>
                <p style="font-size:0.72rem;color:var(--text-muted);margin:0;">{{ $card['label'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('charlas.index') }}" class="filter-form glass-card" style="margin-bottom:1.25rem;">
        <div class="filter-group">
            <label>Buscar</label>
            <input type="text" name="buscar" class="form-input" value="{{ request('buscar') }}" placeholder="Título...">
        </div>
        <div class="filter-group">
            <label>Estado</label>
            <select name="estado" class="form-input">
                <option value="">Todos</option>
                @foreach(['BORRADOR'=>'Borrador','PROGRAMADA'=>'Programada','EN_CURSO'=>'En Curso','COMPLETADA'=>'Completada','CANCELADA'=>'Cancelada'] as $val=>$lbl)
                    <option value="{{ $val }}" {{ request('estado')===$val?'selected':'' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label>Tipo</label>
            <select name="tipo" class="form-input">
                <option value="">Todos</option>
                @foreach(['CHARLA_5MIN'=>'Charla 5 Min','CAPACITACION'=>'Capacitación','INDUCCION'=>'Inducción','CHARLA_ESPECIAL'=>'Charla Especial'] as $val=>$lbl)
                    <option value="{{ $val }}" {{ request('tipo')===$val?'selected':'' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group" style="align-self:flex-end;">
            <button type="submit" class="btn-secondary"><i class="bi bi-search"></i> Buscar</button>
            <a href="{{ route('charlas.index') }}" class="btn-ghost">Limpiar</a>
        </div>
    </form>

    <!-- Table -->
    <div class="glass-card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid var(--surface-border);">
                    <th style="padding:0.85rem 1.25rem;text-align:left;font-size:0.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Charla</th>
                    <th style="padding:0.85rem 1rem;text-align:left;font-size:0.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Tipo</th>
                    <th style="padding:0.85rem 1rem;text-align:left;font-size:0.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Fecha</th>
                    <th style="padding:0.85rem 1rem;text-align:left;font-size:0.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Relator</th>
                    <th style="padding:0.85rem 1rem;text-align:center;font-size:0.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Firmas</th>
                    <th style="padding:0.85rem 1rem;text-align:left;font-size:0.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Estado</th>
                    <th style="padding:0.85rem 1.25rem;text-align:right;font-size:0.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($charlas as $charla)
                @php
                    $badge = $charla->estadoBadge;
                    $pct   = $charla->asistentes_count > 0
                        ? round($charla->firmados_count / $charla->asistentes_count * 100) : 0;
                    $tipoLabel = ['CHARLA_5MIN'=>'5 Min','CAPACITACION'=>'Capacitación','INDUCCION'=>'Inducción','CHARLA_ESPECIAL'=>'Especial'];
                    $tipoColors = ['CHARLA_5MIN'=>'#0891b2','CAPACITACION'=>'#7c3aed','INDUCCION'=>'#d97706','CHARLA_ESPECIAL'=>'#0056b3'];
                @endphp
                <tr style="border-bottom:1px solid var(--surface-border);transition:background 0.15s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.03)'"
                    onmouseout="this.style.background=''">
                    <td style="padding:0.85rem 1.25rem;">
                        <a href="{{ route('charlas.show', $charla) }}"
                           style="font-weight:700;font-size:0.9rem;color:var(--text-main);text-decoration:none;">
                            {{ $charla->titulo }}
                        </a>
                        @if($charla->lugar)
                            <span style="display:block;font-size:0.73rem;color:var(--text-muted);margin-top:2px;">
                                <i class="bi bi-geo-alt"></i> {{ $charla->lugar }}
                            </span>
                        @endif
                        @if($charla->centroCosto)
                            <span style="display:block;font-size:0.73rem;color:var(--text-muted);">
                                <i class="bi bi-building"></i> {{ $charla->centroCosto->nombre }}
                            </span>
                        @endif
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <span style="font-size:0.75rem;font-weight:700;padding:3px 8px;border-radius:6px;
                            background:{{ $tipoColors[$charla->tipo]??'#0056b3' }}20;
                            color:{{ $tipoColors[$charla->tipo]??'#0056b3' }};">
                            {{ $tipoLabel[$charla->tipo]??$charla->tipo }}
                        </span>
                        <span style="display:block;font-size:0.72rem;color:var(--text-muted);margin-top:4px;">{{ $charla->duracion_minutos }} min</span>
                    </td>
                    <td style="padding:0.85rem 1rem;font-size:0.85rem;">
                        {{ $charla->fecha_programada->format('d/m/Y') }}<br>
                        <span style="font-size:0.73rem;color:var(--text-muted);">{{ $charla->fecha_programada->format('H:i') }}</span>
                    </td>
                    <td style="padding:0.85rem 1rem;font-size:0.85rem;">
                        {{ $charla->supervisor->name ?? '—' }}
                    </td>
                    <td style="padding:0.85rem 1rem;text-align:center;">
                        @if($charla->asistentes_count > 0)
                        <div style="font-size:0.8rem;font-weight:700;margin-bottom:4px;">
                            <span style="color:#16a34a;">{{ $charla->firmados_count }}</span>
                            <span style="color:var(--text-muted);">/{{ $charla->asistentes_count }}</span>
                        </div>
                        <div style="height:5px;background:rgba(255,255,255,0.08);border-radius:99px;overflow:hidden;width:80px;margin:0 auto;">
                            <div style="height:100%;width:{{ $pct }}%;background:{{ $pct===100?'#16a34a':'#0056b3' }};border-radius:99px;"></div>
                        </div>
                        @else
                        <span style="font-size:0.75rem;color:var(--text-muted);">Sin asistentes</span>
                        @endif
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    </td>
                    <td style="padding:0.85rem 1.25rem;text-align:right;">
                        <div style="display:flex;gap:0.4rem;justify-content:flex-end;">
                            <a href="{{ route('charlas.show', $charla) }}" class="icon-btn" title="Ver detalle">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                            <a href="{{ route('charlas.edit', $charla) }}" class="icon-btn" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <a href="{{ route('pdf.charla', $charla) }}" class="icon-btn" target="_blank" title="Descargar PDF">
                                <i class="bi bi-file-earmark-pdf-fill" style="color:#dc2626;"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="padding:3rem;text-align:center;color:var(--text-muted);">
                        <i class="bi bi-megaphone" style="font-size:2.5rem;display:block;margin-bottom:0.75rem;opacity:0.4;"></i>
                        No hay charlas registradas
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($charlas->hasPages())
    <div style="padding:1rem 0;">{{ $charlas->links() }}</div>
    @endif

</div>
@endsection
