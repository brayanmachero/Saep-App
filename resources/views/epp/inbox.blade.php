@extends('layouts.app')
@section('title', 'Solicitudes de EPP')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/epp-inbox.css') }}">
@endpush
@section('content')
<div class="epp-inbox">
    <div class="epp-heading">
        <div><p>Bodega SAEP</p><h2>Pedidos recibidos</h2></div>
        <a class="btn btn-light" href="{{ route('epp.portal') }}">Abrir formulario de solicitud</a>
    </div>
    <form class="epp-filters" method="get" action="{{ route('inventario-bodega.epp.index') }}">
        <label>Buscar pedido<input class="form-control" type="search" name="buscar" maxlength="150" placeholder="N° de pedido, centro, nombre o correo" value="{{ $filters['buscar'] ?? '' }}"></label>
        <label>Mes solicitado<input class="form-control" type="month" name="mes" value="{{ $filters['mes'] ?? '' }}"></label>
        <button class="btn btn-primary">Filtrar</button>
        @if(array_filter($filters))<a href="{{ route('inventario-bodega.epp.index') }}">Limpiar</a>@endif
    </form>
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    @forelse($requests as $r)
        <details class="epp-request">
            <summary><strong>Pedido #{{ $r->id }} · {{ $r->centro }}</strong><span>{{ $r->nombre }} · {{ $r->mes }}</span></summary>
            <div><p>{{ $r->email }} · Recibido el {{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</p>
            @include('epp.lines',['r'=>$r])</div>
        </details>
    @empty
        <div class="epp-empty">
            <i class="bi bi-clipboard" aria-hidden="true"></i>
            <h3>{{ array_filter($filters) ? 'No hay pedidos con estos filtros' : 'Aún no hay solicitudes' }}</h3>
            @unless(array_filter($filters))<p>Los pedidos enviados por el personal aparecerán aquí.</p>@endunless
        </div>
    @endforelse
    {{ $requests->links() }}
</div>
@endsection
