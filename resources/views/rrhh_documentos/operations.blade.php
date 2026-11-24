<section class="panel" id="talana-operations" aria-labelledby="talana-operations-title">
<h3 id="talana-operations-title">Prueba en vivo con Talana</h3>
<p class="muted">1. Carga el PDF. 2. Confirma que el trabajador lo ve. 3. Solicita firma. Actualizar estado solo consulta Talana.</p>
<div class="doc-counts" aria-label="Estados de documentos">
@foreach(['preparado'=>'Preparados en SAEP','cargado'=>'Cargados sin firma','firma_solicitada'=>'Firma solicitada','firmado'=>'Firmados'] as $key=>$label)
<div><strong>{{ $preparation['states'][$key] ?? 0 }}</strong>{{ $label }}</div>
@endforeach
</div>
@if($pilotContext)
@if(!$pilotContext['windowOpen'])<p class="note">La ventana de envíos del piloto terminó. Puedes consultar el estado; nuevas cargas o firmas requieren reprogramar la prueba.</p>@endif
@php($pilotFile=$pilotContext['file'])
@php($pilotState=$pilotFile?->state ?? 'sin_pdf')
<div class="pilot-recipient"><strong>{{ $pilotContext['person']['persona_nombre'] }}</strong><span>{{ $pilotContext['person']['empresa_nombre'] }} · Contrato #{{ $pilotContext['person']['talana_id'] }}</span><span>{{ $pilotContext['name'] }}</span></div>
<p><span class="doc-state {{ $pilotState==='firmado' ? 'ACTIVA' : 'BORRADOR' }}">{{ ['sin_pdf'=>'Falta guardar el PDF','preparado'=>'Listo para cargar','cargando'=>'Carga en curso','cargado'=>'Cargado en Talana · Sin solicitud de firma','solicitando_firma'=>'Solicitud en curso','firma_solicitada'=>'Firma solicitada · Actualiza para confirmar el estado','firmado'=>'Firma confirmada','firma_rechazada'=>'Firma rechazada','firma_cancelada'=>'Solicitud cancelada','revisar_firma'=>'Solicitud por conciliar · No reenviar','revisar_carga'=>'Carga por conciliar · No reenviar','retirado_prueba'=>'Prueba retirada'][$pilotState] ?? 'Revisar estado en el historial' }}</span></p>
@if($pilotFile?->document_id)<p class="muted">Documento Talana #{{ $pilotFile->document_id }}@if($pilotFile->signature_id) · Solicitud #{{ $pilotFile->signature_id }}@endif</p>@endif
@if(auth()->user()->tieneAcceso('gestion_documental','puede_editar'))
<div class="pilot-actions">
@if($pilotState==='preparado' && $preparation['writes_enabled'] && $pilotContext['windowOpen'])
<form method="post" action="{{ route('rrhh-documentos.operation',$batch->id) }}" data-pilot-operation>@csrf<input type="hidden" name="action" value="upload"><label class="pilot-confirm"><input type="checkbox" name="confirmed" value="1" required><span>Revisé el PDF y confirmo cargarlo en la ficha del trabajador indicado. Esta acción no solicita firma.</span></label><button class="cta">1. Cargar PDF en Talana</button></form>
@elseif($pilotState==='cargado' && $preparation['writes_enabled'] && $pilotContext['windowOpen'])
<form method="post" action="{{ route('rrhh-documentos.operation',$batch->id) }}" data-pilot-operation>@csrf<input type="hidden" name="action" value="signature"><label class="pilot-confirm"><input type="checkbox" name="confirmed" value="1" required><span>El trabajador confirmó que ve el PDF. Autorizo solicitarle la firma de este documento de prueba; Talana puede notificarlo.</span></label><button class="cta">2. Solicitar firma al trabajador</button></form>
@endif
@if($pilotFile?->document_id)
<form method="post" action="{{ route('rrhh-documentos.operation',$batch->id) }}" data-pilot-operation>@csrf<input type="hidden" name="action" value="refresh"><button class="cta secondary">Actualizar estado desde Talana</button><p class="muted">Consulta únicamente. No carga archivos ni envía solicitudes.</p></form>
@endif
</div>
@endif
@else<p class="note">{{ $pilotReason }}</p>@endif
<p class="muted">Última consulta confirmada: {{ $lastStatusCheck ? \Carbon\Carbon::parse($lastStatusCheck)->timezone('America/Santiago')->format('d/m/Y H:i') : 'Todavía no se ha consultado desde este panel' }}. Los estados no se actualizan automáticamente.</p>
<p class="muted">Solicitar firma no firma por el trabajador. El retiro de documentos con solicitud de firma requiere revisar primero su cancelación.</p>
</section>
@push('styles')
<style>.doc-workspace .pilot-recipient{display:flex;flex-direction:column;gap:6px;padding:16px;border:1px solid var(--border-color,#ddd);border-radius:10px;overflow-wrap:anywhere}.doc-workspace .pilot-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:16px}.doc-workspace .pilot-actions form{min-width:0;padding:16px;border:1px solid var(--border-color,#ddd);border-radius:10px}.doc-workspace .pilot-confirm{display:flex;align-items:flex-start;gap:10px;margin-bottom:14px;font-size:14px}.doc-workspace .pilot-confirm input[type=checkbox]{width:20px;height:20px;flex:0 0 20px;margin:2px 0}.doc-workspace .pilot-actions button{white-space:normal;max-width:100%;min-height:44px}.doc-workspace .pilot-actions button:disabled{opacity:.6;cursor:wait}</style>
@endpush
@push('scripts')
<script>document.querySelectorAll('[data-pilot-operation]').forEach(form=>form.addEventListener('submit',()=>{form.querySelectorAll('button').forEach(button=>{button.disabled=true;button.textContent='Consultando Talana…';});form.setAttribute('aria-busy','true');}));</script>
@endpush
