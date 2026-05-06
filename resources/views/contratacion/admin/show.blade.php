@extends('layouts.app')
@section('title', "Postulante — {$postulante->folio}")

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <!-- Header -->
    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-person-badge-fill" style="color:#0ea5e9"></i>
                {{ $postulante->nombre }}
                <span style="font-size:1rem;font-weight:500;color:var(--text-muted);margin-left:.5rem;">
                    {{ $postulante->folio }}
                </span>
            </h2>
            <p class="page-subheading">Detalle de postulante · {{ $postulante->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a href="{{ route('contratacion.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            @if(!empty($postulante->documentosSubidos()))
            <a href="{{ route('contratacion.zip', $postulante) }}" class="btn-ghost">
                <i class="bi bi-file-zip-fill" style="color:#f59e0b;"></i> Descargar ZIP
            </a>
            <a href="{{ route('contratacion.ficha-pdf', $postulante) }}" class="btn-ghost" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill" style="color:#ef4444;"></i> Ficha PDF
            </a>
            @endif
            <form method="POST" action="{{ route('contratacion.resincronizar', $postulante) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn-ghost"
                        onclick="return confirm('¿Re-sincronizar documentos y ficha PDF en SharePoint?')"
                        title="Vuelve a subir todos los documentos y regenera la ficha PDF en SharePoint">
                    <i class="bi bi-cloud-upload" style="color:#0ea5e9;"></i> Sincronizar SharePoint
                </button>
            </form>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;">

        <!-- Columna izquierda: datos + documentos -->
        <div>
            <!-- Datos personales -->
            <div class="glass-card" style="padding:1.5rem;margin-bottom:1.5rem;">
                <h5 style="font-size:.9rem;font-weight:700;margin-bottom:1.25rem;color:var(--text-main);display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-person-lines-fill" style="color:#0ea5e9;"></i> Datos Personales
                </h5>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div>
                        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:.25rem;">Nombre</div>
                        <div style="font-weight:600;">{{ $postulante->nombre }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:.25rem;">RUT</div>
                        <div style="font-weight:600;font-family:monospace;">{{ $postulante->rut }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:.25rem;">Correo</div>
                        <div>{{ $postulante->email }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:.25rem;">Nombre Google</div>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            @if($postulante->google_avatar)
                            <img src="{{ $postulante->google_avatar }}" alt="" style="width:24px;height:24px;border-radius:50%;">
                            @endif
                            {{ $postulante->google_name }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:.25rem;">Folio</div>
                        <code style="font-weight:800;font-size:.9rem;color:#0369a1;">{{ $postulante->folio }}</code>
                    </div>
                    <div>
                        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:.25rem;">Fecha Postulación</div>
                        <div>{{ $postulante->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
            </div>

            <!-- Documentos -->
            <div class="glass-card" style="padding:1.5rem;">
                <h5 style="font-size:.9rem;font-weight:700;margin-bottom:1.25rem;color:var(--text-main);display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-folder2-open" style="color:#0ea5e9;"></i> Documentos
                    @if($postulante->documentosCompletos())
                    <span style="font-size:.72rem;background:#dcfce7;color:#166534;padding:.2rem .6rem;border-radius:6px;font-weight:700;">Completos</span>
                    @else
                    <span style="font-size:.72rem;background:#fefce8;color:#854d0e;padding:.2rem .6rem;border-radius:6px;font-weight:700;">Incompletos</span>
                    @endif
                </h5>

                @php
                $docsCampos = [
                    'carnet_frontal'            => ['label' => 'Carnet de Identidad (Frontal)',  'req' => true],
                    'carnet_reverso'            => ['label' => 'Carnet de Identidad (Reverso)',  'req' => true],
                    'certificado_afp'           => ['label' => 'Certificado AFP',                'req' => true],
                    'certificado_fonasa'        => ['label' => 'Certificado FONASA',             'req' => true],
                    'licencia_conducir_frontal' => ['label' => 'Licencia de Conducir (Frontal)', 'req' => false],
                    'licencia_conducir_reverso' => ['label' => 'Licencia de Conducir (Reverso)', 'req' => false],
                ];
                @endphp

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    @foreach($docsCampos as $campo => $info)
                    <div style="
                        border:1px solid {{ $postulante->$campo ? '#bbf7d0' : ($info['req'] ? '#fde68a' : '#e2e8f0') }};
                        border-radius:12px; padding:1rem;
                        background:{{ $postulante->$campo ? '#f0fdf4' : '#fafafa' }};
                    ">
                        <div style="font-size:.75rem;font-weight:700;color:var(--text-muted);margin-bottom:.5rem;display:flex;justify-content:space-between;align-items:center;">
                            {{ $info['label'] }}
                            @if(!$info['req'])
                            <span style="font-size:.65rem;background:#f0f7ff;color:#0369a1;padding:.15rem .4rem;border-radius:4px;">Opcional</span>
                            @endif
                        </div>

                        @if($postulante->$campo)
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;color:#166534;">
                                <i class="bi bi-check-circle-fill"></i> Subido
                            </div>
                            <a href="{{ route('contratacion.documento', [$postulante, $campo]) }}"
                               class="btn-icon" title="Descargar" style="font-size:.9rem;">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                        @else
                        <div style="font-size:.8rem;color:{{ $info['req'] ? '#92400e' : '#94a3b8' }};display:flex;align-items:center;gap:.4rem;">
                            <i class="bi bi-{{ $info['req'] ? 'clock-history' : 'dash' }}"></i>
                            {{ $info['req'] ? 'Pendiente' : 'No subido' }}
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>

                <!-- Formulario de carga/reemplazo de documentos -->
                <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border);">
                    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:1rem;display:flex;align-items:center;gap:.4rem;">
                        <i class="bi bi-cloud-arrow-up" style="color:#0ea5e9;"></i> Subir / Reemplazar Documentos
                    </div>
                    <form method="POST" action="{{ route('contratacion.update-documentos', $postulante) }}"
                          enctype="multipart/form-data">
                        @csrf
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
                            @foreach($docsCampos as $campo => $info)
                            <div>
                                <label style="font-size:.75rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.3rem;">
                                    {{ $info['label'] }}
                                    @if($postulante->$campo)
                                    <span style="font-size:.65rem;color:#0369a1;">(reemplazar)</span>
                                    @endif
                                </label>
                                <input type="file" name="{{ $campo }}"
                                       accept=".jpg,.jpeg,.png,.pdf"
                                       class="form-control form-control-sm"
                                       style="font-size:.78rem;">
                            </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn-premium" style="width:100%;justify-content:center;padding:.5rem;font-size:.82rem;">
                            <i class="bi bi-cloud-upload"></i> Guardar Documentos y Sincronizar SharePoint
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Columna derecha: estado + notas -->
        <div>
            <div class="glass-card" style="padding:1.5rem;">
                <h5 style="font-size:.9rem;font-weight:700;margin-bottom:1.25rem;color:var(--text-main);display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-sliders" style="color:#0ea5e9;"></i> Gestión RRHH
                </h5>

                <!-- Estado actual -->
                <div style="text-align:center;margin-bottom:1.5rem;">
                    <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem;">Estado actual</div>
                    <span style="
                        display:inline-block;padding:.5rem 1.25rem;border-radius:10px;
                        background:{{ $postulante->estado_color }}20;color:{{ $postulante->estado_color }};
                        font-weight:800;font-size:1rem;
                    ">{{ $postulante->estado_label }}</span>
                </div>

                <!-- Form actualizar -->
                <form method="POST" action="{{ route('contratacion.update', $postulante) }}">
                    @csrf
                    @method('PATCH')

                    <div style="margin-bottom:1rem;">
                        <label style="font-size:.8rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.4rem;">
                            Cambiar Estado
                        </label>
                        <select name="estado" class="form-select form-select-sm">
                            @foreach(\App\Models\PostulanteContratacion::estadosMap() as $key => $info)
                            <option value="{{ $key }}" {{ $postulante->estado === $key ? 'selected' : '' }}>
                                {{ $info['label'] }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div style="margin-bottom:1rem;">
                        <label style="font-size:.8rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.4rem;">
                            Observaciones internas
                        </label>
                        <textarea name="observaciones" class="form-control form-control-sm"
                            rows="5" placeholder="Notas internas de RRHH...">{{ $postulante->observaciones }}</textarea>
                    </div>

                    <button type="submit" class="btn-premium" style="width:100%;justify-content:center;padding:.55rem;font-size:.85rem;">
                        <i class="bi bi-check-lg"></i> Guardar Cambios
                    </button>
                </form>

                <!-- Línea de tiempo de estado -->
                <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border);">
                    <div style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:.75rem;">
                        Flujo de Estados
                    </div>
                    @foreach(\App\Models\PostulanteContratacion::estadosMap() as $key => $info)
                    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.5rem;">
                        <div style="
                            width:10px;height:10px;border-radius:50%;flex-shrink:0;
                            background:{{ $postulante->estado === $key ? $info['color'] : '#e2e8f0' }};
                        "></div>
                        <span style="font-size:.8rem;{{ $postulante->estado === $key ? 'font-weight:700;color:' . $info['color'] : 'color:var(--text-muted)' }}">
                            {{ $info['label'] }}
                        </span>
                        @if($postulante->estado === $key)
                        <i class="bi bi-arrow-left" style="font-size:.7rem;color:{{ $info['color'] }};margin-left:auto;"></i>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
