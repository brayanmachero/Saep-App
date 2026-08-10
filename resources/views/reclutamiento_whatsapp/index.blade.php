@extends('layouts.app')

@section('title', 'Reclutamiento WhatsApp')

@push('styles')
<style>
    .rw-shell { max-width: 1480px; margin: 0 auto; }
    .rw-header { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; margin-bottom:1rem; }
    .rw-eyebrow { color:#16a34a; font-size:.72rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin:0 0 .25rem; }
    .rw-heading { margin:0; font-size:1.55rem; color:var(--text-main); }
    .rw-subheading { margin:.3rem 0 0; color:var(--text-muted); font-size:.9rem; }
    .rw-config { display:flex; align-items:center; gap:.45rem; border-radius:8px; padding:.55rem .75rem; font-size:.78rem; font-weight:700; white-space:nowrap; }
    .rw-config.ready { color:#087443; background:#e7f8ef; }
    .rw-config.pending { color:#9a5700; background:#fff4df; }
    .rw-stats { display:grid; grid-template-columns:repeat(5,minmax(150px,1fr)); gap:.8rem; margin:1rem 0; }
    .rw-stat { background:var(--card-bg); border:1px solid var(--border); border-left:4px solid var(--stat-color); box-shadow:var(--shadow-sm); border-radius:8px; min-height:88px; padding:.9rem 1rem; display:flex; align-items:center; gap:.7rem; }
    .rw-stat i { font-size:1.2rem; color:var(--stat-color); }
    .rw-stat strong { color:var(--text-main); font-size:1.45rem; line-height:1; display:block; }
    .rw-stat span { color:var(--text-muted); font-size:.75rem; display:block; margin-top:.28rem; }
    .rw-notice { border:1px solid #f6cf91; background:#fff8eb; color:#754600; border-radius:8px; padding:.8rem 1rem; display:flex; gap:.7rem; align-items:flex-start; font-size:.82rem; margin-bottom:1rem; }
    .rw-tabs { display:flex; gap:.35rem; border-bottom:1px solid var(--border); margin-bottom:1rem; overflow-x:auto; }
    .rw-tab { appearance:none; border:0; border-bottom:3px solid transparent; background:transparent; color:var(--text-muted); padding:.75rem .95rem; white-space:nowrap; cursor:pointer; font-size:.84rem; font-weight:700; }
    .rw-tab.active { color:#12833f; border-color:#16a34a; }
    .rw-panel { display:none; }
    .rw-panel.active { display:block; }
    .rw-grid { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(320px,.8fr); gap:1rem; align-items:start; }
    .rw-card { background:var(--card-bg); border:1px solid var(--border); border-radius:8px; box-shadow:var(--shadow-sm); }
    .rw-card-head { padding:1rem 1.15rem; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:.75rem; }
    .rw-card-head h3 { margin:0; color:var(--text-main); font-size:.96rem; }
    .rw-card-head p { margin:.2rem 0 0; color:var(--text-muted); font-size:.76rem; }
    .rw-empty { padding:2.5rem 1.25rem; text-align:center; color:var(--text-muted); font-size:.86rem; }
    .rw-empty i { display:block; font-size:1.8rem; color:#b9c2cc; margin-bottom:.55rem; }
    .rw-table-wrap { overflow-x:auto; }
    .rw-table { width:100%; border-collapse:collapse; min-width:720px; }
    .rw-table th { text-align:left; color:var(--text-muted); font-size:.67rem; text-transform:uppercase; letter-spacing:.045em; padding:.7rem .8rem; border-bottom:1px solid var(--border); background:color-mix(in srgb, var(--card-bg) 88%, #edf2f7); }
    .rw-table td { padding:.75rem .8rem; border-bottom:1px solid var(--border); color:var(--text-main); font-size:.81rem; vertical-align:middle; }
    .rw-table tr:last-child td { border-bottom:0; }
    .rw-pill { display:inline-flex; align-items:center; gap:.3rem; padding:.25rem .5rem; border-radius:999px; font-weight:800; font-size:.68rem; }
    .rw-pill.draft { background:#eef2ff; color:#4054ac; }
    .rw-pill.review { background:#fff4d8; color:#966000; }
    .rw-pill.approved { background:#e4f8ed; color:#087443; }
    .rw-pill.revoked { background:#ffebeb; color:#bd2626; }
    .rw-pill.consent { background:#e4f8ed; color:#087443; }
    .rw-muted { color:var(--text-muted); font-size:.74rem; }
    .rw-btn { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; border:1px solid transparent; border-radius:7px; padding:.56rem .75rem; cursor:pointer; text-decoration:none; font-size:.78rem; font-weight:800; }
    .rw-btn.primary { color:#fff; background:#155c35; border-color:#155c35; }
    .rw-btn.primary:hover { background:#104b2b; }
    .rw-btn.secondary { color:var(--text-main); background:var(--card-bg); border-color:var(--border); }
    .rw-btn.danger { color:#bd2626; background:#fff; border-color:#f2bcbc; }
    .rw-form { padding:1rem 1.15rem; }
    .rw-field { margin-bottom:.8rem; }
    .rw-field label { display:block; color:var(--text-muted); font-size:.69rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; margin-bottom:.32rem; }
    .rw-field input, .rw-field textarea, .rw-field select { width:100%; box-sizing:border-box; border:1px solid var(--border); border-radius:7px; background:var(--input-bg, var(--card-bg)); color:var(--text-main); padding:.62rem .68rem; font-size:.82rem; }
    .rw-field textarea { min-height:72px; resize:vertical; }
    .rw-two { display:grid; grid-template-columns:1fr 1fr; gap:.7rem; }
    .rw-checkbox { display:flex; align-items:flex-start; gap:.45rem; color:var(--text-muted); font-size:.76rem; line-height:1.35; margin:.5rem 0 .95rem; }
    .rw-checkbox input { margin-top:.15rem; accent-color:#16803d; }
    .rw-actions { display:flex; flex-wrap:wrap; gap:.55rem; }
    .rw-divider { border:0; border-top:1px solid var(--border); margin:1rem 0; }
    .rw-pagination { padding:.8rem 1rem; border-top:1px solid var(--border); }
    .rw-modal { display:none; position:fixed; inset:0; z-index:2100; background:rgba(11, 23, 40, .56); align-items:center; justify-content:center; padding:1rem; }
    .rw-modal.open { display:flex; }
    .rw-modal-content { max-width:620px; width:100%; max-height:calc(100vh - 2rem); overflow:auto; background:var(--card-bg); border-radius:8px; box-shadow:0 24px 60px rgba(0,0,0,.25); }
    .rw-modal-title { display:flex; justify-content:space-between; align-items:center; padding:1rem 1.15rem; border-bottom:1px solid var(--border); }
    .rw-modal-title h3 { margin:0; color:var(--text-main); font-size:1rem; }
    .rw-close { border:0; background:transparent; color:var(--text-muted); font-size:1.25rem; cursor:pointer; padding:.1rem .25rem; }
    @media (max-width: 920px) { .rw-stats { grid-template-columns:repeat(2, minmax(0,1fr)); } .rw-grid { grid-template-columns:1fr; } }
    @media (max-width: 640px) { .rw-header { flex-direction:column; } .rw-stats { gap:.55rem; } .rw-stat { min-height:76px; padding:.7rem; } .rw-stat strong { font-size:1.2rem; } .rw-two { grid-template-columns:1fr; } .rw-card-head { padding:.85rem; } .rw-form { padding:.85rem; } .rw-actions .rw-btn { flex:1; } }
</style>
@endpush

@section('content')
<div class="page-container rw-shell">
    @include('partials._alerts')

    <header class="rw-header">
        <div>
            <p class="rw-eyebrow">Recursos Humanos</p>
            <h1 class="rw-heading"><i class="bi bi-whatsapp" style="color:#16a34a"></i> Reclutamiento WhatsApp</h1>
            <p class="rw-subheading">Campañas, consentimiento y trazabilidad de comunicaciones de reclutamiento.</p>
        </div>
        <div class="rw-config {{ $metaConfigurado ? 'ready' : 'pending' }}" title="{{ $metaConfigurado ? 'La integración oficial está configurada.' : 'La integración está bloqueada hasta completar Meta Cloud API.' }}">
            <i class="bi {{ $metaConfigurado ? 'bi-shield-check' : 'bi-shield-exclamation' }}"></i>
            {{ $metaConfigurado ? 'Meta configurado' : 'Envíos bloqueados' }}
        </div>
    </header>

    <section class="rw-stats" aria-label="Resumen de Reclutamiento WhatsApp">
        <div class="rw-stat" style="--stat-color:#2563eb"><i class="bi bi-person-lines-fill"></i><div><strong>{{ $stats['contactos'] }}</strong><span>Contactos registrados</span></div></div>
        <div class="rw-stat" style="--stat-color:#16a34a"><i class="bi bi-patch-check-fill"></i><div><strong>{{ $stats['consentimiento_vigente'] }}</strong><span>Habilitados para campañas</span></div></div>
        <div class="rw-stat" style="--stat-color:#f59e0b"><i class="bi bi-person-exclamation"></i><div><strong>{{ $stats['pendientes_consentimiento'] }}</strong><span>Pendientes de validación</span></div></div>
        <div class="rw-stat" style="--stat-color:#dc2626"><i class="bi bi-person-x-fill"></i><div><strong>{{ $stats['bajas'] }}</strong><span>Bajas registradas</span></div></div>
        <div class="rw-stat" style="--stat-color:#7c3aed"><i class="bi bi-send"></i><div><strong>{{ $stats['campanias_borrador'] }}</strong><span>Campañas sin despacho</span></div></div>
    </section>

    @unless($metaConfigurado)
    <div class="rw-notice">
        <i class="bi bi-lock-fill" style="font-size:1rem"></i>
        <div><strong>Despacho desactivado.</strong> Se pueden registrar contactos y preparar campañas, pero no enviar mensajes. Antes del primer envío se configurarán la cuenta oficial de Meta, las plantillas aprobadas, el webhook y una prueba con destinatarios autorizados.</div>
    </div>
    @endunless

    <div class="rw-tabs" role="tablist" aria-label="Secciones de Reclutamiento WhatsApp">
        <button class="rw-tab active" type="button" role="tab" data-rw-tab="campanias"><i class="bi bi-megaphone"></i> Campañas</button>
        <button class="rw-tab" type="button" role="tab" data-rw-tab="contactos"><i class="bi bi-people"></i> Contactos y consentimiento</button>
        <button class="rw-tab" type="button" role="tab" data-rw-tab="integracion"><i class="bi bi-plug"></i> Integración</button>
    </div>

    <section class="rw-panel active" id="rw-panel-campanias" role="tabpanel">
        <div class="rw-grid">
            <article class="rw-card">
                <div class="rw-card-head">
                    <div><h3>Campañas preparadas</h3><p>Solo consideran contactos con autorización verificable, vigente y acorde a la finalidad.</p></div>
                    @if($puedeCrear)<button type="button" class="rw-btn primary" data-rw-open="campania"><i class="bi bi-plus-lg"></i> Nueva campaña</button>@endif
                </div>
                @if($campanias->isEmpty())
                    <div class="rw-empty"><i class="bi bi-megaphone"></i>Aún no hay campañas creadas.</div>
                @else
                    <div class="rw-table-wrap"><table class="rw-table"><thead><tr><th>Campaña</th><th>Plantilla Meta</th><th>Audiencia</th><th>Estado</th><th>Resultados</th><th>Creada por</th><th>Acción</th></tr></thead><tbody>
                    @foreach($campanias as $campania)
                        @php $statusClass = match($campania->estado) { 'aprobada', 'completada' => 'approved', 'pendiente_aprobacion', 'programada', 'enviando' => 'review', 'fallida' => 'revoked', default => 'draft' }; @endphp
                        <tr>
                            <td><strong>{{ $campania->nombre }}</strong><div class="rw-muted">{{ $finalidades[$campania->finalidad] ?? 'Finalidad pendiente de definir' }} · {{ $campania->created_at->format('d/m/Y H:i') }}</div></td>
                            <td><code>{{ $campania->plantilla_nombre }}</code><div class="rw-muted">{{ $campania->plantilla_idioma }} · {{ $campania->categoria }}</div></td>
                            <td>{{ number_format($campania->destinatarios_estimados) }}<div class="rw-muted">{{ $campania->programada_para ? 'congelados para despacho' : 'estimados' }}</div></td>
                            <td><span class="rw-pill {{ $statusClass }}">{{ str_replace('_', ' ', ucfirst($campania->estado)) }}</span></td>
                            <td><strong>{{ number_format($campania->enviados) }}</strong> enviados<div class="rw-muted">{{ number_format($campania->entregados) }} entregados · {{ number_format($campania->leidos) }} leídos · {{ number_format($campania->fallidos) }} fallidos</div></td>
                            <td>{{ $campania->creador?->nombre_completo ?: 'Sin usuario' }}</td>
                            <td>
                                @if($puedeEditar && $campania->estado === 'pendiente_aprobacion')
                                <form method="POST" action="{{ route('reclutamiento-whatsapp.campanias.aprobar', $campania) }}">
                                    @csrf @method('PATCH')
                                    <button class="rw-btn secondary" type="submit" title="Aprobar campaña"><i class="bi bi-check2-circle"></i> Aprobar</button>
                                </form>
                                @elseif($puedeEditar && $campania->estado === 'aprobada')
                                <form method="POST" action="{{ route('reclutamiento-whatsapp.campanias.programar', $campania) }}" class="rw-actions" style="display:grid;gap:.45rem;min-width:13rem">
                                    @csrf @method('PATCH')
                                    <label class="rw-muted" for="programada-{{ $campania->id }}">Fecha y hora</label>
                                    <input id="programada-{{ $campania->id }}" type="datetime-local" name="programada_para" min="{{ now()->format('Y-m-d\\TH:i') }}" value="{{ now()->addMinutes(5)->format('Y-m-d\\TH:i') }}" required>
                                    <button class="rw-btn primary" type="submit" @disabled(!$metaConfigurado) title="Programar despacho"><i class="bi bi-calendar-check"></i> Programar</button>
                                </form>
                                @elseif(in_array($campania->estado, ['programada', 'enviando'], true))
                                <span class="rw-muted">{{ $campania->programada_para?->format('d/m H:i') ?: 'En proceso' }}</span>
                                @elseif($campania->estado === 'completada')
                                <span class="rw-muted">Finalizada</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody></table></div>
                    @if($campanias->hasPages())<div class="rw-pagination">{{ $campanias->links() }}</div>@endif
                @endif
            </article>
            <aside class="rw-card">
                <div class="rw-card-head"><div><h3>Control previo</h3><p>Condiciones que se validarán antes de despachar.</p></div></div>
                <div class="rw-form">
                    <div class="rw-field"><label>Destinatarios</label><div class="rw-muted"><i class="bi bi-check-circle-fill" style="color:#16a34a"></i> Solo autorización verificable, no revocada, no vencida y compatible con la finalidad.</div></div>
                    <div class="rw-field"><label>Contenido</label><div class="rw-muted"><i class="bi bi-check-circle-fill" style="color:#16a34a"></i> Plantilla aprobada por Meta con variables validadas.</div></div>
                    <div class="rw-field"><label>Responsabilidad</label><div class="rw-muted"><i class="bi bi-check-circle-fill" style="color:#16a34a"></i> Borrador, solicitud de aprobación y auditoría de usuario.</div></div>
                    <hr class="rw-divider">
                    <div class="rw-muted"><i class="bi bi-shield-lock"></i> No se adjuntarán RUT, documentos ni antecedentes de postulantes en mensajes masivos.</div>
                </div>
            </aside>
        </div>
    </section>

    <section class="rw-panel" id="rw-panel-contactos" role="tabpanel">
        <div class="rw-grid">
            <article class="rw-card">
                <div class="rw-card-head">
                    <div><h3>Base propia de Reclutamiento</h3><p>Importa contactos descargados desde portales de empleo. Esta base no usa los postulantes internos de SAEP.</p></div>
                    @if($puedeCrear)<div class="rw-actions"><button type="button" class="rw-btn secondary" data-rw-open="importar"><i class="bi bi-file-earmark-arrow-up"></i> Importar CSV</button><button type="button" class="rw-btn primary" data-rw-open="contacto"><i class="bi bi-person-plus"></i> Incorporar contacto</button></div>@endif
                </div>
                <form method="GET" class="rw-form" style="padding-bottom:.3rem">
                    <div class="rw-two"><div class="rw-field"><label for="rw-search">Buscar</label><input id="rw-search" name="buscar" value="{{ request('buscar') }}" placeholder="Nombre, teléfono o correo"></div><div class="rw-field"><label for="rw-consent">Elegibilidad</label><select id="rw-consent" name="consentimiento"><option value="">Todos los estados</option><option value="vigente" @selected(request('consentimiento') === 'vigente')>Habilitado para campañas</option><option value="sin_consentimiento" @selected(request('consentimiento') === 'sin_consentimiento')>Pendiente de validación</option><option value="revocado" @selected(request('consentimiento') === 'revocado')>Baja registrada</option></select></div></div>
                    <button class="rw-btn secondary" type="submit"><i class="bi bi-funnel"></i> Aplicar filtros</button>
                </form>
                @if($contactos->isEmpty())
                    <div class="rw-empty"><i class="bi bi-person-x"></i>No hay contactos para los filtros seleccionados.</div>
                @else
                    <div class="rw-table-wrap"><table class="rw-table"><thead><tr><th>Contacto</th><th>Teléfono</th><th>Origen</th><th>Consentimiento</th><th>Registro</th><th></th></tr></thead><tbody>
                    @foreach($contactos as $contacto)
                        <tr>
                            <td><strong>{{ $contacto->nombre }}</strong><div class="rw-muted">{{ $contacto->email ?: 'Sin correo' }}</div></td>
                            <td><code>{{ $contacto->telefono }}</code></td>
                            <td>{{ str_replace('_', ' ', ucfirst($contacto->origen)) }}<div class="rw-muted">{{ $contacto->origen_detalle }}</div></td>
                            <td>
                                @if($contacto->consentimiento_revocado_at)
                                    <span class="rw-pill revoked"><i class="bi bi-slash-circle"></i> Baja</span><div class="rw-muted">{{ $contacto->consentimiento_revocado_at->format('d/m/Y') }}</div>
                                @elseif($contacto->puedeRecibirCampanias())
                                    <span class="rw-pill consent"><i class="bi bi-check2-circle"></i> Habilitado</span><div class="rw-muted">{{ $finalidades[$contacto->consentimiento_finalidad] }} · hasta {{ $contacto->retencion_hasta->format('d/m/Y') }}</div>
                                @else
                                    <span class="rw-pill review"><i class="bi bi-exclamation-circle"></i> Pendiente</span><div class="rw-muted">Falta evidencia, verificación o vigencia.</div>
                                @endif
                            </td>
                            <td>{{ $contacto->created_at->format('d/m/Y') }}</td>
                            <td>
                                @if($puedeEditar && !$contacto->consentimiento_revocado_at)
                                <form method="POST" action="{{ route('reclutamiento-whatsapp.contactos.revocar', $contacto) }}" onsubmit="return confirm('¿Registrar la baja de WhatsApp para este contacto?');">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="motivo_revocacion" value="Baja registrada por Reclutamiento">
                                    <button class="rw-btn danger" type="submit" title="Registrar baja"><i class="bi bi-person-x"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody></table></div>
                    @if($contactos->hasPages())<div class="rw-pagination">{{ $contactos->links() }}</div>@endif
                @endif
            </article>
            <aside class="rw-card"><div class="rw-card-head"><div><h3>Autorización requerida</h3><p>Antes de incorporar a una campaña.</p></div></div><div class="rw-form"><div class="rw-muted" style="line-height:1.55"><p style="margin-top:0"><strong style="color:var(--text-main)">Una base descargada no equivale a autorización</strong></p><p>Un contacto de Computrabajo, LinkedIn u otro portal puede importarse para ordenar la gestión, pero queda bloqueado para campañas hasta acreditar su autorización expresa para WhatsApp.</p><p>Para habilitarlo se registra finalidad, fecha real de aceptación, versión del aviso, referencia de evidencia, vigencia y el usuario que verificó el antecedente.</p><p>Un mensaje inequívoco de <strong>BAJA</strong>, <strong>STOP</strong>, <strong>SALIR</strong>, <strong>CANCELAR</strong> o <strong>NO CONTACTAR</strong> bloquea futuras campañas sin borrar la trazabilidad.</p></div></div></aside>
        </div>
    </section>

    <section class="rw-panel" id="rw-panel-integracion" role="tabpanel">
        <div class="rw-grid">
            <article class="rw-card"><div class="rw-card-head"><div><h3>Conexión oficial de Meta</h3><p>Estado técnico del canal de WhatsApp.</p></div></div><div class="rw-form">
                <div class="rw-field"><label>Estado</label><span class="rw-pill {{ $metaConfigurado ? 'consent' : 'review' }}">{{ $metaConfigurado ? 'Configuración detectada' : 'Pendiente de credenciales' }}</span></div>
                <div class="rw-field"><label>Webhook de Meta</label><code style="font-size:.78rem;word-break:break-all">{{ route('reclutamiento-whatsapp.webhook.verify') }}</code></div>
                <div class="rw-field"><label>Variables de producción</label><div class="rw-muted">WHATSAPP_ENABLED, WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_BUSINESS_ACCOUNT_ID, WHATSAPP_ACCESS_TOKEN, WHATSAPP_APP_SECRET y WHATSAPP_WEBHOOK_VERIFY_TOKEN.</div></div>
                @if($puedeEditar && $metaConfigurado)<form method="POST" action="{{ route('reclutamiento-whatsapp.plantillas.sincronizar') }}">@csrf<button class="rw-btn secondary" type="submit"><i class="bi bi-arrow-repeat"></i> Sincronizar plantillas Meta</button></form>@endif
            </div></article>
            <aside class="rw-card"><div class="rw-card-head"><div><h3>Secuencia de activación</h3><p>Se ejecutará una vez, con prueba controlada.</p></div></div><div class="rw-form"><ol class="rw-muted" style="padding-left:1.2rem;line-height:1.7;margin:0"><li>Crear y aprobar plantillas oficiales de Reclutamiento en Meta.</li><li>Cargar secretos solo en producción y verificar el webhook.</li><li>Incorporar una plantilla aprobada a esta sección.</li><li>Crear campaña de prueba con destinatarios autorizados.</li><li>Revisar entrega, baja y auditoría antes de habilitar campañas reales.</li></ol></div></aside>
        </div>
    </section>
</div>

@if($puedeCrear)
<div class="rw-modal" id="rw-modal-contacto" role="dialog" aria-modal="true" aria-labelledby="rw-contacto-title">
    <div class="rw-modal-content"><div class="rw-modal-title"><h3 id="rw-contacto-title">Incorporar contacto autorizado</h3><button type="button" class="rw-close" data-rw-close aria-label="Cerrar">×</button></div>
    <form method="POST" action="{{ route('reclutamiento-whatsapp.contactos.store') }}" class="rw-form">@csrf
        <div class="rw-two"><div class="rw-field"><label for="nombre">Nombre</label><input id="nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="200"></div><div class="rw-field"><label for="telefono">WhatsApp</label><input id="telefono" name="telefono" value="{{ old('telefono') }}" placeholder="+56912345678" required maxlength="30" inputmode="tel"></div></div>
        <div class="rw-field"><label for="email">Correo (opcional)</label><input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="200"></div>
        <div class="rw-two"><div class="rw-field"><label for="consentimiento_finalidad">Finalidad autorizada</label><select id="consentimiento_finalidad" name="consentimiento_finalidad" required><option value="">Selecciona una finalidad</option>@foreach($finalidades as $codigo => $etiqueta)<option value="{{ $codigo }}" @selected(old('consentimiento_finalidad') === $codigo)>{{ $etiqueta }}</option>@endforeach</select></div><div class="rw-field"><label for="consentimiento_aceptado_at">Fecha y hora de autorización</label><input id="consentimiento_aceptado_at" type="datetime-local" name="consentimiento_aceptado_at" value="{{ old('consentimiento_aceptado_at', now()->format('Y-m-d\TH:i')) }}" required></div></div>
        <div class="rw-two"><div class="rw-field"><label for="consentimiento_origen">Origen de autorización</label><input id="consentimiento_origen" name="consentimiento_origen" value="{{ old('consentimiento_origen') }}" placeholder="Formulario, llamada grabada, documento firmado..." required maxlength="120"></div><div class="rw-field"><label for="consentimiento_version">Versión del aviso</label><input id="consentimiento_version" name="consentimiento_version" value="{{ old('consentimiento_version') }}" placeholder="Ej.: RRHH-WA-2026.1" required maxlength="50"></div></div>
        <div class="rw-field"><label for="consentimiento_texto">Texto de autorización</label><textarea id="consentimiento_texto" name="consentimiento_texto" required maxlength="1500" placeholder="Registra el texto aceptado; no adjuntes documentos personales aquí.">{{ old('consentimiento_texto') }}</textarea></div>
        <div class="rw-two"><div class="rw-field"><label for="consentimiento_evidencia_ref">Referencia de evidencia</label><input id="consentimiento_evidencia_ref" name="consentimiento_evidencia_ref" value="{{ old('consentimiento_evidencia_ref') }}" placeholder="ID interno, URL o registro verificable" required maxlength="500"></div><div class="rw-field"><label for="retencion_hasta">Vigencia / retención hasta</label><input id="retencion_hasta" type="date" name="retencion_hasta" value="{{ old('retencion_hasta') }}" required></div></div>
        <label class="rw-checkbox"><input type="checkbox" name="confirma_consentimiento" value="1" @checked(old('confirma_consentimiento')) required><span>Confirmo que la autorización es expresa, verificable y compatible con la finalidad elegida. Al guardar quedará registrada mi verificación como responsable de RRHH.</span></label>
        <div class="rw-actions"><button class="rw-btn secondary" type="button" data-rw-close>Cancelar</button><button class="rw-btn primary" type="submit"><i class="bi bi-person-check"></i> Registrar contacto</button></div>
    </form></div>
</div>

<div class="rw-modal" id="rw-modal-importar" role="dialog" aria-modal="true" aria-labelledby="rw-importar-title">
    <div class="rw-modal-content"><div class="rw-modal-title"><h3 id="rw-importar-title">Importar base de portal de empleo</h3><button type="button" class="rw-close" data-rw-close aria-label="Cerrar">×</button></div>
    <form method="POST" action="{{ route('reclutamiento-whatsapp.contactos.importar') }}" enctype="multipart/form-data" class="rw-form">@csrf
        <div class="rw-notice" style="margin-bottom:.9rem"><i class="bi bi-shield-lock"></i><div>CSV con encabezado de teléfono, celular, móvil o WhatsApp. Nombre y correo son opcionales. No subas RUT, CV, documentos ni información innecesaria.</div></div>
        <div class="rw-field"><label for="rw-archivo">Archivo CSV</label><input id="rw-archivo" type="file" name="archivo" accept=".csv,text/csv" required></div>
        <div class="rw-field"><label for="rw-origen-detalle">Portal o fuente</label><input id="rw-origen-detalle" name="origen_detalle" placeholder="Ej.: Computrabajo - búsqueda auxiliar de bodega" required maxlength="160"></div>
        <label class="rw-checkbox"><input id="rw-consent-importado" type="checkbox" name="confirma_consentimiento_importado" value="1"><span>La fuente conserva autorización expresa, individual y verificable para recibir comunicaciones de Reclutamiento por WhatsApp. No marques esta opción solo porque existe una postulación.</span></label>
        <div class="rw-consent-import" hidden>
            <div class="rw-two"><div class="rw-field"><label for="rw-consent-finalidad">Finalidad autorizada</label><select id="rw-consent-finalidad" name="consentimiento_finalidad" data-rw-required-when-import><option value="">Selecciona una finalidad</option>@foreach($finalidades as $codigo => $etiqueta)<option value="{{ $codigo }}" @selected(old('consentimiento_finalidad') === $codigo)>{{ $etiqueta }}</option>@endforeach</select></div><div class="rw-field"><label for="rw-consent-aceptado-at">Fecha y hora de autorización</label><input id="rw-consent-aceptado-at" type="datetime-local" name="consentimiento_aceptado_at" value="{{ old('consentimiento_aceptado_at') }}" data-rw-required-when-import></div></div>
            <div class="rw-two"><div class="rw-field"><label for="rw-consent-origen">Origen de autorización</label><input id="rw-consent-origen" name="consentimiento_origen" placeholder="Términos del portal, formulario firmado, etc." maxlength="120" data-rw-required-when-import></div><div class="rw-field"><label for="rw-consent-version">Versión del aviso</label><input id="rw-consent-version" name="consentimiento_version" placeholder="Ej.: RRHH-WA-2026.1" maxlength="50" data-rw-required-when-import></div></div>
            <div class="rw-field"><label for="rw-consent-texto">Texto de autorización</label><textarea id="rw-consent-texto" name="consentimiento_texto" maxlength="1500" placeholder="Texto autorizado para esta base; no adjuntes CV ni documentos." data-rw-required-when-import></textarea></div>
            <div class="rw-two"><div class="rw-field"><label for="rw-consent-evidencia">Referencia de evidencia</label><input id="rw-consent-evidencia" name="consentimiento_evidencia_ref" placeholder="ID interno, URL o registro verificable" maxlength="500" data-rw-required-when-import></div><div class="rw-field"><label for="rw-retencion-hasta">Vigencia / retención hasta</label><input id="rw-retencion-hasta" type="date" name="retencion_hasta" data-rw-required-when-import></div></div>
        </div>
        <div class="rw-actions"><button class="rw-btn secondary" type="button" data-rw-close>Cancelar</button><button class="rw-btn primary" type="submit"><i class="bi bi-upload"></i> Procesar base</button></div>
    </form></div>
</div>

<div class="rw-modal" id="rw-modal-campania" role="dialog" aria-modal="true" aria-labelledby="rw-campania-title">
    <div class="rw-modal-content"><div class="rw-modal-title"><h3 id="rw-campania-title">Preparar campaña</h3><button type="button" class="rw-close" data-rw-close aria-label="Cerrar">×</button></div>
    <form method="POST" action="{{ route('reclutamiento-whatsapp.campanias.store') }}" class="rw-form">@csrf
        <div class="rw-field"><label for="campania_nombre">Nombre interno</label><input id="campania_nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="160" placeholder="Convocatoria entrevista agosto"></div>
        <div class="rw-field"><label for="campania_descripcion">Propósito</label><textarea id="campania_descripcion" name="descripcion" maxlength="1500" placeholder="Uso interno; no se envía al destinatario.">{{ old('descripcion') }}</textarea></div>
        <div class="rw-field"><label for="plantilla_id">Plantilla aprobada sincronizada</label><select id="plantilla_id" name="plantilla_id" required @disabled($plantillas->isEmpty())><option value="">Selecciona una plantilla aprobada</option>@foreach($plantillas as $plantilla)<option value="{{ $plantilla->id }}" @selected((string) old('plantilla_id') === (string) $plantilla->id)>{{ $plantilla->nombre_meta }} · {{ $plantilla->idioma }} · {{ $plantilla->categoria }}</option>@endforeach</select>@if($plantillas->isEmpty())<div class="rw-muted">Aún no hay plantillas aprobadas sincronizadas. Completa Meta y usa “Sincronizar plantillas Meta”.</div>@endif</div>
        <div class="rw-field"><label for="campania-finalidad">Finalidad de la campaña</label><select id="campania-finalidad" name="finalidad" required><option value="">Selecciona una finalidad</option>@foreach($finalidades as $codigo => $etiqueta)<option value="{{ $codigo }}" @selected(old('finalidad') === $codigo)>{{ $etiqueta }}</option>@endforeach</select></div>
        <div class="rw-notice" style="margin-bottom:.9rem"><i class="bi bi-info-circle"></i><div>La audiencia se calcula solo con contactos vigentes cuya finalidad autorizada coincide. La aprobación y la programación son pasos separados; no se envía nada desde este formulario.</div></div>
        <div class="rw-actions"><button class="rw-btn secondary" type="button" data-rw-close>Cancelar</button><button class="rw-btn secondary" name="accion" value="borrador" type="submit"><i class="bi bi-save"></i> Guardar borrador</button><button class="rw-btn primary" name="accion" value="pendiente_aprobacion" type="submit"><i class="bi bi-send-check"></i> Enviar a aprobación</button></div>
    </form></div>
</div>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const tabs = document.querySelectorAll('[data-rw-tab]');
    const activate = (key) => {
        tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.rwTab === key));
        document.querySelectorAll('.rw-panel').forEach((panel) => panel.classList.toggle('active', panel.id === `rw-panel-${key}`));
        history.replaceState(null, '', `#${key}`);
    };
    tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.rwTab)));
    const initial = location.hash.replace('#', '');
    if (['campanias', 'contactos', 'integracion'].includes(initial)) activate(initial);

    document.querySelectorAll('[data-rw-open]').forEach((button) => button.addEventListener('click', () => {
        document.getElementById(`rw-modal-${button.dataset.rwOpen}`)?.classList.add('open');
    }));
    const close = (modal) => modal.classList.remove('open');
    document.querySelectorAll('[data-rw-close]').forEach((button) => button.addEventListener('click', () => close(button.closest('.rw-modal'))));
    document.querySelectorAll('.rw-modal').forEach((modal) => modal.addEventListener('click', (event) => { if (event.target === modal) close(modal); }));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') document.querySelectorAll('.rw-modal.open').forEach(close); });
    const importedConsent = document.getElementById('rw-consent-importado');
    const importedEvidence = document.querySelector('.rw-consent-import');
    const syncImportedConsent = () => {
        if (!importedConsent || !importedEvidence) return;
        importedEvidence.hidden = !importedConsent.checked;
        importedEvidence.querySelectorAll('[data-rw-required-when-import]').forEach((field) => { field.required = importedConsent.checked; });
    };
    importedConsent?.addEventListener('change', syncImportedConsent);
    syncImportedConsent();
})();
</script>
@endpush
