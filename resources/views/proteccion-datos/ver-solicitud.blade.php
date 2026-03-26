@extends('layouts.app')

@section('content')
<div style="max-width: 720px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="{{ route('proteccion-datos.index') }}" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.3rem;">
            <i class="bi bi-arrow-left"></i> Volver a Protección de Datos
        </a>
    </div>

    <div class="card-glass" style="padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
                    {{ $solicitud->numero_solicitud }}
                </h2>
                <span style="background: {{ $solicitud->color_estado }}20; color: {{ $solicitud->color_estado }}; padding: 0.3rem 0.9rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                    {{ $solicitud->nombre_estado }}
                </span>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.85rem; color: var(--text-muted);">Tipo de solicitud</div>
                <div style="font-weight: 600; color: var(--primary-color);">{{ $solicitud->nombre_tipo }}</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; padding: 1.25rem; background: var(--bg-color); border-radius: 10px;">
            <div>
                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Fecha solicitud</div>
                <div style="font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">{{ $solicitud->fecha_solicitud->format('d/m/Y H:i') }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Plazo máximo</div>
                <div style="font-weight: 600; color: {{ $solicitud->fecha_vencimiento->isPast() && in_array($solicitud->estado, ['pendiente','en_revision']) ? '#dc2626' : 'var(--text-main)' }}; margin-top: 0.2rem;">
                    {{ $solicitud->fecha_vencimiento->format('d/m/Y') }}
                    @if($solicitud->fecha_vencimiento->isPast() && in_array($solicitud->estado, ['pendiente','en_revision']))
                        <i class="bi bi-exclamation-triangle-fill" style="color: #dc2626;"></i> Vencida
                    @endif
                </div>
            </div>
            @if($solicitud->fecha_respuesta)
            <div>
                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Fecha respuesta</div>
                <div style="font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">{{ $solicitud->fecha_respuesta->format('d/m/Y H:i') }}</div>
            </div>
            @endif
            @if($solicitud->responsable)
            <div>
                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Responsable</div>
                <div style="font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">{{ $solicitud->responsable->nombre_completo }}</div>
            </div>
            @endif
        </div>

        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.5rem;">
                <i class="bi bi-chat-left-text" style="color: var(--primary-color);"></i> Descripción
            </h3>
            <div style="background: var(--bg-color); padding: 1rem 1.25rem; border-radius: 8px; color: var(--text-main); font-size: 0.9rem; line-height: 1.6;">
                {{ $solicitud->descripcion }}
            </div>
        </div>

        @if($solicitud->datos_afectados)
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.5rem;">
                <i class="bi bi-database" style="color: var(--primary-color);"></i> Datos Afectados
            </h3>
            <div style="background: var(--bg-color); padding: 1rem 1.25rem; border-radius: 8px; color: var(--text-main); font-size: 0.9rem;">
                {{ $solicitud->datos_afectados }}
            </div>
        </div>
        @endif

        @if($solicitud->respuesta)
        <div style="margin-bottom: 1.5rem; border-left: 3px solid var(--primary-color); padding-left: 1.25rem;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.5rem;">
                <i class="bi bi-reply" style="color: var(--primary-color);"></i> Respuesta
            </h3>
            <div style="color: var(--text-main); font-size: 0.9rem; line-height: 1.6;">
                {{ $solicitud->respuesta }}
            </div>
        </div>
        @endif

        @if($solicitud->motivo_rechazo)
        <div style="background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: #991b1b; margin-bottom: 0.5rem;">
                <i class="bi bi-x-circle"></i> Motivo del Rechazo
            </h3>
            <p style="color: #7f1d1d; font-size: 0.9rem; margin: 0;">{{ $solicitud->motivo_rechazo }}</p>
        </div>
        @endif

        {{-- Formulario de respuesta para admins --}}
        @if(in_array(auth()->user()->rol->codigo, ['SUPER_ADMIN', 'PREVENCIONISTA']) && in_array($solicitud->estado, ['pendiente', 'en_revision']))
        <div style="border-top: 2px solid var(--border-color); padding-top: 1.5rem; margin-top: 2rem;">
            <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem;">
                <i class="bi bi-reply-all" style="color: var(--accent-color);"></i> Responder Solicitud
            </h3>
            <form action="{{ route('proteccion-datos.responder-solicitud', $solicitud) }}" method="POST">
                @csrf
                @method('PUT')

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 600; color: var(--text-main); margin-bottom: 0.4rem; font-size: 0.85rem;">Estado</label>
                    <select name="estado" id="admin-estado" style="width: 100%; padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; background: var(--bg-color); color: var(--text-main);">
                        <option value="en_revision">En Revisión</option>
                        <option value="aprobada">Aprobada</option>
                        <option value="rechazada">Rechazada</option>
                        <option value="completada">Completada</option>
                    </select>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 600; color: var(--text-main); margin-bottom: 0.4rem; font-size: 0.85rem;">Respuesta</label>
                    <textarea name="respuesta" rows="4" required maxlength="2000"
                        style="width: 100%; padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; font-family: inherit; resize: vertical; background: var(--bg-color); color: var(--text-main);"
                        placeholder="Escriba la respuesta a la solicitud del titular..."></textarea>
                </div>

                <div id="motivo-rechazo-wrap" style="margin-bottom: 1rem; display: none;">
                    <label style="display: block; font-weight: 600; color: var(--text-main); margin-bottom: 0.4rem; font-size: 0.85rem;">Motivo del Rechazo</label>
                    <textarea name="motivo_rechazo" rows="3" maxlength="1000"
                        style="width: 100%; padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; font-family: inherit; resize: vertical; background: var(--bg-color); color: var(--text-main);"
                        placeholder="Indique el motivo por el cual se rechaza la solicitud..."></textarea>
                </div>

                <div style="text-align: right;">
                    <button type="submit" style="padding: 0.7rem 2rem; background: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer;">
                        <i class="bi bi-send"></i> Enviar Respuesta
                    </button>
                </div>
            </form>
        </div>

        <script>
        document.getElementById('admin-estado').addEventListener('change', function() {
            document.getElementById('motivo-rechazo-wrap').style.display = this.value === 'rechazada' ? 'block' : 'none';
        });
        </script>
        @endif

        <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-color); text-align: center;">
            <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                <i class="bi bi-info-circle"></i>
                Si su solicitud es rechazada o no recibe respuesta oportuna, puede recurrir ante la
                <strong>Agencia de Protección de Datos Personales</strong> (Art. 41, Ley 19.628 reformada).
            </p>
        </div>
    </div>
</div>
@endsection
