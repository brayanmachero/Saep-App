@extends('layouts.app')
@section('title','Resumen del lote')
@include('rrhh_documentos.style')
@include('rrhh_documentos.workspace-style')
@section('content')
<div class="gdm doc-workspace">
@include('rrhh_documentos.navigation',['section'=>'lots'])
<a href="{{ route('rrhh-documentos.show',$batch->id) }}">← Volver al lote</a>
<section class="panel"><h2>Resumen para Cristian</h2><p class="muted">Revisa el contenido y descarga el borrador para abrirlo en tu aplicación de correo. Esta acción no envía mensajes.</p>
<p><strong>Para:</strong> chormazabal@saep.cl</p><p><strong>Asunto:</strong> {{ $subject }}</p>
<pre style="white-space:pre-wrap;overflow-wrap:anywhere;font:inherit">{{ $body }}</pre>
<a class="cta" href="{{ route('rrhh-documentos.summary',['id'=>$batch->id,'formato'=>'eml']) }}">Descargar borrador de correo</a>
<p class="muted">El borrador se genera con el estado actual al descargar. Confirma destinatario y contenido antes de enviarlo desde tu correo. No contiene PDF ni datos personales de trabajadores.</p></section>
</div>
@endsection
