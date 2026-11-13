<section class="panel">
    <h3>PDF por contrato</h3>
    <p>Selecciona un documento y adjunta el PDF definitivo del contrato. Comprueba el archivo con «Descargar» antes de procesar el lote.</p>
    @foreach ($items as $item)
        @php($person = json_decode($item->snapshot, true))
        <details>
            <summary>{{ $person['persona_nombre'] }} · contrato #{{ $person['talana_id'] }}</summary>
            <ul>
            @foreach ($battery['documentos'] as $position => $name)
                @php($file = $files->get($item->id)?->firstWhere('position', $position))
                <li>{{ $name }} · {{ $file ? str_replace('_', ' ', $file->state) : 'Sin PDF' }}
                    @if ($file)
                        <a href="{{ route('rrhh-documentos.pdf', [$batch->id, $item->id, $position]) }}">Descargar</a>
                        @if ($file->signature_id) · Solicitud #{{ $file->signature_id }} @endif
                    @endif
                </li>
            @endforeach
            </ul>
            @if ($batch->estado === 'BORRADOR' && !$item->excluido && !json_decode($item->observaciones, true) && auth()->user()->tieneAcceso('gestion_documental', 'puede_editar'))
                <form method="POST" enctype="multipart/form-data" action="{{ route('rrhh-documentos.prepare', [$batch->id, $item->id]) }}">
                    @csrf
                    <label>Documento de la batería <select name="position">@foreach ($battery['documentos'] as $position => $name)<option value="{{ $position }}">{{ $name }}</option>@endforeach</select></label>
                    <label>Categoría en Talana <input name="category" required maxlength="120"></label>
                    <label>Archivo PDF (hasta 10 MB) <input type="file" name="pdf" accept="application/pdf" required></label>
                    <button class="cta">Guardar PDF</button>
                </form>
            @endif
        </details>
    @endforeach
</section>
