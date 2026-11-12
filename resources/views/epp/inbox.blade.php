@extends('layouts.app')
@section('content')
<div class="container py-4"><p>Bodega SAEP</p><h1>Solicitudes de EPP</h1><p>Pedidos recibidos, del más reciente al más antiguo.</p><a href="{{ route('epp.portal') }}">Abrir formulario de solicitud</a>
@forelse($requests as $r)<details class="card p-3 my-2"><summary><strong>#{{ $r->id }} · {{ $r->centro }}</strong> · {{ $r->mes }} · {{ $r->nombre }}</summary><p>{{ $r->email }} · Recibido: {{ $r->created_at }}</p>@include('epp.lines',['r'=>$r])</details>@empty<p class="mt-3">Aún no hay solicitudes.</p>@endforelse
{{ $requests->links() }}</div>@endsection
