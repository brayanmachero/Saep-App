@extends('layouts.app')
@section('title', 'Sábana de tarifas · Comercial')
@section('content')
@include('comercial::tarifas.styles')
<div class="tarifas">
    <header class="toolbar"><div><p class="muted">COMERCIAL · TARIFAS Y VERSIONES</p><h1>Sábana de tarifas</h1><p class="muted">Consulta por cliente, centro, cargo y categoría. Cada valor conserva su período y origen.</p></div>
        <div class="actions"><a class="btn btn-outline-primary" href="{{ route('comercial.tarifas.export', request()->except('page')) }}">Exportar consulta CSV</a>
        @if(auth()->user()->tieneAcceso('comercial', 'puede_crear'))<a class="btn btn-primary" href="{{ route('comercial.tarifas.create') }}">Registrar tarifa</a>@endif</div>
    </header>
    <div class="stats">@foreach(['fuentes'=>'Archivos registrados','referencias'=>'Referencias cargadas','pendientes'=>'Por revisar','vigentes'=>'Vigentes hoy'] as $key=>$label)<div class="panel stat"><span class="muted">{{ $label }}</span><strong>{{ number_format($resumen[$key],0,',','.') }}</strong></div>@endforeach</div>
    <div class="note">Las referencias de Excel están disponibles para consulta. Solo las revisadas y confirmadas aparecen en <strong>Vigentes a la fecha</strong>. El período del archivo no acredita vigencia. Los indicadores superiores muestran la cobertura total.</div>
    <form class="panel filters" method="GET">
        <div><label for="q">Buscar</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Cliente, cargo o archivo…"></div>
        @foreach(['cliente'=>'Cliente','centro'=>'Centro de costo','cargo'=>'Cargo','categoria'=>'Categoría','modalidad'=>'Modalidad','unidad'=>'Unidad de cobro'] as $campo=>$label)
        <div><label for="{{ $campo }}">{{ $label }}</label><select id="{{ $campo }}" name="{{ $campo }}"><option value="">Todos</option>@foreach($opciones[$campo] as $opcion)<option value="{{ $opcion }}" @selected(request($campo)===$opcion)>{{ $opcion }}</option>@endforeach</select></div>
        @endforeach
        <div><label for="vista">Vista</label><select id="vista" name="vista">@foreach(['ultimas'=>'Último período por cargo y condiciones','referencias'=>'Referencias disponibles','vigentes'=>'Vigentes a la fecha','pendientes'=>'Pendientes de revisión','historico'=>'Todas las versiones'] as $value=>$label)<option value="{{ $value }}" @selected(request('vista','ultimas')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="anio">Año del documento</label><input id="anio" name="anio" type="number" min="2000" max="2100" value="{{ request('anio') }}" placeholder="Todos"></div>
        <div><label for="fecha">Fecha de consulta (vista vigentes)</label><input type="date" id="fecha" name="fecha" value="{{ request('fecha',now()->toDateString()) }}"></div>
        <div class="actions" style="align-items:end"><button class="btn btn-primary">Consultar</button><a class="btn btn-outline-secondary" href="{{ route('comercial.tarifas.index') }}">Limpiar</a></div>
    </form>
    <section class="panel"><div class="toolbar"><h2>{{ number_format($tarifas->total(),0,',','.') }} referencias en esta consulta</h2><span class="muted">Valores en su moneda y unidad originales · sin sumar unidades distintas</span></div>
        <div class="table-wrap"><table><thead><tr><th>Cliente / Centro</th><th>Cargo / Categoría</th><th>Modalidad</th><th>Unidad / Variante</th><th class="number">Tarifa</th><th>Período origen</th><th>Revisión</th><th>Vigencia confirmada</th><th>Fuente</th><th></th></tr></thead><tbody>
        @forelse($tarifas as $tarifa)<tr>
            <td><strong>{{ $tarifa->cliente }}</strong><div class="muted">{{ $tarifa->centro ?: 'Sin centro informado' }}</div></td>
            <td>{{ $tarifa->cargo }}<div class="muted">{{ $tarifa->categoria ?: 'Sin clasificar' }}</div></td><td>{{ $tarifa->modalidad ?: 'Por confirmar' }}</td>
            <td>{{ $tarifa->unidad ?: 'Por confirmar' }}<div class="muted">{{ $tarifa->variante }}</div></td>
            <td class="number"><strong>{{ $tarifa->precio === null ? 'Sin valor' : number_format((float)$tarifa->precio,2,',','.') }}</strong><div class="muted">{{ $tarifa->moneda }}</div></td>
            <td>{{ $tarifa->periodo ?: $tarifa->fecha_referencia?->format('d/m/Y') ?: 'Sin fecha' }}</td>
            <td><span class="badge {{ $tarifa->estado }}">{{ ucfirst($tarifa->estado) }}</span></td>
            <td>{{ $tarifa->vigente_desde?->format('d/m/Y') ?: 'Por confirmar' }}@if($tarifa->vigente_desde)<div class="muted">Hasta {{ $tarifa->vigente_hasta?->format('d/m/Y') ?: 'sin término informado' }}</div>@endif</td>
            <td style="max-width:230px">{{ $tarifa->fuente?->archivo }}<div class="muted">{{ $tarifa->hoja }} {{ $tarifa->celda }}</div></td>
            <td><a href="{{ route('comercial.tarifas.show',$tarifa) }}">Ver desglose</a></td>
        </tr>@empty<tr><td class="empty" colspan="10">No hay tarifas para estos filtros. Puedes consultar las referencias pendientes o cambiar la fecha.</td></tr>@endforelse
        </tbody></table></div><div style="margin-top:15px">{{ $tarifas->links() }}</div>
    </section>
    <details class="panel"><summary>Cobertura de fuentes · {{ $fuentes->count() }} archivos</summary><p class="muted">Un archivo registrado puede seguir pendiente de extracción. Las referencias no se activan automáticamente.</p><div class="table-wrap"><table><thead><tr><th>Carpeta</th><th>Archivo</th><th>Referencias</th><th>Lectura</th><th>Observaciones</th></tr></thead><tbody>@foreach($fuentes as $fuente)<tr><td>{{ $fuente->carpeta }}</td><td>{{ $fuente->archivo }}</td><td>{{ $fuente->tarifas_count }}</td><td>{{ $fuente->estado }}</td><td>{{ $fuente->observaciones }}</td></tr>@endforeach</tbody></table></div></details>
</div>
@endsection
