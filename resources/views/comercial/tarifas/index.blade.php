@extends('layouts.app')
@section('title', 'Sábana de tarifas · Comercial')
@section('content')
@include('comercial::tarifas.styles')
<div class="tarifas">
    <header class="toolbar"><div><p class="muted">COMERCIAL · CONSULTA DE PRECIOS</p><h1>Sábana de tarifas</h1><p class="muted">Encuentra el precio de un servicio, dónde aplica y cómo se cobra.</p></div>
        <div class="actions">@if(auth()->user()->tieneAcceso('comercial','puede_editar'))<a class="btn btn-outline-primary" href="{{ route('comercial.tarifas.validacion') }}">Validar precios</a>@endif<a class="btn btn-outline-primary" href="{{ route('comercial.tarifas.export', request()->except('page')) }}">Exportar consulta CSV</a>
        @if(auth()->user()->tieneAcceso('comercial', 'puede_crear'))<a class="btn btn-primary" href="{{ route('comercial.tarifas.create') }}">Registrar tarifa</a>@endif</div>
    </header>
    <div class="stats">@foreach(['referencias'=>'Precios registrados','pendientes'=>'Por confirmar','vigentes'=>'Vigentes hoy'] as $key=>$label)<div class="panel stat"><span class="muted">{{ $label }}</span><strong>{{ number_format($resumen[$key],0,',','.') }}</strong></div>@endforeach</div>
    <div class="note">Los precios <strong>por confirmar</strong> son antecedentes para consulta. Antes de utilizarlos, confirma con Comercial su unidad de cobro y vigencia. Las cotizaciones se actualizan desde el cotizador; «En cotización» no es un precio aprobado.</div>
    @if(session('info'))<div class="note" role="status">{{ session('info') }}</div>@endif
    <section class="panel"><h2>Cotizador conectado · {{ $conciliacion['vinculadas'] }} de {{ $conciliacion['total'] }} cotizaciones vinculadas</h2><p>Los cambios guardados en el cotizador se reflejan al consultar esta sábana. <a href="{{ route('comercial.tarifas.index', ['vista'=>'cotizaciones']) }}">Ver cotizaciones del sistema</a></p>@if($conciliacion['total'] > $conciliacion['vinculadas'])<p class="note">{{ $conciliacion['total'] - $conciliacion['vinculadas'] }} cotizaciones requieren revisar su correspondencia antes de enlazarlas. No se han duplicado ni aprobado sus precios.</p><div class="actions">@foreach($conciliacion['pendientes'] as $pendiente)<a href="{{ route('comercial.cotizaciones.show', $pendiente) }}">Revisar {{ $pendiente->numero }}</a>@endforeach</div>@endif</section>
    <form id="tarifas-consulta" class="panel" method="GET"><div class="filters">
        <div><label for="q">Buscar</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Cliente, centro o servicio…"></div>
        @foreach(['cliente'=>'Cliente','centro'=>'Centro','cargo'=>'Cargo o servicio'] as $campo=>$label)
        <div><label for="{{ $campo }}">{{ $label }}</label><select id="{{ $campo }}" name="{{ $campo }}"><option value="">Todos</option>@foreach($opciones[$campo] as $opcion)<option value="{{ $opcion }}" @selected(request($campo)===$opcion)>{{ $opcion }}</option>@endforeach</select></div>
        @endforeach
        <div><label for="vista">Vista</label><select id="vista" name="vista">@foreach(['ultimas'=>'Precios más recientes','cotizaciones'=>'Cotizaciones del sistema','referencias'=>'Todos los precios disponibles','vigentes'=>'Precios vigentes','pendientes'=>'Precios por confirmar','historico'=>'Historial de precios'] as $value=>$label)<option value="{{ $value }}" @selected(request('vista','ultimas')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="actions" style="align-items:end"><button class="btn btn-primary">Consultar</button><a class="btn btn-outline-secondary" href="{{ route('comercial.tarifas.index') }}">Limpiar</a></div>
        </div><details style="margin-top:16px" @if(request()->filled('categoria') || request()->filled('modalidad') || request()->filled('unidad') || request()->filled('anio') || request('vista')==='vigentes') open @endif><summary>Más filtros</summary><div class="filters">
        @foreach(['categoria'=>'Categoría','modalidad'=>'Modalidad','unidad'=>'Unidad de cobro'] as $campo=>$label)<div><label for="{{ $campo }}">{{ $label }}</label><select id="{{ $campo }}" name="{{ $campo }}"><option value="">Todos</option>@foreach($opciones[$campo] as $opcion)<option value="{{ $opcion }}" @selected(request($campo)===$opcion)>{{ $opcion }}</option>@endforeach</select></div>@endforeach
        <div><label for="anio">Año de referencia</label><input id="anio" name="anio" type="number" min="2000" max="2100" value="{{ request('anio') }}" placeholder="Todos"></div>
        <div><label for="fecha">Fecha de consulta (vista vigentes)</label><input type="date" id="fecha" name="fecha" value="{{ request('fecha',now()->toDateString()) }}"></div>
        </div></details>
        <p class="muted" style="margin-top:12px">Al cambiar cliente o centro se actualizan las opciones y se reinician los filtros dependientes. La búsqueda, vista y fechas se conservan.</p>
        <noscript><p class="muted">Pulsa Consultar después de elegir cliente o centro para actualizar las opciones.</p></noscript>
    </form>
    @if($errors->any())<div class="note error">{{ $errors->first() }}</div>@endif
    <form id="tarifas-comparador" class="panel" method="GET" action="{{ route('comercial.tarifas.comparar') }}"><div class="toolbar"><div><h2>Comparar precios</h2><p class="muted">Selecciona entre 2 y 4 tarifas. Puedes cambiar filtros o página sin perder la selección.</p><div id="comparador-seleccion" class="actions" aria-live="polite"></div></div><div class="actions"><button id="comparador-abrir" class="btn btn-primary" disabled>Comparar</button><button id="comparador-limpiar" type="button" class="btn">Quitar selección</button></div></div><p id="comparador-aviso" role="status" class="muted"></p></form>
    <section class="panel"><div class="toolbar"><h2>{{ number_format($tarifas->total(),0,',','.') }} precios encontrados</h2><span class="muted">Revisa siempre la unidad de cobro</span></div>
        <div class="table-wrap"><table><thead><tr><th>Comparar</th><th>Cliente / Centro</th><th>Cargo o servicio</th><th>Modalidad</th><th>Unidad / Variante</th><th class="number">Tarifa</th><th>Fecha de referencia</th><th>Revisión</th><th>Vigencia confirmada</th><th></th></tr></thead><tbody>
        @forelse($tarifas as $tarifa)<tr>
            <td><input type="checkbox" class="comparar-tarifa" value="{{ $tarifa->id }}" data-label="{{ $tarifa->cliente }} · {{ $tarifa->centro }} · {{ $tarifa->cargo }} (#{{ $tarifa->id }})" aria-label="Comparar tarifa {{ $tarifa->id }} de {{ $tarifa->cargo }}"></td>
            <td><strong>{{ $tarifa->cliente }}</strong><div class="muted">{{ $tarifa->centro ?: 'Sin centro informado' }}</div></td>
            <td>{{ $tarifa->cargo }}<div class="muted">{{ $tarifa->categoria ?: 'Sin clasificar' }}</div>@if($tarifa->cotizacion && !$tarifa->cotizacion->trashed())<a href="{{ route('comercial.cotizaciones.show', $tarifa->cotizacion_id) }}">{{ $tarifa->cotizacion->numero }}</a>@endif</td><td>{{ $tarifa->modalidad ?: 'Por confirmar' }}</td>
            <td>{{ $tarifa->unidad ?: 'Por confirmar' }}<div class="muted">{{ $tarifa->variante }}</div></td>
            <td class="number" title="Importe exacto: {{ $tarifa->precio }} {{ $tarifa->moneda }}"><strong>{{ $tarifa->precio === null ? 'Sin valor' : \App\Modules\Comercial\Services\TarifaPresentacionService::importe($tarifa->precio, 2) }}</strong><div class="muted">{{ $tarifa->moneda }}</div></td>
            <td>{{ $tarifa->fecha_referencia?->format('d/m/Y') ?: 'Por confirmar' }}</td>
            <td><span class="badge {{ in_array($tarifa->id,$vigentesHoy) ? 'confirmada' : 'pendiente' }}">{{ $tarifa->etiquetaConsulta(in_array($tarifa->id,$vigentesHoy)) }}</span></td>
            <td>@if($tarifa->estado === 'confirmada'){{ $tarifa->vigente_desde?->format('d/m/Y') ?: 'Por confirmar' }}@if($tarifa->vigente_desde)<div class="muted">Hasta {{ $tarifa->vigente_hasta?->format('d/m/Y') ?: 'sin término informado' }}</div>@endif @else Sin vigencia activa @endif</td>

            <td><a href="{{ route('comercial.tarifas.show',$tarifa) }}">Consultar precio</a></td>
        </tr>@empty<tr><td class="empty" colspan="10">No hay tarifas para estos filtros. Puedes consultar las referencias pendientes o cambiar la fecha.</td></tr>@endforelse
        </tbody></table></div><div style="margin-top:15px">{{ $tarifas->links() }}</div>
    </section>
@if(auth()->user()->tieneAcceso('comercial','puede_editar'))
    <details class="panel"><summary>Administrar documentos · {{ $fuentes->count() }} archivos</summary><p class="muted">Un archivo registrado puede seguir pendiente de extracción. Las referencias no se activan automáticamente.</p><div class="table-wrap"><table><thead><tr><th>Carpeta</th><th>Archivo</th><th>Referencias</th><th>Lectura</th><th>Observaciones</th></tr></thead><tbody>@foreach($fuentes as $fuente)<tr><td>{{ $fuente->carpeta }}</td><td>{{ $fuente->archivo }}</td><td>{{ $fuente->tarifas_count }}</td><td>{{ $fuente->estado }}</td><td>{{ $fuente->observaciones }}</td></tr>@endforeach</tbody></table></div></details>
@endif
</div>
<script>
(() => {
    const form = document.getElementById('tarifas-consulta');
    if (!form) return;
    const dependencias = {cliente: ['centro', 'cargo', 'categoria', 'modalidad', 'unidad'], centro: ['cargo', 'categoria', 'modalidad', 'unidad'], cargo: ['categoria', 'modalidad', 'unidad']};
    Object.entries(dependencias).forEach(([campo, limpiar]) => {
        form.elements[campo].addEventListener('change', () => {
            limpiar.forEach(nombre => { form.elements[nombre].value = ''; });
            form.requestSubmit();
        });
    });
})();
</script>
<script src="{{ asset('js/comercial-comparador.js') }}" defer></script>
@endsection
