@extends('layouts.app')

@section('content')
<div style="max-width: 720px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="{{ route('proteccion-datos.index') }}" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.3rem;">
            <i class="bi bi-arrow-left"></i> Volver a Protección de Datos
        </a>
    </div>

    <div class="card-glass" style="padding: 2rem;">
        <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
            <i class="bi bi-plus-circle" style="color: var(--primary-color);"></i> Nueva Solicitud ARCO
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem;">
            Ejerza sus derechos de Acceso, Rectificación, Supresión, Oposición o Portabilidad conforme a la Ley 21.719.
        </p>

        @if($errors->any())
        <div style="background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <ul style="margin: 0; padding-left: 1.2rem; font-size: 0.9rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('proteccion-datos.guardar-solicitud') }}" method="POST">
            @csrf

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-weight: 600; color: var(--text-main); margin-bottom: 0.5rem; font-size: 0.9rem;">
                    Tipo de Solicitud <span style="color: #dc2626;">*</span>
                </label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;">
                    @php
                    $tipos = [
                        ['acceso', 'bi-eye', 'Acceso', 'Conocer qué datos personales suyos se tratan y cómo se utilizan.'],
                        ['rectificacion', 'bi-pencil-square', 'Rectificación', 'Corregir datos personales que sean inexactos o estén incompletos.'],
                        ['supresion', 'bi-trash3', 'Supresión', 'Solicitar la eliminación de datos cuando ya no sean necesarios.'],
                        ['oposicion', 'bi-hand-thumbs-down', 'Oposición', 'Oponerse al tratamiento de sus datos en ciertas circunstancias.'],
                        ['portabilidad', 'bi-box-arrow-right', 'Portabilidad', 'Recibir sus datos en formato electrónico estructurado.'],
                    ];
                    @endphp
                    @foreach($tipos as [$valor, $icono, $nombre, $desc])
                    <label style="cursor: pointer;">
                        <input type="radio" name="tipo" value="{{ $valor }}" {{ (old('tipo', request('tipo')) === $valor) ? 'checked' : '' }}
                               style="display: none;" class="tipo-radio">
                        <div class="tipo-card" style="border: 2px solid var(--border-color); border-radius: 10px; padding: 1rem; text-align: center; transition: all 0.2s;">
                            <i class="bi {{ $icono }}" style="font-size: 1.3rem; color: var(--primary-color); display: block; margin-bottom: 0.3rem;"></i>
                            <strong style="font-size: 0.85rem; color: var(--text-main); display: block;">{{ $nombre }}</strong>
                            <span style="font-size: 0.7rem; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 0.25rem;">{{ $desc }}</span>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label for="descripcion" style="display: block; font-weight: 600; color: var(--text-main); margin-bottom: 0.5rem; font-size: 0.9rem;">
                    Descripción de la solicitud <span style="color: #dc2626;">*</span>
                </label>
                <textarea name="descripcion" id="descripcion" rows="5" required maxlength="2000"
                    style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; font-family: inherit; resize: vertical; background: var(--bg-color); color: var(--text-main);"
                    placeholder="Describa en detalle su solicitud. Por ejemplo: 'Solicito acceso a todos mis datos personales almacenados en el sistema' o 'Solicito la rectificación de mi número de teléfono'">{{ old('descripcion') }}</textarea>
                <span style="font-size: 0.75rem; color: var(--text-muted);">Máximo 2000 caracteres</span>
            </div>

            <div style="margin-bottom: 2rem;">
                <label for="datos_afectados" style="display: block; font-weight: 600; color: var(--text-main); margin-bottom: 0.5rem; font-size: 0.9rem;">
                    Datos específicos afectados <span style="color: var(--text-muted); font-weight: 400;">(opcional)</span>
                </label>
                <textarea name="datos_afectados" id="datos_afectados" rows="3" maxlength="1000"
                    style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; font-family: inherit; resize: vertical; background: var(--bg-color); color: var(--text-main);"
                    placeholder="Indique los datos específicos a los que se refiere su solicitud (ej: nombre, teléfono, dirección, etc.)">{{ old('datos_afectados') }}</textarea>
            </div>

            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 1rem; margin-bottom: 2rem;">
                <p style="font-size: 0.85rem; color: #1e40af; margin: 0;">
                    <i class="bi bi-info-circle"></i>
                    <strong>Plazo de respuesta:</strong> Su solicitud será atendida en un plazo máximo de <strong>30 días hábiles</strong>
                    conforme al artículo 11 de la Ley 19.628 reformada. Si su solicitud es rechazada o no recibe respuesta,
                    puede recurrir ante la Agencia de Protección de Datos Personales.
                </p>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="{{ route('proteccion-datos.index') }}"
                   style="padding: 0.7rem 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-main); text-decoration: none; font-weight: 500; font-size: 0.9rem;">
                    Cancelar
                </a>
                <button type="submit"
                    style="padding: 0.7rem 2rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: background 0.2s;">
                    <i class="bi bi-send"></i> Enviar Solicitud
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.tipo-radio:checked + .tipo-card {
    border-color: var(--primary-color) !important;
    background: rgba(15, 27, 76, 0.05);
    box-shadow: 0 0 0 1px var(--primary-color);
}
.tipo-card:hover {
    border-color: var(--primary-hover) !important;
    transform: translateY(-1px);
}
</style>
@endsection
