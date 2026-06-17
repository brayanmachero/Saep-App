@extends('layouts.app')
@section('title', $rule->exists ? 'Editar Automatización Kizeo' : 'Nueva Automatización Kizeo')

@section('content')
@php
    $conditionRows = old('conditions.field')
        ? collect(old('conditions.field'))->map(fn($field, $i) => [
            'field' => $field,
            'operator' => old("conditions.operator.$i", 'equals'),
            'value' => old("conditions.value.$i", ''),
        ])->all()
        : $conditions;

    while (count($conditionRows) < 3) {
        $conditionRows[] = ['field' => '', 'operator' => 'equals', 'value' => ''];
    }
@endphp

<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">{{ $rule->exists ? 'Editar Automatización Kizeo' : 'Nueva Automatización Kizeo' }}</h2>
            <p class="page-subheading">PDF de Kizeo hacia carpetas SharePoint</p>
        </div>
        <a href="{{ route('kizeo-automations.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')

    <form method="POST" action="{{ $rule->exists ? route('kizeo-automations.update', $rule) : route('kizeo-automations.store') }}">
        @csrf
        @if($rule->exists) @method('PUT') @endif

        <div class="glass-card" style="margin-bottom:1rem;">
            <div style="display:grid;grid-template-columns:1.2fr .8fr .35fr;gap:1rem;">
                <div class="form-group">
                    <label>Nombre</label>
                    <input name="name" class="form-input" value="{{ old('name', $rule->name) }}" required maxlength="140">
                </div>
                <div class="form-group">
                    <label>Formulario Kizeo</label>
                    <select class="form-input" id="form_picker">
                        <option value="">Seleccionar</option>
                        @if($rule->form_id && !collect($forms)->contains('id', $rule->form_id))
                            <option value="{{ $rule->form_id }}" data-name="{{ $rule->form_name }}" selected>{{ $rule->form_name ?: $rule->form_id }}</option>
                        @endif
                        @foreach($forms as $form)
                            <option value="{{ $form['id'] }}" data-name="{{ $form['name'] }}" {{ old('form_id', $rule->form_id) == $form['id'] ? 'selected' : '' }}>
                                {{ $form['name'] }} ({{ $form['id'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Prioridad</label>
                    <input name="priority" type="number" min="0" max="999" class="form-input" value="{{ old('priority', $rule->priority ?? 100) }}" required>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem;">
                <div class="form-group">
                    <label>Form ID</label>
                    <input name="form_id" id="form_id" class="form-input" value="{{ old('form_id', $rule->form_id) }}" required maxlength="80">
                </div>
                <div class="form-group">
                    <label>Nombre formulario</label>
                    <input name="form_name" id="form_name" class="form-input" value="{{ old('form_name', $rule->form_name) }}" maxlength="180">
                </div>
            </div>

            <div style="display:flex;gap:1.25rem;margin-top:1rem;align-items:center;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:.45rem;margin:0;">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" {{ old('enabled', $rule->enabled ?? true) ? 'checked' : '' }}>
                    <span>Activa</span>
                </label>
                <label style="display:flex;align-items:center;gap:.45rem;margin:0;">
                    <input type="hidden" name="continue_legacy" value="0">
                    <input type="checkbox" name="continue_legacy" value="1" {{ old('continue_legacy', $rule->continue_legacy) ? 'checked' : '' }}>
                    <span>Continuar flujo legacy</span>
                </label>
            </div>
        </div>

        <div class="glass-card" style="margin-bottom:1rem;">
            <h3 style="margin:0 0 1rem;font-size:1rem;">Condiciones</h3>
            <div id="conditions-list" style="display:flex;flex-direction:column;gap:.65rem;">
                @foreach($conditionRows as $index => $condition)
                    <div class="condition-row" style="display:grid;grid-template-columns:1fr .75fr 1fr auto;gap:.65rem;align-items:center;">
                        <input name="conditions[field][]" class="form-input" value="{{ $condition['field'] ?? '' }}" placeholder="campo_kizeo">
                        <select name="conditions[operator][]" class="form-input">
                            @foreach($operators as $operator => $label)
                                <option value="{{ $operator }}" {{ ($condition['operator'] ?? 'equals') === $operator ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input name="conditions[value][]" class="form-input" value="{{ $condition['value'] ?? '' }}" placeholder="valor">
                        <button type="button" class="icon-btn" onclick="this.closest('.condition-row').remove()" title="Quitar condición">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn-ghost" style="margin-top:.75rem;" onclick="addCondition()">
                <i class="bi bi-plus"></i> Agregar condición
            </button>
        </div>

        <div class="glass-card" style="margin-bottom:1rem;">
            <h3 style="margin:0 0 1rem;font-size:1rem;">SharePoint</h3>
            <div style="display:grid;grid-template-columns:.7fr 1.3fr;gap:1rem;">
                <div class="form-group">
                    <label>Sitio</label>
                    <input name="sharepoint_site" class="form-input" value="{{ old('sharepoint_site', $rule->sharepoint_site) }}" placeholder="PDR">
                </div>
                <div class="form-group">
                    <label>Carpeta base</label>
                    <input name="sharepoint_folder" class="form-input" value="{{ old('sharepoint_folder', $rule->sharepoint_folder) }}" placeholder="Actas/Inspecciones">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem;">
                <div class="form-group">
                    <label>Plantilla carpeta</label>
                    <input name="folder_template" class="form-input" value="{{ old('folder_template', $rule->folder_template) }}" required>
                </div>
                <div class="form-group">
                    <label>Plantilla archivo</label>
                    <input name="filename_template" class="form-input" value="{{ old('filename_template', $rule->filename_template) }}" required>
                </div>
            </div>
            <div class="form-group" style="margin-top:1rem;">
                <label>Export ID</label>
                <input name="export_id" class="form-input" value="{{ old('export_id', $rule->export_id) }}" placeholder="Vacío = PDF estándar">
            </div>
        </div>

        <div class="glass-card" style="display:flex;justify-content:flex-end;gap:.75rem;">
            <a href="{{ route('kizeo-automations.index') }}" class="btn-ghost">Cancelar</a>
            <button class="btn-premium">
                <i class="bi bi-floppy-fill"></i> Guardar
            </button>
        </div>
    </form>
</div>

<template id="condition-template">
    <div class="condition-row" style="display:grid;grid-template-columns:1fr .75fr 1fr auto;gap:.65rem;align-items:center;">
        <input name="conditions[field][]" class="form-input" placeholder="campo_kizeo">
        <select name="conditions[operator][]" class="form-input">
            @foreach($operators as $operator => $label)
                <option value="{{ $operator }}">{{ $label }}</option>
            @endforeach
        </select>
        <input name="conditions[value][]" class="form-input" placeholder="valor">
        <button type="button" class="icon-btn" onclick="this.closest('.condition-row').remove()" title="Quitar condición">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</template>

<script>
document.getElementById('form_picker')?.addEventListener('change', function () {
    const option = this.options[this.selectedIndex];
    document.getElementById('form_id').value = option.value || '';
    document.getElementById('form_name').value = option.dataset.name || '';
});

function addCondition() {
    const template = document.getElementById('condition-template');
    document.getElementById('conditions-list').append(template.content.cloneNode(true));
}
</script>
@endsection
