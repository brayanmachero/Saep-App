@extends('layouts.app')
@section('title','Baterías documentales EST')
@include('rrhh_documentos.style')
@section('content')
<div class="gdm">
<a href="{{ route('rrhh-documentos.index') }}">Volver a gestión documental</a>
<h2>Baterías documentales EST</h2>
<section class="panel">
<form method="get"><label>Cliente o centro de la hoja EST
<select name="grupo">@foreach(array_keys($groups) as $option)<option @selected($option === $group)>{{ $option }}</option>@endforeach</select></label>
<button class="cta">Ver documentos</button></form>
<p>Referencia de Cristian · Octubre 2025</p>
@if($group === 'Unimarc EST')<p class="note">La columna Unimarc repite documentos base con nombres distintos. Revisa posibles duplicados antes de guardar.</p>@endif
@if(str_starts_with($group,'IDL'))<p class="note">Revisar la vigencia del pacto de horas extras con Cristian. El Excel indica actualización en enero.</p>@endif
<p>Cruce de nombres del 13/11/2026</p><ol>@foreach($documents as $name => $sources)<li>{{ $name }} <small>({{ implode(', ', $sources) }})</small> · {{ $matches[$name] ?? 'Pendiente de validar' }} · <a href="{{ route('rrhh-documentos.est',['grupo'=>$group,'buscar'=>$name]) }}#talana">Buscar en Talana</a></li>@endforeach</ol>
</section>
<section class="panel" id="talana"><h3>Consultar documentos de Talana</h3>
<form method="get" action="{{ route('rrhh-documentos.est') }}#talana"><input type="hidden" name="grupo" value="{{ $group }}"><label>Nombre o palabras del documento <input name="buscar" required minlength="2" maxlength="255" value="{{ $term }}"></label><button class="cta">Buscar</button></form>
@if($error)<p role="alert">{{ $error }}</p>@elseif($total !== null)<p>{{ number_format($total,0,',','.') }} registros encontrados · Página {{ $page }}</p><ul>@foreach(array_keys($names) as $name)<li>{{ $name }}</li>@endforeach</ul>
@if($page > 1)<a href="{{ request()->fullUrlWithQuery(['pagina'=>$page-1]) }}#talana">Anterior</a>@endif
@if($next)<a href="{{ request()->fullUrlWithQuery(['pagina'=>$page+1]) }}#talana">Siguiente</a>@endif
@endif</section>
@if(auth()->user()->tieneAcceso('gestion_documental','puede_crear'))
<section class="panel"><h3>Guardar batería revisada</h3>
<form method="post" action="{{ route('rrhh-documentos.battery') }}">@csrf
<label>Nombre <input required name="nombre" maxlength="150" value="EST · {{ $group }}"></label>
<label>Centro de costo Talana <select required name="centro_costo_nombre"><option value="">Elegir centro</option>@foreach($centres as $centre)<option>{{ $centre }}</option>@endforeach</select></label>
<label>Cargo, si corresponde <input name="cargo_nombre" maxlength="200"></label>
<label>Versión <input type="number" required min="1" max="9999" value="1" name="version"></label>
<label>Documentos aprobados, uno por línea <textarea rows="12" required name="documentos">{{ implode("\n",array_keys($documents)) }}</textarea></label>
<button class="cta">Guardar versión</button></form></section>@endif
</div>@endsection
