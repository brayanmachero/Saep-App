@extends('layouts.app')
@section('title','Editar Accidente SST')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Accidente SST</h2>
            <p style="color:var(--text-muted);margin:0">Caso N° {{ $accidenteSst->numero_caso ?? $accidenteSst->id }}</p>
        </div>
        <a href="{{ route('accidentes-sst.show', $accidenteSst) }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('accidentes-sst.update', $accidenteSst) }}">
            @csrf @method('PUT')

            {{-- Info del usuario que reporta --}}
            <div style="background:var(--bg-tertiary);border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem">
                <i class="bi bi-person-badge" style="font-size:1.25rem;color:var(--accent-primary)"></i>
                <div>
                    <small style="color:var(--text-muted)">Reportado por</small>
                    <div style="font-weight:600">{{ $accidenteSst->registradoPor->name ?? auth()->user()->name }} {{ $accidenteSst->registradoPor->apellido_paterno ?? '' }}</div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha <span class="required">*</span></label>
                    <input type="date" name="fecha_accidente"
                           value="{{ old('fecha_accidente', $accidenteSst->fecha_accidente ? \Carbon\Carbon::parse($accidenteSst->fecha_accidente)->format('Y-m-d') : '') }}"
                           class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Hora</label>
                    <input type="time" name="hora_accidente" value="{{ old('hora_accidente', $accidenteSst->hora_accidente) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Tipo <span class="required">*</span></label>
                    <select name="tipo" class="form-control" required>
                        @foreach(['accidente_trabajo','accidente_trayecto','enfermedad_profesional','casi_accidente','incidente'] as $t)
                            <option value="{{ $t }}" {{ old('tipo', $accidenteSst->tipo) === $t ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$t)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Gravedad <span class="required">*</span></label>
                    <select name="gravedad" class="form-control" required>
                        @foreach(['leve','moderado','grave','fatal','sin_lesión'] as $g)
                            <option value="{{ $g }}" {{ old('gravedad', $accidenteSst->gravedad) === $g ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$g)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Centro de Costo <span class="required">*</span></label>
                    <select name="centro_costo_id" class="form-control" required>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id', $accidenteSst->centro_costo_id) == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Trabajador Afectado</label>
                    <input type="hidden" name="trabajador_data" id="trabajador_data"
                           value="{{ old('trabajador_data', $accidenteSst->trabajador_kizeo_id ? json_encode(['id' => $accidenteSst->trabajador_kizeo_id, 'label' => $accidenteSst->trabajador_nombre, 'rut' => $accidenteSst->trabajador_rut, 'cargo' => $accidenteSst->trabajador_cargo]) : '') }}">
                    <select id="trabajador_select" class="form-control">
                        <option value="">— Seleccionar Personal Vigente —</option>
                        @foreach($personal as $p)
                            <option value="{{ json_encode($p) }}"
                                    {{ $accidenteSst->trabajador_kizeo_id == $p['id'] ? 'selected' : '' }}>
                                {{ $p['label'] }} {{ $p['rut'] ? '('.$p['rut'].')' : '' }}
                            </option>
                        @endforeach
                    </select>
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
                <label>Descripción <span class="required">*</span></label>
                <textarea name="descripcion" class="form-control" rows="4" required>{{ old('descripcion', $accidenteSst->descripcion) }}</textarea>
            </div>

            <h4 style="margin-top:1.5rem;color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Clasificación del Evento</h4>

            @php
                $selLesiones = old('lesiones_ids', $accidenteSst->lesionesOpciones->pluck('id')->toArray());
                $selCausas   = old('causas_ids', $accidenteSst->causasOpciones->pluck('id')->toArray());
                $selMedidas  = old('medidas_ids', $accidenteSst->medidasOpciones->pluck('id')->toArray());
            @endphp

            <div class="form-group">
                <label>Lesiones / Diagnóstico</label>
                <div class="checkbox-grid">
                    @foreach($lesiones as $op)
                    <label class="checkbox-item">
                        <input type="checkbox" name="lesiones_ids[]" value="{{ $op->id }}"
                               {{ in_array($op->id, $selLesiones) ? 'checked' : '' }}>
                        <span>{{ $op->nombre }}</span>
                    </label>
                    @endforeach
                </div>
                @if($lesiones->isEmpty())
                <small style="color:var(--text-muted)">No hay opciones.
                    <a href="{{ route('accidentes-sst.opciones', ['tipo' => 'lesion']) }}">Configurar catálogo</a>
                </small>
                @endif
            </div>

            <div class="form-group">
                <label>Causas del Accidente</label>
                <div class="checkbox-grid">
                    @foreach($causas as $op)
                    <label class="checkbox-item">
                        <input type="checkbox" name="causas_ids[]" value="{{ $op->id }}"
                               {{ in_array($op->id, $selCausas) ? 'checked' : '' }}>
                        <span>{{ $op->nombre }}</span>
                    </label>
                    @endforeach
                </div>
                @if($causas->isEmpty())
                <small style="color:var(--text-muted)">No hay opciones.
                    <a href="{{ route('accidentes-sst.opciones', ['tipo' => 'causa']) }}">Configurar catálogo</a>
                </small>
                @endif
            </div>

            <div class="form-group">
                <label>Medidas Preventivas</label>
                <div class="checkbox-grid">
                    @foreach($medidas as $op)
                    <label class="checkbox-item">
                        <input type="checkbox" name="medidas_ids[]" value="{{ $op->id }}"
                               {{ in_array($op->id, $selMedidas) ? 'checked' : '' }}>
                        <span>{{ $op->nombre }}</span>
                    </label>
                    @endforeach
                </div>
                @if($medidas->isEmpty())
                <small style="color:var(--text-muted)">No hay opciones.
                    <a href="{{ route('accidentes-sst.opciones', ['tipo' => 'medida']) }}">Configurar catálogo</a>
                </small>
                @endif
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Días Perdidos</label>
                    <input type="number" name="dias_perdidos" value="{{ old('dias_perdidos', $accidenteSst->dias_perdidos) }}" class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        @foreach(['ingresado','aceptado','rechazado','aprobado','cerrado'] as $e)
                            <option value="{{ $e }}" {{ old('estado', $accidenteSst->estado) === $e ? 'selected' : '' }}>
                                {{ ucfirst($e) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>DIAT / Folio Mutualidad</label>
                    <input type="text" name="numero_diat" value="{{ old('numero_diat', $accidenteSst->numero_diat) }}" class="form-control">
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('accidentes-sst.show', $accidenteSst) }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<style>
.checkbox-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(250px,1fr)); gap:.5rem; margin-top:.35rem; }
.checkbox-item { display:flex; align-items:center; gap:.5rem; padding:.4rem .6rem; border-radius:.35rem; cursor:pointer; transition:background .15s; }
.checkbox-item:hover { background:var(--bg-tertiary); }
.checkbox-item input[type="checkbox"] { accent-color:var(--accent-primary); width:1rem; height:1rem; }
</style>

<script>
document.getElementById('trabajador_select').addEventListener('change', function() {
    const hidden = document.getElementById('trabajador_data');
    const info   = document.getElementById('trabajador_info');
    if (this.value) {
        hidden.value = this.value;
        const data = JSON.parse(this.value);
        document.getElementById('info_nombre').textContent = data.label || '—';
        document.getElementById('info_rut').textContent    = data.rut || '—';
        document.getElementById('info_cargo').textContent  = data.cargo || '—';
        info.style.display = 'block';
    } else {
        hidden.value = '';
        info.style.display = 'none';
    }
});
// Trigger on load if pre-selected
if (document.getElementById('trabajador_data').value) {
    document.getElementById('trabajador_select').dispatchEvent(new Event('change'));
}
</script>
@endsection
