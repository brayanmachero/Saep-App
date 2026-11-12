<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Solicitud de EPP | SAEP</title><link rel="stylesheet" href="{{ asset('css/epp-portal.css') }}?v=20261112c"></head>
<body><header><div><img src="{{ asset('brand/wp/Logo_Saep.svg') }}" alt="SAEP"><span>Bodega</span></div></header>
<main><div class="epp-hero"><div><h1>Solicitud de EPP</h1><p>Solicita equipos de protección, ropa de trabajo e insumos para tu centro.</p></div></div>
@if($errors->any())<section role="alert"><strong>Revisa la solicitud</strong><ul>@foreach($errors->messages() as $field => $messages)
@php($item = str_starts_with($field, 'cantidades.') ? $catalog->firstWhere('id', substr($field, strlen('cantidades.'))) : null)
@foreach($messages as $error)<li>@if($item){{ $item['nombre'] }} {{ $item['talla'] }} · @endif{{ $error }}</li>@endforeach
@endforeach</ul></section>@endif
@if(!$identity)<section><h2>Ingresa con tu cuenta SAEP</h2><p>Tu nombre y correo se completarán con los datos de tu cuenta corporativa.</p><a class="button" href="{{ route('epp.login') }}">Continuar con Microsoft</a></section>
@else
@if(session('pedido_ok'))<section role="status"><strong>Bodega recibió tu solicitud #{{ session('pedido_ok') }}.</strong> Puedes consultar el detalle en tus solicitudes recientes.</section>@endif
<section class="person"><div><small>SOLICITANTE</small><strong>{{ $identity['name'] }}</strong>{{ $identity['email'] }}</div>@unless(auth()->check())<form method="post" action="{{ route('epp.logout') }}">@csrf<button class="secondary">Cerrar sesión</button></form>@endunless</section>
<form method="post" action="{{ route('epp.store') }}" id="pedido">@csrf<input type="hidden" name="folio" value="{{ old('folio', (string) Illuminate\Support\Str::uuid()) }}">
<section><h2>Centro y período</h2><div class="grid"><label>Buscar centro por nombre o código<input id="buscar-centro" type="search" placeholder="Ej.: Quilicura o 153-EST"></label><label>Mes del pedido<input type="month" name="mes" required value="{{ old('mes', now()->format('Y-m')) }}"></label></div>
<label>Centro de trabajo<select required id="centro" name="centro_id"><option value="">Selecciona el centro</option>@foreach($centers as $c)<option value="{{ $c->id }}" @selected(old('centro_id') == $c->id)>{{ $c->codigo_origen }} · {{ $c->nombre }}</option>@endforeach</select></label></section>
<section><h2>Artículos y cantidades</h2><label>Buscar en el catálogo<input type="search" id="buscar" placeholder="Nombre, código, talla o categoría"></label>
<div class="grid"><label>Categoría<select id="categoria"><option value="">Todas las categorías</option>@foreach($catalog->pluck('categoria')->filter()->unique()->sort() as $v)<option>{{ $v }}</option>@endforeach</select></label><label>Talla<select id="talla"><option value="">Todas las tallas</option>@foreach($catalog->pluck('talla')->filter()->unique()->sort() as $v)<option>{{ $v }}</option>@endforeach</select></label></div>
<label>Artículo del catálogo<select id="variante"></select></label><button type="button" id="agregar" class="secondary">Agregar artículo</button>
<h3>Artículos del pedido</h3><div id="lineas"></div><p id="vacio">Todavía no agregaste artículos.</p><p id="aviso" aria-live="polite"></p></section>
<section><h2>Insumos generales</h2><label>Elemento y cantidad (opcional)<textarea name="insumos" maxlength="3000" rows="3" placeholder="Ej.: 2 resmas de papel tamaño carta">{{ old('insumos') }}</textarea></label></section>
<button class="button" type="submit">Enviar solicitud a Bodega</button></form>
<section><h2>Mis solicitudes recientes</h2>@forelse($history as $r)<details><summary>Pedido #{{ $r->id }} · {{ $r->mes }} · {{ $r->centro }}</summary>@include('epp.lines',['r'=>$r])</details>@empty<p>Aún no tienes solicitudes registradas.</p>@endforelse</section>
<script>window.eppCatalog={{ Illuminate\Support\Js::from($catalog) }};window.eppOld={{ Illuminate\Support\Js::from(old('cantidades', [])) }};</script><script src="{{ asset('js/epp-portal.js') }}?v=20261112d"></script>
@endif</main></body></html>
