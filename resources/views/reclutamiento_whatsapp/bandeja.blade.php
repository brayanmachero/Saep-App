@extends('layouts.app')

@section('title', 'Bandeja de Reclutamiento')

@push('styles')
<style>
    .rw-inbox { max-width:1480px; margin:0 auto; }
    .rw-inbox-header { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; margin-bottom:1rem; }
    .rw-inbox-header h1 { margin:0; color:var(--text-main); font-size:1.55rem; }
    .rw-inbox-header p { margin:.3rem 0 0; color:var(--text-muted); font-size:.9rem; }
    .rw-inbox-grid { display:grid; grid-template-columns:minmax(310px,.82fr) minmax(0,1.65fr); min-height:620px; border:1px solid var(--border-color); border-radius:8px; overflow:hidden; background:var(--card-bg); box-shadow:var(--glass-shadow); }
    .rw-inbox-list { border-right:1px solid var(--border-color); min-width:0; background:var(--card-bg); }
    .rw-inbox-list-title { display:flex; align-items:center; justify-content:space-between; gap:.7rem; padding:1rem .9rem .45rem; color:var(--text-main); font-size:.92rem; font-weight:800; }
    .rw-inbox-count { min-width:1.45rem; height:1.45rem; display:grid; place-items:center; border-radius:99px; background:#e8f5ed; color:#087443; font-size:.7rem; font-weight:800; }
    .rw-inbox-filters { padding:.9rem; border-bottom:1px solid var(--border-color); display:grid; grid-template-columns:1fr 1fr; gap:.55rem; }
    .rw-inbox-filters select { width:100%; border:1px solid var(--border-color); border-radius:6px; padding:.55rem .6rem; background:var(--card-bg); color:var(--text-main); font-size:.8rem; }
    .rw-own-inbox { align-self:center; color:var(--text-muted); font-size:.76rem; font-weight:700; }
    .rw-inbox-item { display:flex; align-items:flex-start; gap:.7rem; text-decoration:none; color:inherit; padding:.82rem .9rem; border-bottom:1px solid var(--border-color); border-left:3px solid transparent; }
    .rw-inbox-item:hover { background:rgba(37,99,235,.045); }
    .rw-inbox-item.active { border-left-color:#16a34a; background:rgba(22,163,74,.07); }
    .rw-contact-avatar { flex:0 0 2.15rem; width:2.15rem; height:2.15rem; display:grid; place-items:center; border-radius:50%; background:#e7eefc; color:#1e3a8a; font-size:.8rem; font-weight:800; }
    .rw-contact-avatar.large { flex-basis:2.65rem; width:2.65rem; height:2.65rem; font-size:.95rem; background:#e8f5ed; color:#087443; }
    .rw-inbox-item-content { min-width:0; flex:1; }
    .rw-inbox-item-top, .rw-inbox-item-bottom { display:flex; justify-content:space-between; gap:.5rem; align-items:center; }
    .rw-inbox-item strong { color:var(--text-main); font-size:.87rem; }
    .rw-inbox-item p { margin:.35rem 0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; color:var(--text-muted); font-size:.78rem; }
    .rw-inbox-item small { color:var(--text-muted); font-size:.7rem; }
    .rw-inbox-state { font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; border-radius:99px; padding:.25rem .45rem; background:#f1f5f9; color:#475569; white-space:nowrap; }
    .rw-inbox-state.nueva { background:#fff4df; color:#9a5700; }.rw-inbox-state.en_atencion,.rw-inbox-state.asignada { background:#eaf2ff; color:#1d4ed8; }.rw-inbox-state.resuelta,.rw-inbox-state.cerrada { background:#e7f8ef; color:#087443; }
    .rw-thread { display:flex; min-width:0; flex-direction:column; }
    .rw-thread-head { padding:1rem 1.1rem; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; background:var(--card-bg); }
    .rw-thread-person { display:flex; align-items:center; gap:.7rem; min-width:0; }.rw-thread-head h2 { margin:0; color:var(--text-main); font-size:1.05rem; }.rw-thread-head p { margin:.25rem 0 0; color:var(--text-muted); font-size:.8rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .rw-thread-actions { min-width:270px; display:grid; gap:.5rem; }.rw-thread-actions form { display:flex; align-items:end; gap:.35rem; }.rw-thread-actions label { flex:1; min-width:0; display:grid; gap:.18rem; color:var(--text-muted); font-size:.68rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }.rw-thread-actions select { width:100%; min-width:0; padding:.48rem; border:1px solid var(--border-color); border-radius:6px; color:var(--text-main); background:var(--card-bg); font-size:.78rem; }
    .rw-thread-actions button { border:1px solid #1e3a8a; background:#1e3a8a; color:#fff; border-radius:6px; padding:.45rem .6rem; cursor:pointer; font-size:.75rem; font-weight:700; }
    .rw-thread-body { flex:1; min-height:350px; max-height:590px; overflow-y:auto; padding:1.2rem; background:var(--bg-color); display:flex; flex-direction:column; gap:.65rem; }
    .rw-message { max-width:min(74%,620px); border:1px solid var(--border-color); border-radius:8px 8px 8px 2px; padding:.65rem .75rem; background:var(--card-bg); color:var(--text-main); font-size:.86rem; line-height:1.45; box-shadow:0 1px 1px rgba(15,23,42,.12); }
    .rw-message.outgoing { align-self:flex-end; border-radius:8px 8px 2px 8px; background:#e7f8ef; border-color:#bbf7d0; color:#064e3b; }.rw-message-meta { display:block; margin-top:.3rem; color:var(--text-muted); font-size:.68rem; }
    .rw-thread-composer { padding:1rem 1.1rem; border-top:1px solid var(--border-color); background:var(--card-bg); }.rw-thread-composer p { margin:0; color:var(--text-muted); font-size:.8rem; line-height:1.45; }.rw-thread-composer strong { color:#9a5700; }.rw-thread-composer textarea { width:100%; min-height:76px; resize:vertical; border:1px solid var(--border-color); border-radius:7px; padding:.65rem; background:var(--card-bg); color:var(--text-main); font:inherit; }
    .rw-campaign-link { display:inline-flex; align-items:center; gap:.45rem; min-height:2.2rem; border:1px solid var(--border-color); border-radius:6px; padding:.45rem .7rem; color:var(--text-main); background:var(--card-bg); font-size:.8rem; font-weight:700; text-decoration:none; }.rw-campaign-link:hover { border-color:var(--primary-color); color:var(--primary-color); }
    .rw-assignment-history { border-bottom:1px solid var(--border-color); background:var(--card-bg); }.rw-assignment-history summary { cursor:pointer; list-style:none; display:flex; align-items:center; gap:.45rem; padding:.65rem 1.1rem; color:var(--text-muted); font-size:.76rem; font-weight:800; }.rw-assignment-history summary::-webkit-details-marker { display:none; }.rw-assignment-history summary::after { content:'+'; margin-left:auto; color:var(--primary-color); font-size:1rem; }.rw-assignment-history[open] summary::after { content:'-'; }.rw-assignment-history-list { display:grid; gap:.45rem; padding:0 1.1rem .8rem; }.rw-assignment-history-item { display:flex; justify-content:space-between; gap:.8rem; color:var(--text-muted); font-size:.74rem; }.rw-assignment-history-item strong { color:var(--text-main); }
    .rw-inbox-empty { display:grid; place-items:center; min-height:460px; padding:2rem; color:var(--text-muted); text-align:center; }.rw-inbox-empty i { font-size:2rem; display:block; margin-bottom:.7rem; }
    @media (max-width: 920px) { .rw-inbox-grid { grid-template-columns:1fr; } .rw-inbox-list { border-right:0; border-bottom:1px solid var(--border-color); max-height:340px; overflow:auto; } .rw-thread-body { max-height:440px; } }
    @media (max-width: 640px) { .rw-inbox-header, .rw-thread-head { flex-direction:column; }.rw-inbox-filters { grid-template-columns:1fr; }.rw-thread-actions { width:100%; min-width:0; }.rw-message { max-width:88%; }.rw-thread-body { padding:.8rem; min-height:290px; }.rw-inbox-grid { border-radius:0; margin:0 -.7rem; }.rw-assignment-history-item { align-items:flex-start; flex-direction:column; gap:.15rem; } }
</style>
@endpush

@section('content')
<div class="page-container rw-inbox">
    @include('partials._alerts')
    <header class="rw-inbox-header">
        <div><p style="margin:0 0 .25rem;color:#16a34a;font-weight:800;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase">Recursos Humanos</p><h1><i class="bi bi-chat-square-dots" style="color:#16a34a"></i> Bandeja de Reclutamiento</h1><p>Atención asignable para respuestas de contactos de campañas y consultas entrantes.</p></div>
        <a href="{{ route('reclutamiento-whatsapp.index') }}" class="rw-campaign-link"><i class="bi bi-megaphone"></i> Campañas y contactos</a>
    </header>

    <div class="rw-inbox-grid">
        <aside class="rw-inbox-list" aria-label="Conversaciones">
            <div class="rw-inbox-list-title"><span><i class="bi bi-chat-left-dots"></i> Conversaciones</span><span class="rw-inbox-count" title="Conversaciones visibles">{{ $conversaciones->count() }}</span></div>
            <form method="GET" class="rw-inbox-filters">
                <select name="estado" aria-label="Filtrar por estado" onchange="this.form.submit()"><option value="">Todos los estados</option>@foreach(\App\Models\ReclutamientoWhatsappConversacion::ESTADOS as $estado)<option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ str_replace('_', ' ', ucfirst($estado)) }}</option>@endforeach</select>
                @if($puedeCoordinar)
                    <select name="asignada_a" aria-label="Filtrar por responsable" onchange="this.form.submit()"><option value="">Todos los responsables</option><option value="sin_asignar" @selected(request('asignada_a') === 'sin_asignar')>Sin asignar</option>@foreach($agentes as $agente)<option value="{{ $agente->id }}" @selected((string) request('asignada_a') === (string) $agente->id)>{{ $agente->nombre_completo }}</option>@endforeach</select>
                @else
                    <span class="rw-own-inbox">Mis conversaciones asignadas</span>
                @endif
            </form>
            @forelse($conversaciones as $conversacion)
                <a class="rw-inbox-item {{ $seleccionada?->id === $conversacion->id ? 'active' : '' }}" href="{{ route('reclutamiento-whatsapp.bandeja', array_filter(['conversacion' => $conversacion->id, 'estado' => request('estado'), 'asignada_a' => request('asignada_a')], fn ($value) => $value !== null && $value !== '')) }}">
                    <span class="rw-contact-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($conversacion->contacto->nombre, 0, 1)) }}</span>
                    <div class="rw-inbox-item-content"><div class="rw-inbox-item-top"><strong>{{ $conversacion->contacto->nombre }}</strong><span class="rw-inbox-state {{ $conversacion->estado }}">{{ str_replace('_', ' ', $conversacion->estado) }}</span></div>
                    <p>{{ $conversacion->ultimo_mensaje_preview ?: 'Sin contenido disponible' }}</p>
                    <div class="rw-inbox-item-bottom"><small>{{ $conversacion->asignado?->nombre_completo ?: 'Sin asignar' }}</small><small>{{ $conversacion->ultimo_mensaje_at?->format('d/m H:i') ?: 'Sin fecha' }}</small></div></div>
                </a>
            @empty
                <div class="rw-inbox-empty"><div><i class="bi bi-inbox"></i>No hay conversaciones para este filtro.</div></div>
            @endforelse
        </aside>

        <main class="rw-thread">
            @if($seleccionada)
                <div class="rw-thread-head">
                    <div class="rw-thread-person"><span class="rw-contact-avatar large" aria-hidden="true">{{ mb_strtoupper(mb_substr($seleccionada->contacto->nombre, 0, 1)) }}</span><div><h2>{{ $seleccionada->contacto->nombre }}</h2><p>{{ $seleccionada->contacto->telefono }} · {{ $seleccionada->contacto->origen_detalle ?: 'Contacto de Reclutamiento' }}</p></div></div>
                    <div class="rw-thread-actions">
                        @if($puedeCoordinar)
                        <form method="POST" action="{{ route('reclutamiento-whatsapp.conversaciones.asignar', $seleccionada) }}">@csrf @method('PATCH')<label>Responsable<select name="asignada_a" aria-label="Asignar responsable"><option value="">Sin asignar</option>@foreach($agentes as $agente)<option value="{{ $agente->id }}" @selected($seleccionada->asignada_a === $agente->id)>{{ $agente->nombre_completo }}</option>@endforeach</select></label><button type="submit" aria-label="Guardar responsable" title="Guardar responsable"><i class="bi bi-person-check"></i></button></form>
                        @endif
                        @if($puedeCoordinar || $seleccionada->asignada_a === auth()->id())
                        <form method="POST" action="{{ route('reclutamiento-whatsapp.conversaciones.estado', $seleccionada) }}">@csrf @method('PATCH')<label>Estado<select name="estado" aria-label="Actualizar estado">@foreach(\App\Models\ReclutamientoWhatsappConversacion::ESTADOS as $estado)<option value="{{ $estado }}" @selected($seleccionada->estado === $estado)>{{ str_replace('_', ' ', ucfirst($estado)) }}</option>@endforeach</select></label><button type="submit" aria-label="Guardar estado" title="Guardar estado"><i class="bi bi-check2"></i></button></form>
                        @endif
                    </div>
                </div>
                @if($puedeCoordinar)
                    <details class="rw-assignment-history">
                        <summary><i class="bi bi-clock-history"></i> Trazabilidad de asignaciones ({{ $seleccionada->asignaciones->count() }})</summary>
                        <div class="rw-assignment-history-list">
                            @forelse($seleccionada->asignaciones as $asignacion)
                                <div class="rw-assignment-history-item"><span><strong>{{ ucfirst($asignacion->accion) }}</strong>{{ $asignacion->asignado ? ' a ' . $asignacion->asignado->nombre_completo : '' }} por {{ $asignacion->asignadoPor?->nombre_completo ?: 'usuario no disponible' }}</span><span>{{ $asignacion->created_at?->format('d/m/Y H:i') }}</span></div>
                            @empty
                                <div class="rw-assignment-history-item"><span>Sin cambios de responsable registrados.</span></div>
                            @endforelse
                        </div>
                    </details>
                @endif
                <div class="rw-thread-body" aria-live="polite">
                    @forelse($seleccionada->mensajes as $mensaje)
                        <article class="rw-message {{ $mensaje->direccion === 'saliente' ? 'outgoing' : '' }}">{{ $mensaje->contenido ?: 'Contenido no compatible disponible en WhatsApp.' }}<span class="rw-message-meta">{{ $mensaje->direccion === 'saliente' ? ($mensaje->enviadoPor?->nombre_completo ?: 'Equipo de Reclutamiento') : $seleccionada->contacto->nombre }} · {{ $mensaje->ocurrido_at?->format('d/m/Y H:i') }}</span></article>
                    @empty
                        <div class="rw-inbox-empty"><div><i class="bi bi-chat-left-text"></i>La conversación quedará disponible cuando Meta entregue una respuesta entrante.</div></div>
                    @endforelse
                </div>
                <div class="rw-thread-composer">
                    @if($metaConfigurado && $seleccionada->ultimo_mensaje_entrante_at?->gte(now()->subHours(24)) && ($puedeCoordinar || $seleccionada->asignada_a === auth()->id()))
                        <form method="POST" action="{{ route('reclutamiento-whatsapp.conversaciones.responder', $seleccionada) }}">@csrf
                            <label for="rw-respuesta" class="visually-hidden">Respuesta</label><textarea id="rw-respuesta" name="contenido" maxlength="4096" required placeholder="Escribe una respuesta de Reclutamiento..."></textarea>
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:.6rem;margin-top:.55rem"><p>Respuesta directa disponible porque el contacto escribió durante las últimas 24 horas.</p><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Enviar</button></div>
                        </form>
                    @elseif($metaConfigurado)
                        <p><strong>Respuesta directa no disponible.</strong> Meta solo permite mensajes libres dentro de las 24 horas desde el último mensaje del contacto. Fuera de ese plazo se debe usar una plantilla aprobada.</p>
                    @else
                        <p><strong>Canal sin despacho.</strong> La bandeja permite asignar y clasificar conversaciones; el envío se mantiene deshabilitado hasta configurar Meta Cloud API, su webhook y la prueba controlada.</p>
                    @endif
                </div>
            @else
                <div class="rw-inbox-empty"><div><i class="bi bi-chat-square"></i>Cuando una persona responda por WhatsApp, la conversación aparecerá aquí para asignarla a un reclutador.</div></div>
            @endif
        </main>
    </div>
</div>
@endsection
