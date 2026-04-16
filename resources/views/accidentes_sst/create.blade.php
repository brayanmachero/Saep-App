@extends('layouts.app')
@section('title','Nuevo Accidente SST')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div><h2 class="page-heading">Registrar Accidente / Enfermedad Profesional</h2></div>
        <a href="{{ route('accidentes-sst.index') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('accidentes-sst.store') }}">
            @csrf

            {{-- Info del usuario que reporta --}}
            <div style="background:var(--bg-tertiary);border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem">
                <i class="bi bi-person-badge" style="font-size:1.25rem;color:var(--accent-primary)"></i>
                <div>
                    <small style="color:var(--text-muted)">Reportado por</small>
                    <div style="font-weight:600">{{ auth()->user()->name }} {{ auth()->user()->apellido_paterno ?? '' }}</div>
                </div>
            </div>

            <h4 style="margin-top:0;color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Datos del Evento</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha del Accidente <span class="required">*</span></label>
                    <input type="date" name="fecha_accidente" value="{{ old('fecha_accidente', date('Y-m-d')) }}"
                           class="form-control @error('fecha_accidente') is-invalid @enderror" required>
                    @error('fecha_accidente')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Hora del Accidente</label>
                    <input type="time" name="hora_accidente" value="{{ old('hora_accidente') }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Tipo <span class="required">*</span></label>
                    <select name="tipo" class="form-control @error('tipo') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach(['accidente_trabajo','accidente_trayecto','enfermedad_profesional','casi_accidente','incidente'] as $t)
                            <option value="{{ $t }}" {{ old('tipo') === $t ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$t)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('tipo')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Gravedad <span class="required">*</span></label>
                    <select name="gravedad" class="form-control @error('gravedad') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach(['leve','moderado','grave','fatal','sin_lesión'] as $g)
                            <option value="{{ $g }}" {{ old('gravedad') === $g ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$g)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('gravedad')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Centro de Costo <span class="required">*</span></label>
                    <select name="centro_costo_id" class="form-control @error('centro_costo_id') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id') == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('centro_costo_id')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Trabajador Afectado</label>
                    <input type="hidden" name="trabajador_data" id="trabajador_data" value="{{ old('trabajador_data') }}">
                    <div class="search-select-wrap" id="trabajador_wrap" style="position:relative">
                        <input type="text" class="form-control" id="trabajador_search" autocomplete="off"
                               placeholder="Buscar por nombre o RUT..." style="padding-right:2.5rem">
                        <i class="bi bi-chevron-down" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></i>
                        <div id="trabajador_dropdown" class="search-dropdown"></div>
                    </div>
                    <script type="application/json" id="personal_data">@json($personal)</script>
                    <small style="color:var(--text-muted);margin-top:.25rem;display:block">
                        <i class="bi bi-cloud-arrow-down"></i> Fuente: Lista Kizeo "Personal Vigente"
                    </small>
                </div>
            </div>

            {{-- Detalle del trabajador seleccionado --}}
            <div id="trabajador_info" style="display:none;background:var(--bg-tertiary);border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1rem">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem">
                    <div><small style="color:var(--text-muted)">Nombre</small><div id="info_nombre" style="font-weight:600">—</div></div>
                    <div><small style="color:var(--text-muted)">RUT</small><div id="info_rut">—</div></div>
                    <div><small style="color:var(--text-muted)">Cargo</small><div id="info_cargo">—</div></div>
                </div>
            </div>

            <div class="form-group">
                <label>Descripción del Accidente <span class="required">*</span></label>
                <textarea name="descripcion" class="form-control @error('descripcion') is-invalid @enderror"
                          rows="4" placeholder="Describir circunstancias, lugar, actividad al momento del accidente...">{{ old('descripcion') }}</textarea>
                @error('descripcion')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <h4 style="margin-top:1.5rem;color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Clasificación del Evento</h4>

            <div class="form-group">
                <label>Lesiones / Diagnóstico</label>
                <div class="tag-select-wrap" data-name="lesiones_ids[]" data-tipo="lesion"
                     data-api="{{ route('accidentes-sst.opciones.api', 'lesion') }}"
                     data-store="{{ route('accidentes-sst.opciones.store') }}">
                    <div class="tag-selected"></div>
                    <div style="position:relative">
                        <input type="text" class="form-control tag-search" placeholder="Buscar o crear lesión..." autocomplete="off">
                        <div class="tag-dropdown"></div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Causas del Accidente</label>
                <div class="tag-select-wrap" data-name="causas_ids[]" data-tipo="causa"
                     data-api="{{ route('accidentes-sst.opciones.api', 'causa') }}"
                     data-store="{{ route('accidentes-sst.opciones.store') }}">
                    <div class="tag-selected"></div>
                    <div style="position:relative">
                        <input type="text" class="form-control tag-search" placeholder="Buscar o crear causa..." autocomplete="off">
                        <div class="tag-dropdown"></div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Medidas Preventivas</label>
                <div class="tag-select-wrap" data-name="medidas_ids[]" data-tipo="medida"
                     data-api="{{ route('accidentes-sst.opciones.api', 'medida') }}"
                     data-store="{{ route('accidentes-sst.opciones.store') }}">
                    <div class="tag-selected"></div>
                    <div style="position:relative">
                        <input type="text" class="form-control tag-search" placeholder="Buscar o crear medida..." autocomplete="off">
                        <div class="tag-dropdown"></div>
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Días Perdidos</label>
                    <input type="number" name="dias_perdidos" value="{{ old('dias_perdidos', 0) }}"
                           class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label>DIAT / Folio Mutualidad</label>
                    <input type="text" name="numero_diat" value="{{ old('numero_diat') }}" class="form-control">
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('accidentes-sst.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Registrar Accidente</button>
            </div>
        </form>
    </div>
</div>

<style>
.tag-select-wrap .tag-selected { display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:.35rem; }
.tag-select-wrap .tag-badge {
    display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .6rem;
    background:var(--accent-primary, #6366f1); color:#fff; border-radius:1rem; font-size:.8rem; font-weight:500;
}
.tag-select-wrap .tag-badge button {
    background:none; border:none; color:#fff; cursor:pointer; font-size:.9rem; line-height:1; opacity:.7; padding:0;
}
.tag-select-wrap .tag-badge button:hover { opacity:1; }
.tag-dropdown {
    display:none; position:absolute; left:0; right:0; z-index:999; max-height:200px; overflow-y:auto;
    background:var(--surface-card-solid, #fff); border:1px solid var(--surface-border, #d1d5db);
    border-radius:8px; margin-top:2px; box-shadow:0 8px 24px rgba(0,0,0,.18);
}
.tag-dropdown .tag-opt {
    padding:.5rem .75rem; font-size:.85rem; cursor:pointer; transition:background .1s;
}
.tag-dropdown .tag-opt:hover { background:var(--bg-tertiary, #f3f4f6); }
.tag-dropdown .tag-opt.tag-create {
    border-top:1px solid var(--surface-border, #e5e7eb); display:flex; align-items:center; gap:.4rem; color:var(--accent-primary, #6366f1);
}
.search-dropdown {
    display:none; position:absolute; left:0; right:0; z-index:999; max-height:250px; overflow-y:auto;
    background:var(--surface-card-solid, #fff); border:1px solid var(--surface-border, #d1d5db);
    border-radius:8px; margin-top:2px; box-shadow:0 8px 24px rgba(0,0,0,.18);
}
.search-dropdown .sd-item {
    padding:.5rem .75rem; font-size:.85rem; cursor:pointer; transition:background .1s;
}
.search-dropdown .sd-item:hover { background:var(--bg-tertiary, #f3f4f6); }
.search-dropdown .sd-item .sd-rut { color:var(--text-muted); font-size:.78rem; margin-left:.5rem; }
</style>

<script>
// ── Trabajador Afectado: Searchable dropdown ──
(function() {
    const personal = JSON.parse(document.getElementById('personal_data').textContent || '[]');
    const searchInput = document.getElementById('trabajador_search');
    const dropdown = document.getElementById('trabajador_dropdown');
    const hidden = document.getElementById('trabajador_data');
    const infoPanel = document.getElementById('trabajador_info');

    function showInfo(data) {
        document.getElementById('info_nombre').textContent = data.label || '—';
        document.getElementById('info_rut').textContent = data.rut || '—';
        document.getElementById('info_cargo').textContent = data.cargo || '—';
        infoPanel.style.display = 'block';
    }

    function selectPerson(p) {
        searchInput.value = p.label + (p.rut ? ' (' + p.rut + ')' : '');
        hidden.value = JSON.stringify(p);
        showInfo(p);
        dropdown.style.display = 'none';
    }

    function renderList(query) {
        const q = query.toLowerCase();
        const filtered = q ? personal.filter(p =>
            (p.label || '').toLowerCase().includes(q) ||
            (p.rut || '').toLowerCase().includes(q) ||
            (p.cargo || '').toLowerCase().includes(q)
        ) : personal;

        if (filtered.length === 0) {
            dropdown.innerHTML = '<div style="padding:.6rem .75rem;font-size:.82rem;color:var(--text-muted)">Sin resultados para "' + query + '"</div>';
        } else {
            dropdown.innerHTML = filtered.slice(0, 50).map(p =>
                '<div class="sd-item" data-id="' + (p.id || '') + '">' +
                p.label + '<span class="sd-rut">' + (p.rut || '') + '</span></div>'
            ).join('');
        }
        dropdown.style.display = 'block';

        dropdown.querySelectorAll('.sd-item').forEach(item => {
            item.addEventListener('mousedown', e => {
                e.preventDefault();
                const match = personal.find(p => String(p.id) === item.dataset.id);
                if (match) selectPerson(match);
            });
        });
    }

    searchInput.addEventListener('input', () => renderList(searchInput.value.trim()));
    searchInput.addEventListener('focus', () => renderList(searchInput.value.trim()));
    searchInput.addEventListener('blur', () => setTimeout(() => dropdown.style.display = 'none', 200));

    // Preload if old value exists
    if (hidden.value) {
        try {
            const data = JSON.parse(hidden.value);
            searchInput.value = data.label + (data.rut ? ' (' + data.rut + ')' : '');
            showInfo(data);
        } catch(e) {}
    }
})();

// ── Tag Select Multi (searchable + create) ──
document.querySelectorAll('.tag-select-wrap').forEach(wrap => {
    const name     = wrap.dataset.name;
    const tipo     = wrap.dataset.tipo;
    const apiUrl   = wrap.dataset.api;
    const storeUrl = wrap.dataset.store;
    const search   = wrap.querySelector('.tag-search');
    const dropdown = wrap.querySelector('.tag-dropdown');
    const selBox   = wrap.querySelector('.tag-selected');
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content;
    const selected = new Map(); // id => nombre
    let timer;

    function renderTags() {
        selBox.innerHTML = '';
        selected.forEach((nombre, id) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden'; hidden.name = name; hidden.value = id;
            const badge = document.createElement('span');
            badge.className = 'tag-badge';
            badge.innerHTML = nombre + ' <button type="button" data-id="' + id + '">&times;</button>';
            badge.querySelector('button').addEventListener('click', () => { selected.delete(id); renderTags(); });
            selBox.appendChild(hidden);
            selBox.appendChild(badge);
        });
    }

    function renderDropdown(items, query) {
        let html = '';
        items.forEach(item => {
            if (!selected.has(String(item.id))) {
                html += '<div class="tag-opt" data-id="' + item.id + '" data-nombre="' + (item.nombre || '').replace(/"/g, '&quot;') + '">' + (item.nombre || '') + '</div>';
            }
        });
        if (query.length > 1 && !items.some(i => i.nombre.toLowerCase() === query.toLowerCase())) {
            html += '<div class="tag-opt tag-create" data-nombre="' + query.replace(/"/g, '&quot;') + '"><i class="bi bi-plus-circle"></i> Crear "<strong>' + query + '</strong>"</div>';
        }
        if (!html) html = '<div style="padding:.6rem .75rem;font-size:.82rem;color:var(--text-muted)">Sin resultados</div>';
        dropdown.innerHTML = html;
        dropdown.style.display = 'block';

        dropdown.querySelectorAll('.tag-opt').forEach(opt => {
            opt.addEventListener('mousedown', e => {
                e.preventDefault();
                if (opt.classList.contains('tag-create')) {
                    // Crear nueva opción via API
                    fetch(storeUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({ tipo: tipo, nombre: opt.dataset.nombre })
                    }).then(r => r.json()).then(data => {
                        const op = data.opcion || data;
                        if (op.id) { selected.set(String(op.id), op.nombre || opt.dataset.nombre); renderTags(); }
                    });
                } else {
                    selected.set(String(opt.dataset.id), opt.dataset.nombre);
                    renderTags();
                }
                search.value = '';
                dropdown.style.display = 'none';
            });
        });
    }

    function doSearch() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const q = search.value.trim();
            fetch(apiUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json()).then(items => renderDropdown(items, q)).catch(() => {});
        }, 200);
    }

    search.addEventListener('input', doSearch);
    search.addEventListener('focus', doSearch);
    search.addEventListener('blur', () => setTimeout(() => dropdown.style.display = 'none', 200));
});
</script>
@endsection
