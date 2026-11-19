@php
$documentState=app(\App\Services\RrhhDocumentBatteryService::class)->documentState($metadata ?? []);
$documentStatus=['validados'=>['✓','Validado'],'observados'=>['!','Requiere corrección'],'por-revisar'=>['◷','Pendiente de validar'],'sin-vincular'=>['−','Sin documento vinculado']][$documentState];
@endphp
<span class="doc-validation-badge doc-validation-{{ $documentState }}"><span class="doc-validation-icon" aria-hidden="true">{{ $documentStatus[0] }}</span><span>{{ $documentStatus[1] }}</span></span>
