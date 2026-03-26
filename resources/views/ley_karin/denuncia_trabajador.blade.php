@extends('layouts.app')
@section('title', 'Canal de Denuncia — Ley Karin')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-megaphone-fill" style="color:#dc2626"></i> Canal de Denuncia</h2>
            <p class="page-subheading">Ley 21.643 · Acoso laboral, acoso sexual y violencia en el trabajo</p>
        </div>
    </div>

    {{-- Marco legal --}}
    <div class="glass-card" style="border-left:4px solid #dc2626;margin-bottom:1.5rem;padding:1rem 1.25rem;">
        <div style="display:flex;gap:.75rem;align-items:flex-start;">
            <i class="bi bi-shield-lock-fill" style="font-size:1.25rem;color:#dc2626;flex-shrink:0;margin-top:.1rem;"></i>
            <div>
                <strong>Confidencialidad garantizada</strong>
                <p style="margin:.25rem 0 0;font-size:.88rem;color:var(--text-muted);line-height:1.5;">
                    Tu denuncia será tratada con <strong>estricta confidencialidad</strong> conforme a la Ley 21.643.
                    Tienes derecho a realizar esta denuncia de forma anónima si lo prefieres.
                    Solo personal autorizado tendrá acceso a este expediente.
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('ley-karin.denuncia.store') }}">
        @csrf

        {{-- Tus datos (auto-completados) --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-person-fill"></i> Tus Datos
            </h3>

            <div class="form-group" style="margin-bottom:1rem;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="checkbox" name="anonima" value="1" id="esAnonima" {{ old('anonima') ? 'checked' : '' }}
                           style="width:18px;height:18px;accent-color:#dc2626;">
                    <span><i class="bi bi-eye-slash-fill" style="color:#dc2626;"></i> <strong>Deseo realizar esta denuncia de forma anónima</strong></span>
                </label>
                <small style="display:block;margin-top:.35rem;color:var(--text-muted);padding-left:2rem;">
                    Si marcas esta opción, tus datos personales <strong>no serán registrados</strong> en el expediente.
                </small>
            </div>

            <div id="datosDenunciante" style="transition:opacity .2s;">
                <div class="glass-card" style="background:rgba(var(--primary-rgb,15,27,76),.04);padding:.85rem 1rem;margin-bottom:0;">
                    <div class="form-grid-2">
                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label style="font-size:.82rem;color:var(--text-muted);">Nombre completo</label>
                            <p style="margin:0;font-weight:600;">{{ $user->nombre_completo }}</p>
                        </div>
                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label style="font-size:.82rem;color:var(--text-muted);">RUT</label>
                            <p style="margin:0;font-weight:600;">{{ $user->rut ?? '—' }}</p>
                        </div>
                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label style="font-size:.82rem;color:var(--text-muted);">Correo electrónico</label>
                            <p style="margin:0;">{{ $user->email ?? '—' }}</p>
                        </div>
                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label style="font-size:.82rem;color:var(--text-muted);">Centro de costo</label>
                            <p style="margin:0;">{{ $user->centroCosto->nombre ?? '—' }}</p>
                        </div>
                    </div>
                    <p style="font-size:.8rem;color:var(--text-muted);margin:.5rem 0 0;">
                        <i class="bi bi-info-circle"></i> Estos datos se completan automáticamente desde tu perfil.
                    </p>
                </div>
            </div>
        </div>

        {{-- Tipo y centro de costo --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-file-earmark-text"></i> Tipo de Denuncia
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>¿Qué tipo de conducta deseas denunciar? *</label>
                    <select name="tipo" class="form-input @error('tipo') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach(\App\Models\LeyKarin::tiposMap() as $val => $lbl)
                            <option value="{{ $val }}" {{ old('tipo') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    @error('tipo') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Centro de Costo *</label>
                    <select name="centro_costo_id" class="form-input @error('centro_costo_id') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id', $user->centro_costo_id) == $cc->id ? 'selected' : '' }}>{{ $cc->nombre }}</option>
                        @endforeach
                    </select>
                    @error('centro_costo_id') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Persona denunciada --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-person-x-fill"></i> Persona Denunciada
            </h3>
            <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:1rem;">
                Si conoces los datos de la persona denunciada, completa lo siguiente. Estos campos son opcionales.
            </p>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Nombre del Denunciado</label>
                    <input type="text" name="denunciado_nombre" value="{{ old('denunciado_nombre') }}"
                           class="form-input" placeholder="Nombre completo">
                </div>
                <div class="form-group">
                    <label>Cargo del Denunciado</label>
                    <input type="text" name="denunciado_cargo" value="{{ old('denunciado_cargo') }}"
                           class="form-input" placeholder="Ej: Supervisor, Jefatura">
                </div>
            </div>
        </div>

        {{-- Descripción de los hechos --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-chat-left-text-fill"></i> Descripción de los Hechos
            </h3>
            <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:1rem;">
                Describe en detalle lo sucedido. Incluye fechas, lugares, testigos y cualquier información que consideres relevante.
            </p>
            <div class="form-group" style="margin-bottom:0;">
                <textarea name="descripcion_hechos" class="form-input @error('descripcion_hechos') is-invalid @enderror"
                          rows="8" required placeholder="Describe los hechos con el mayor detalle posible...">{{ old('descripcion_hechos') }}</textarea>
                @error('descripcion_hechos') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Información legal --}}
        <div class="glass-card" style="margin-bottom:1.5rem;border-left:4px solid var(--primary-color,#0f1b4c);">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:.75rem;font-weight:700;">
                <i class="bi bi-info-circle-fill"></i> Tus Derechos
            </h3>
            <ul style="font-size:.88rem;color:var(--text-muted);line-height:1.7;margin:0;padding-left:1.2rem;">
                <li>Tu denuncia será investigada en un plazo máximo de <strong>30 días hábiles</strong>.</li>
                <li>Se adoptarán <strong>medidas cautelares</strong> para protegerte durante la investigación.</li>
                <li>Está prohibida toda <strong>represalia</strong> contra el denunciante (Art. 211-B del Código del Trabajo).</li>
                <li>Recibirás una notificación cuando tu caso sea <strong>resuelto</strong> (si la denuncia no es anónima).</li>
            </ul>
        </div>

        {{-- Acciones --}}
        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <button type="submit" class="btn-premium" onclick="this.disabled=true;this.form.submit();">
                <i class="bi bi-shield-check"></i> Enviar Denuncia
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const anonima = document.getElementById('esAnonima');
    const datos = document.getElementById('datosDenunciante');
    function toggleAnonima() {
        datos.style.opacity = anonima.checked ? '.35' : '1';
        datos.style.pointerEvents = anonima.checked ? 'none' : 'auto';
    }
    anonima.addEventListener('change', toggleAnonima);
    toggleAnonima();
});
</script>
@endpush
@endsection
