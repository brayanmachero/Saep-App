@extends('layouts.app')

@section('title', 'Importación de Datos')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    {{-- Mostrar errores de importación --}}
    @if(session('import_errores') && count(session('import_errores')) > 0)
    <div class="glass-card" style="margin-bottom:1.5rem;border-left:4px solid var(--danger-color);">
        <h3 style="margin:0 0 .75rem;font-size:1rem;color:var(--danger-color);display:flex;align-items:center;gap:.5rem;">
            <i class="bi bi-exclamation-triangle-fill"></i> Errores durante la importación
        </h3>
        <div style="max-height:200px;overflow-y:auto;font-size:.85rem;">
            @foreach(session('import_errores') as $err)
                <div style="padding:.25rem 0;border-bottom:1px solid var(--surface-border);">{{ $err }}</div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-cloud-upload-fill" style="color:var(--primary-color)"></i> Importación de Datos</h2>
            <p class="page-subheading">Importa información masiva desde archivos CSV (formato Talana)</p>
        </div>
        <a href="{{ route('configuraciones.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Configuraciones
        </a>
    </div>

    {{-- Tarjeta de importación de usuarios --}}
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
            <i class="bi bi-people-fill"></i> Importar Usuarios
        </h3>

        <p style="color:var(--text-muted);font-size:.9rem;margin:0 0 1rem;line-height:1.5;">
            Sube un archivo CSV con los datos de los trabajadores exportados desde Talana u otro sistema de RRHH.
            Los usuarios existentes (por RUT o email) serán actualizados, los nuevos serán creados con rol <strong>Trabajador</strong>.
        </p>

        <form method="POST" action="{{ route('importacion.preview') }}" enctype="multipart/form-data" id="importForm">
            @csrf
            <input type="hidden" name="tipo" value="usuarios">

            <div class="form-group" style="margin-bottom:1rem;">
                <label id="upload-zone" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.75rem;
                    border:2px dashed var(--surface-border);border-radius:12px;padding:2rem 1.5rem;cursor:pointer;
                    transition:all .2s;background:var(--surface-bg);text-align:center;" 
                    onmouseover="this.style.borderColor='var(--primary-color)';this.style.background='rgba(15,27,76,0.03)'" 
                    onmouseout="if(!this.classList.contains('has-file')){this.style.borderColor='var(--surface-border)';this.style.background='var(--surface-bg)'}">
                    <div style="width:52px;height:52px;border-radius:12px;background:rgba(15,27,76,0.08);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-file-earmark-arrow-up-fill" style="font-size:1.5rem;color:var(--primary-color);" id="upload-icon"></i>
                    </div>
                    <div>
                        <p style="margin:0;font-weight:600;color:var(--text-main);font-size:.95rem;" id="upload-text">Seleccionar archivo CSV</p>
                        <p style="margin:.25rem 0 0;font-size:.8rem;color:var(--text-muted);">CSV separado por coma o punto y coma — Máximo 5 MB</p>
                    </div>
                    <input type="file" name="archivo" accept=".csv,.txt" id="csvFileInput"
                        class="@error('archivo') is-invalid @enderror"
                        style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                </label>
                @error('archivo') <span class="error-msg">{{ $message }}</span> @enderror
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">
                <a href="{{ route('importacion.plantilla', 'usuarios') }}" class="btn-ghost" style="font-size:.85rem;">
                    <i class="bi bi-download"></i> Descargar Plantilla CSV
                </a>
                <button type="submit" class="btn-premium" id="btnPreview" disabled style="opacity:.5;">
                    <i class="bi bi-eye-fill"></i> Previsualizar
                </button>
            </div>
        </form>
    </div>

    {{-- Información del formato --}}
    <div class="glass-card">
        <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
            <i class="bi bi-info-circle"></i> Formato del Archivo
        </h3>

        <div style="font-size:.9rem;">
            <p style="margin:0 0 .75rem;color:var(--text-muted);">
                El archivo debe contener al menos las columnas <strong>RUT</strong> (o Email) y <strong>Nombre</strong>. 
                Las demás columnas son opcionales y se mapean automáticamente:
            </p>
            <div class="glass-table-container">
                <table class="glass-table" style="font-size:.85rem;">
                    <thead>
                        <tr>
                            <th>Columna CSV</th>
                            <th>Campo</th>
                            <th>Obligatorio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>RUT / RUT Trabajador</td><td>RUT</td><td><span class="badge warning">Recomendado</span></td></tr>
                        <tr><td>Nombre / Nombres</td><td>Nombre</td><td><span class="badge success">Sí</span></td></tr>
                        <tr><td>Apellido Paterno</td><td>Apellido Paterno</td><td>No</td></tr>
                        <tr><td>Apellido Materno</td><td>Apellido Materno</td><td>No</td></tr>
                        <tr><td>Email / Correo</td><td>Correo</td><td><span class="badge warning">Recomendado</span></td></tr>
                        <tr><td>Cargo</td><td>Cargo</td><td>No</td></tr>
                        <tr><td>Departamento / Área</td><td>Departamento</td><td>No</td></tr>
                        <tr><td>Centro de Costo</td><td>Centro de Costo</td><td>No</td></tr>
                        <tr><td>Tipo Nomina</td><td>Tipo Nómina</td><td>No</td></tr>
                        <tr><td>Razon Social / Empresa</td><td>Razón Social</td><td>No</td></tr>
                        <tr><td>Fecha Nacimiento</td><td>Nacimiento</td><td>No</td></tr>
                        <tr><td>Nacionalidad</td><td>Nacionalidad</td><td>No</td></tr>
                        <tr><td>Sexo / Género</td><td>Sexo</td><td>No</td></tr>
                        <tr><td>Estado Civil</td><td>Estado Civil</td><td>No</td></tr>
                        <tr><td>Fecha Ingreso</td><td>Fecha Ingreso</td><td>No</td></tr>
                        <tr><td>Telefono / Celular</td><td>Teléfono</td><td>No</td></tr>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:1rem;padding:.75rem 1rem;background:var(--surface-bg);border-radius:10px;border-left:3px solid #f59e0b;">
                <strong style="font-size:.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;">
                    <i class="bi bi-lightbulb-fill" style="color:#f59e0b;"></i> Notas
                </strong>
                <ul style="margin:.5rem 0 0;padding-left:1.25rem;color:var(--text-muted);font-size:.85rem;line-height:1.7;">
                    <li>Si un cargo, departamento o centro de costo no existe, se creará automáticamente.</li>
                    <li>Los usuarios nuevos se crean con contraseña igual a su RUT y rol <strong>Trabajador</strong>.</li>
                    <li>Los usuarios existentes (mismos RUT o email) se actualizan con los nuevos datos.</li>
                    <li>Se soportan archivos con separador <strong>coma (,)</strong> o <strong>punto y coma (;)</strong>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('csvFileInput');
    const uploadZone = document.getElementById('upload-zone');
    const uploadText = document.getElementById('upload-text');
    const uploadIcon = document.getElementById('upload-icon');
    const btnPreview = document.getElementById('btnPreview');

    if (!fileInput || !uploadZone) return;

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            const name = this.files[0].name;
            const size = (this.files[0].size / 1024).toFixed(1);
            uploadText.textContent = name + ' (' + size + ' KB)';
            uploadIcon.className = 'bi bi-file-earmark-check-fill';
            uploadIcon.style.color = '#16a34a';
            uploadZone.style.borderColor = '#16a34a';
            uploadZone.style.background = 'rgba(22,163,106,0.04)';
            uploadZone.classList.add('has-file');
            btnPreview.disabled = false;
            btnPreview.style.opacity = '1';
        }
    });

    // Drag & drop
    ['dragenter','dragover'].forEach(function(evt) {
        uploadZone.addEventListener(evt, function(e) {
            e.preventDefault();
            uploadZone.style.borderColor = 'var(--primary-color)';
            uploadZone.style.background = 'rgba(15,27,76,0.05)';
        });
    });
    ['dragleave','drop'].forEach(function(evt) {
        uploadZone.addEventListener(evt, function(e) {
            e.preventDefault();
            if (evt === 'drop') {
                fileInput.files = e.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            } else if (!uploadZone.classList.contains('has-file')) {
                uploadZone.style.borderColor = 'var(--surface-border)';
                uploadZone.style.background = 'var(--surface-bg)';
            }
        });
    });
});
</script>
@endpush
@endsection
