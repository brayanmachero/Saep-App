@extends('layouts.app')
@section('title', 'Solicitudes de EPP')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/epp-inbox.css') }}?v=20261112f">
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
        @foreach(['pendientes'=>'Pendientes','gestion'=>'En gestión','completadas'=>'Completadas','todas'=>'Todas'] as $key=>$label)
            <a href="{{ route('inventario-bodega.epp.index', array_merge($filters, ['estado'=>$key])) }}" @if($filters['estado']===$key) aria-current="page" @endif>{{ $label }} <b>{{ $counts[$key] }}</b></a>
        @endforeach
    </nav>
    @if(session('epp_status'))<p role="status">{{ session('epp_status') }}</p>@endif
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    @forelse($requests as $r)
        <button type="button" class="epp-request epp-open" data-epp-panel="pedido-{{ $r->id }}" aria-haspopup="dialog">
            <span><strong>Pedido #{{ $r->id }} · {{ $r->centro }}</strong><small>{{ $r->nombre }} · {{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y') }}</small></span>
            <span>{{ $r->estado }}　›</span>
        </button>
        <dialog class="epp-panel" id="pedido-{{ $r->id }}" aria-labelledby="titulo-{{ $r->id }}" data-open="{{ request('pedido') == $r->id ? '1' : '0' }}">
            <header><h2 id="titulo-{{ $r->id }}">Pedido #{{ $r->id }}</h2><button type="button" data-close aria-label="Cerrar detalle">Cerrar ×</button></header>
            <div><strong>{{ $r->estado }} · {{ $r->centro }}</strong><p>{{ $r->nombre }} · {{ $r->email }} · Mes solicitado {{ $r->mes }}</p>
            @if($r->items)
                <div class="epp-scroll"><table>
                    <caption>Disponibilidad actual en Sede Central SAEP</caption>
                    <thead><tr><th>Artículo / talla</th><th>Pedido</th><th>Stock actual</th><th>Cobertura</th></tr></thead>
                    <tbody>@foreach($r->items as $line => $item)
                        @php($balance = (float) $stock->get($item['id'], 0))
                        @php($needed = $r->demand[$item['id']])
                        <tr><td><strong>{{ $item['nombre'] }}</strong><small>{{ $item['talla'] }}</small></td>
                            <td>{{ $item['cantidad'] }}</td><td>{{ $central ? $balance : '—' }}</td>
                            <td><span class="{{ $central && $balance >= $needed ? 'epp-ok' : 'epp-warn' }}">{{ !$central ? 'Sin ubicación activa' : ($balance >= $needed ? 'Disponible' : 'Faltan '.($needed - max(0, $balance))) }}</span></td></tr>
                        @if($central && !$r->completada_en && $balance < $needed && auth()->user()->tieneAcceso('inventario_bodega','puede_editar'))
                            <tr><td colspan="4">
                            @if(!empty($r->options[$line]))
                                <form class="epp-replace" method="post" action="{{ route('inventario-bodega.epp.substitute', ['pedido'=>$r->id, 'mes'=>$filters['mes']]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="linea" value="{{ $line }}">
                                    <input type="hidden" name="actual" value="{{ $item['id'] }}">
                                    <label>Alternativa con stock<select required name="reemplazo"><option value="">Selecciona artículo y talla</option>
                                        @foreach($r->options[$line] as $variant)<option value="{{ $variant->id }}">{{ $variant->producto->nombre }} {{ $variant->talla }} · Stock {{ (float)$variant->stock_actual }}</option>@endforeach
                                    </select></label><button class="epp-action">Guardar reemplazo</button>
                                </form>
                            @else Sin alternativas con stock suficiente para esta cantidad.
                            @endif
                            </td></tr>
                        @endif
                    @endforeach</tbody>
                </table></div>
            @endif
            @if($r->insumos)<p><strong>Insumos fuera del catálogo</strong> · {{ $r->insumos }}</p>@endif
            @include('epp.timeline')
            @if(!$r->completada_en && auth()->user()->tieneAcceso('inventario_bodega','puede_editar'))
                <form method="post" action="{{ route('inventario-bodega.epp.complete', $r->id) }}">@csrf @method('PATCH')
                    <p>Completa la solicitud cuando hayas terminado su gestión. La salida de stock se registra en Inventario.</p>
                    <button class="epp-action">Marcar como completada</button>
                </form>
            @endif
            </div>
        </dialog>
    @empty
        <div class="epp-empty"><h3>No hay pedidos con estos filtros</h3><p>Prueba otro mes o revisa las demás categorías.</p></div>
    @endforelse
    {{ $requests->links() }}
</div>
<script defer src="{{ asset('js/epp-inbox.js') }}"></script>
@endsection
