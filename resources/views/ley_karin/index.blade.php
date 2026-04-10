@extends('layouts.app')
@section('title', 'Ley Karin — Denuncias')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    @if(session('folio_generado'))
    <div class="glass-card" style="margin-bottom:1.5rem;border-left:4px solid #16a34a;padding:1rem 1.25rem;">
        <div style="display:flex;align-items:center;gap:.75rem;">
            <i class="bi bi-check-circle-fill" style="font-size:1.25rem;color:#16a34a;"></i>
            <div>
                <strong>Denuncia registrada</strong>
                <p style="margin:.15rem 0 0;font-size:.9rem;color:var(--text-muted);">
                    Folio asignado: <code style="background:rgba(15,27,76,0.08);padding:.15rem .5rem;border-radius:4px;font-weight:700;">{{ session('folio_generado') }}</code>
                    — Guárdelo para seguimiento.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Header -->
    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-shield-exclamation" style="color:#dc2626"></i> Ley Karin</h2>
            <p class="page-subheading">Denuncias por acoso laboral, sexual y violencia en el trabajo (Ley 21.643)</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a href="{{ route('ley-karin-publico.inicio') }}" target="_blank" class="btn-ghost" title="Abrir formulario público de denuncia">
                <i class="bi bi-box-arrow-up-right"></i> Formulario Público
            </a>
            @if(auth()->user()->tieneAcceso('ley_karin', 'puede_crear'))
            <a href="{{ route('ley-karin.create') }}" class="btn-premium">
                <i class="bi bi-plus-circle-fill"></i> Nueva Denuncia
            </a>
            @endif
        </div>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
        @php
        $statsCards = [
            ['label'=>'Total Casos','value'=>$stats['total'],'icon'=>'bi-folder-fill','color'=>'#0f1b4c'],
            ['label'=>'Recibidas','value'=>$stats['recibidas'],'icon'=>'bi-inbox-fill','color'=>'#0891b2'],
            ['label'=>'En Investigación','value'=>$stats['en_investigacion'],'icon'=>'bi-search','color'=>'#d97706'],
            ['label'=>'Resueltas','value'=>$stats['resueltas'],'icon'=>'bi-check-circle-fill','color'=>'#16a34a'],
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
    <form method="GET" action="{{ route('ley-karin.index') }}" class="filter-form glass-card" style="margin-bottom:1.25rem;">
        <div class="filter-group">
            <label>Buscar</label>
            <input type="text" name="buscar" class="form-input" value="{{ request('buscar') }}" placeholder="Folio, denunciante, denunciado...">
        </div>
        <div class="filter-group">
            <label>Tipo</label>
            <select name="tipo" class="form-input">
                <option value="">Todos</option>
                @foreach(\App\Models\LeyKarin::tiposMap() as $val => $lbl)
                    <option value="{{ $val }}" {{ request('tipo') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label>Estado</label>
            <select name="estado" class="form-input">
                <option value="">Todos</option>
                @foreach(\App\Models\LeyKarin::estadosMap() as $val => $lbl)
                    <option value="{{ $val }}" {{ request('estado') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label>Centro de Costo</label>
            <select name="centro_costo_id" class="form-input">
                <option value="">Todos</option>
                @foreach($centros as $cc)
                    <option value="{{ $cc->id }}" {{ request('centro_costo_id') == $cc->id ? 'selected' : '' }}>{{ $cc->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:.5rem;align-items:flex-end;">
            <button type="submit" class="btn-premium" style="padding:.6rem 1rem;"><i class="bi bi-funnel-fill"></i> Filtrar</button>
            @if(request()->hasAny(['buscar','tipo','estado','centro_costo_id']))
                <a href="{{ route('ley-karin.index') }}" class="btn-ghost" style="padding:.6rem .75rem;"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>

    <!-- Table -->
    <div class="glass-card">
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Centro</th>
                        <th>Denunciante</th>
                        <th>Estado</th>
                        <th style="text-align:center;">Conf.</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($casos as $caso)
                    <tr>
                        <td>
                            <code style="background:rgba(15,27,76,0.06);padding:.15rem .4rem;border-radius:4px;font-weight:600;font-size:.8rem;">
                                {{ $caso->folio }}
                            </code>
                        </td>
                        <td style="white-space:nowrap;">{{ $caso->fecha_denuncia->format('d/m/Y') }}</td>
                        <td><span class="{{ $caso->tipoBadge['class'] }}">{{ $caso->tipoBadge['label'] }}</span></td>
                        <td>{{ $caso->centroCosto->nombre ?? '—' }}</td>
                        <td>
                            @if($caso->anonima)
                                <span style="color:var(--text-muted);font-style:italic;"><i class="bi bi-incognito"></i> Anónima</span>
                            @else
                                {{ $caso->denunciante->name ?? $caso->denunciante_nombre ?? '—' }}
                            @endif
                        </td>
                        <td><span class="{{ $caso->estadoBadge['class'] }}">{{ $caso->estadoBadge['label'] }}</span></td>
                        <td style="text-align:center;">
                            @if($caso->confidencial)
                                <i class="bi bi-lock-fill" style="color:#dc2626;" title="Confidencial"></i>
                            @else
                                <span style="color:var(--text-muted);">—</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex;gap:.25rem;justify-content:center;">
                                <a href="{{ route('ley-karin.show', $caso) }}" class="icon-btn" title="Ver expediente">
                                    <i class="bi bi-folder2-open"></i>
                                </a>
                                @if(auth()->user()->tieneAcceso('ley_karin', 'puede_editar'))
                                <a href="{{ route('ley-karin.edit', $caso) }}" class="icon-btn" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
                            <i class="bi bi-shield-exclamation" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.3;"></i>
                            No hay denuncias registradas.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($casos->hasPages())
        <div style="padding:1rem 0;">{{ $casos->links() }}</div>
        @endif
    </div>
</div>
@endsection
