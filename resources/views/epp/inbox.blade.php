@extends('layouts.app')
@section('title', 'Solicitudes de EPP')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/epp-inbox.css') }}?v=20261112d">
@endpush
@section('content')
<div class="epp-inbox">
    <header class="epp-heading">
        <div><p>Bodega SAEP</p><h2>Pedidos recibidos</h2></div>
        <a class="epp-action" href="{{ route('epp.portal') }}">Abrir formulario de solicitud ↗</a>
    </header>
    <form class="epp-filters" action="{{ route('inventario-bodega.epp.index') }}">
        <label>Mes de recepción<input type="month" required name="mes" value="{{ $filters['mes'] }}"></label>
        <label>Buscar pedido<input type="search" name="buscar" maxlength="150" placeholder="Número, centro, nombre o correo" value="{{ $filters['buscar'] ?? '' }}"></label>
        <input type="hidden" name="estado" value="{{ $filters['estado'] }}">
        <button class="epp-action">Filtrar</button>
    </form>
    <nav class="epp-tabs" aria-label="Estado de las solicitudes">
        @foreach(['pendientes'=>'Pendientes','completadas'=>'Completadas','todas'=>'Todas'] as $key=>$label)
            <a href="{{ route('inventario-bodega.epp.index', array_merge($filters, ['estado'=>$key])) }}" @if($filters['estado']===$key) aria-current="page" @endif>{{ $label }} <b>{{ $counts[$key] }}</b></a>
        @endforeach
    </nav>
    @if(session('epp_status'))<p role="status">{{ session('epp_status') }}</p>@endif
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    @forelse($requests as $r)
        <details class="epp-request">
            <summary><strong>Pedido #{{ $r->id }} · {{ $r->centro }}</strong>
                <span>{{ $r->nombre }} · {{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y') }} · {{ $r->completada_en ? 'Completada' : 'Pendiente' }}</span>
            </summary>
            <div><p>{{ $r->email }} · Mes solicitado {{ $r->mes }}</p>
            @if($r->items)
                <div class="epp-scroll"><table>
                    <caption>Disponibilidad actual en Sede Central SAEP</caption>
                    <thead><tr><th>Artículo / talla</th><th>Pedido</th><th>Stock actual</th><th>Cobertura</th></tr></thead>
                    <tbody>@foreach($r->items as $item)
                        @php($balance = (float) $stock->get($item['id'], 0))
                        <tr><td><strong>{{ $item['nombre'] }}</strong><small>{{ $item['codigo'] }} · {{ $item['talla'] }}</small></td>
                            <td>{{ $item['cantidad'] }}</td><td>{{ $central ? $balance : '—' }}</td>
                            <td><span class="{{ $central && $balance >= $item['cantidad'] ? 'epp-ok' : 'epp-warn' }}">{{ !$central ? 'Sin ubicación activa' : ($balance >= $item['cantidad'] ? 'Disponible' : 'Faltan '.($item['cantidad'] - max(0, $balance))) }}</span></td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
            @if($r->insumos)<p><strong>Insumos fuera del catálogo</strong> · {{ $r->insumos }}</p>@endif
            @if($r->completada_en)<p>Completada el {{ \Illuminate\Support\Carbon::parse($r->completada_en)->format('d/m/Y H:i') }}</p>
            @elseif(auth()->user()->tieneAcceso('inventario_bodega','puede_editar'))
                <form method="post" action="{{ route('inventario-bodega.epp.complete', $r->id) }}">@csrf @method('PATCH')
                    <p>Completa la solicitud cuando hayas terminado su gestión. La salida de stock se registra en Inventario.</p>
                    <button class="epp-action">Marcar como completada</button>
                </form>
            @endif
            </div>
        </details>
    @empty
        <div class="epp-empty"><h3>No hay pedidos con estos filtros</h3><p>Prueba otro mes o revisa las demás categorías.</p></div>
    @endforelse
    {{ $requests->links() }}
</div>
@endsection
