@if($reserva->kizeo_entrega_sharepoint_path)
    <a class="fleet-doc-link" href="{{ route('gestion-vehiculos.reservas.acta', [$reserva, 'entrega']) }}" target="_blank" rel="noopener" title="Ver acta firmada de entrega"><i class="bi bi-file-earmark-check"></i>&nbsp; Entrega</a>
@endif
@if($reserva->kizeo_devolucion_sharepoint_path)
    <a class="fleet-doc-link" href="{{ route('gestion-vehiculos.reservas.acta', [$reserva, 'devolucion']) }}" target="_blank" rel="noopener" title="Ver acta firmada de devolución"><i class="bi bi-file-earmark-arrow-down"></i>&nbsp; Devolución</a>
@endif
@if(! $reserva->kizeo_entrega_sharepoint_path && ! $reserva->kizeo_devolucion_sharepoint_path && $reserva->kizeo_pushed_at)
    <span class="fleet-badge off"><i class="bi bi-clipboard-check"></i>&nbsp; Ficha en Kizeo</span>
    <a class="fleet-doc-link" href="{{ config('services.kizeo.web_url') }}" target="_blank" rel="noopener" title="Abrir Kizeo Forms en el navegador"><i class="bi bi-box-arrow-up-right"></i>&nbsp; Kizeo web</a>
    <span class="fleet-muted">La ficha asignada se completa en la app movil Kizeo Forms, seccion Recepcion.</span>
@elseif(! $reserva->kizeo_entrega_sharepoint_path && ! $reserva->kizeo_devolucion_sharepoint_path)
    <span class="fleet-muted">Sin actas registradas</span>
@endif
