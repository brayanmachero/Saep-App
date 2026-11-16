@extends('layouts.app')
@section('title','Baterías documentales EST')
@include('rrhh_documentos.style')
@push('styles')
<style>
.est-view{max-width:none;min-width:0}.est-view *{box-sizing:border-box}.est-view .panel{margin:12px 0}.est-view .est-controls{display:flex;align-items:end;gap:12px;flex-wrap:wrap}.est-controls label{flex:1 1 210px}.est-view label{margin:0}.est-view label :is(input,select,textarea){display:block;margin-top:5px}.est-view :is(select,textarea,input,.cta){min-height:42px}.est-view :focus-visible{outline:3px solid #458bef;outline-offset:2px}.est-view a{color:var(--text-primary);text-decoration:underline;text-underline-offset:3px}.est-view .cta{font-weight:600}.est-view .est-row{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr) auto;gap:12px;align-items:center;border-top:1px solid var(--border-color);padding:9px 0;font-size:13px;overflow-wrap:anywhere}.est-view .muted{color:var(--text-muted)}.est-row small{display:block;margin-top:3px;color:var(--text-muted)}.est-view .est-row a{padding:8px}.est-view .est-docs{max-height:27rem;overflow:auto;scrollbar-gutter:stable}.est-view details summary{padding:5px 0}.est-view .note{margin:10px 0}.est-view .est-pending{color:#875400}.dark-mode .est-view .est-pending{color:#ffd286}.est-view .est-match{color:#176348}.dark-mode .est-view .est-match{color:#9be5bf}.est-view .fields>*{min-width:0}.est-view input,.est-view textarea{max-width:100%}.est-view .est-results{overflow-wrap:anywhere}.est-view .cta{width:auto}.est-view .est-nav{display:flex;gap:16px;margin:10px 0;flex-wrap:wrap}.est-view section{scroll-margin-top:5rem}
@media(max-width:700px){.est-view .est-row{grid-template-columns:minmax(0,1fr) auto;gap:4px 12px}.est-row .est-title{grid-column:1/-1}.est-view .est-controls{align-items:stretch}.est-controls label{flex-basis:100%}.est-view .est-docs{max-height:none;overflow:visible}.est-view .cta{min-height:44px}.est-view .fields{grid-template-columns:1fr}}

.est-view h2{font-size:18px;line-height:1.4;font-weight:650;margin-bottom:14px}
.est-view .est-results-meta{font-size:12px;color:var(--text-muted,#64748b);margin:14px 0 10px;line-height:1.5}
.est-view .est-results{list-style:none;padding:0;margin:0;border:1px solid var(--border-color,#dce3ed);border-radius:10px;max-height:420px;overflow:auto;scrollbar-gutter:stable}
.est-view .est-results li{padding:11px 14px;font-size:13px;line-height:1.5;overflow-wrap:anywhere;border-bottom:1px solid var(--border-color,#dce3ed)}
.est-view .est-results li:last-child{border-bottom:0}
.est-view .est-results li:nth-child(even){background:var(--hover-bg,#f8fafc)}
.est-view .est-pagination{justify-content:flex-end;border-top:1px solid var(--border-color,#dce3ed);padding-top:12px;margin-top:16px;gap:8px}
.est-view .est-pagination a{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:8px 16px;border:1px solid var(--border-color,#dce3ed);border-radius:8px;background:var(--card-bg,#fff);font-size:12px;font-weight:600;text-decoration:none}
.est-view .est-pagination a:hover{background:var(--hover-bg,#f1f5f9)}
@media(max-width:640px){.est-view h2{font-size:17px}.est-view .est-results li{padding:10px 12px;font-size:12px}.est-view .est-pagination a{min-height:44px}}
.est-view .talana-meta{display:block;font-size:12px;font-weight:400;color:var(--text-muted,#64748b);margin-top:4px;line-height:1.6}
</style>
@endpush
@section('content')
<div class="gdm est-view">
<a href="{{ route('rrhh-documentos.index') }}">← Gestión documental</a>
@if($errors->any())<div class="panel err" role="alert"><strong>Revisa los datos</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
<section class="panel">
<form method="get" class="est-controls"><label>Cliente o centro de la hoja EST
<select name="grupo">@foreach(array_keys($groups) as $option)<option @selected($option === $group)>{{ $option }}</option>@endforeach</select></label><button class="cta">Ver batería</button></form>
<nav class="est-nav" aria-label="Acciones de la batería"><a href="#talana">Consultar Talana</a>@if(auth()->user()->tieneAcceso('gestion_documental','puede_crear'))<a href="#guardar">Preparar versión</a>@endif</nav>
</section>
<section class="panel"><h2>{{ $group }}</h2>
<p class="muted">{{ count($documents) }} referencias · {{ count(array_intersect_key($matches,$documents)) }} con coincidencia de nombre · {{ count(array_diff_key($documents,$matches)) }} pendientes de validar</p>
@if($group === 'Unimarc EST')<p class="note">Unimarc repite documentos de la base con nombres distintos. Revisa los posibles duplicados antes de guardar.</p>@endif
@if(str_starts_with($group,'IDL'))<p class="note">Confirma la vigencia del pacto de horas extras. La hoja indica actualización en enero.</p>@endif
<p class="muted">Hoja EST de octubre de 2025 · Comparación de nombres del 13/11/2026</p>
<div class="est-docs" role="region" aria-label="Documentos de la batería" tabindex="0">
@foreach($documents as $name => $sources)
<div class="est-row"><div class="est-title"><strong>{{ $name }}</strong><small>{{ implode(' · ', $sources) }}</small></div>
@if(isset($matches[$name]))<div class="est-match">Coincide el nombre<small>{{ $matches[$name] }}</small></div>@else<span class="est-pending">Pendiente de validar</span>@endif
<a aria-label="Buscar {{ $name }} en Talana" href="{{ route('rrhh-documentos.est',['grupo'=>$group,'buscar'=>$name]) }}#talana">Buscar</a></div>
@endforeach</div></section>
<section class="panel" id="talana"><h2>Consultar Talana</h2>
<form class="est-controls" method="get" action="{{ route('rrhh-documentos.est') }}#talana"><input type="hidden" name="grupo" value="{{ $group }}"><label>Nombre del documento <input type="search" name="buscar" required minlength="2" maxlength="255" value="{{ $term }}"></label><label>Orden en esta página<select name="orden">@foreach(['recent'=>'Más recientes','oldest'=>'Más antiguos','name'=>'Nombre A–Z'] as $value=>$label)<option value="{{ $value }}" @selected($order === $value)>{{ $label }}</option>@endforeach</select></label><button class="cta">Buscar en Talana</button></form>
@if($error)<p role="alert">{{ $error }}</p>@elseif($total !== null)
@if((int) $total === 0)<p>Sin resultados para «{{ $term }}». Prueba con menos palabras.</p>@else
<p class="est-results-meta">{{ number_format($total,0,',','.') }} registros · {{ count($names) }} {{ count($names) === 1 ? 'título distinto' : 'títulos distintos' }} en la página {{ $page }}</p>
<ul class="est-results" tabindex="0" aria-label="Títulos encontrados en Talana">@foreach($names as $document)<li><strong>{{ $document['name'] }}</strong><small class="talana-meta">Último registro observado · {{ $document['date'] ?? 'Fecha no informada' }} · Versión en el título · {{ $document['version'] ?? 'No identificada' }}</small></li>@endforeach</ul>@endif
<p class="muted">Fechas de creación en Talana, hora de Chile. Se agrupan y ordenan los registros de esta página. La vigencia de la plantilla requiere revisión.</p><nav class="est-nav est-pagination" aria-label="Resultados de Talana">@if($page > 1)<a href="{{ request()->fullUrlWithQuery(['pagina'=>$page-1]) }}#talana">Anterior</a>@endif
@if($next)<a href="{{ request()->fullUrlWithQuery(['pagina'=>$page+1]) }}#talana">Siguiente</a>@endif</nav>
@endif</section>
@if(auth()->user()->tieneAcceso('gestion_documental','puede_crear'))
<section class="panel" id="guardar"><h2>Guardar batería revisada</h2>
<form method="post" action="{{ route('rrhh-documentos.battery') }}">@csrf
<input type="hidden" name="origen_est" value="1">
<div class="fields">
<label>Nombre <input required name="nombre" maxlength="150" value="{{ old('nombre','EST · '.$group) }}"></label>
<label>Centro de costo en Talana <select required name="centro_costo_nombre"><option value="">Elegir centro</option>@foreach($centres as $centre)<option @selected(old('centro_costo_nombre') === $centre)>{{ $centre }}</option>@endforeach</select></label>
<label>Cargo (opcional) <input name="cargo_nombre" maxlength="200" value="{{ old('cargo_nombre') }}"></label>
<label>Versión <input type="number" required min="1" max="9999" value="{{ old('version',1) }}" name="version"></label></div>
@include('rrhh_documentos.document-picker')
<p><button class="cta">Guardar versión</button></p></form></section>@endif
</div>@endsection
