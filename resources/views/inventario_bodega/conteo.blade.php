@extends('layouts.app')

@section('content')
@php
    $canApprove = $canEdit && $conteo->estado === 'EN_REVISION';
    $isLocked = $conteo->estado === 'APROBADO' || ! $canEdit;
    $completed = $conteo->lineas->whereNotNull('cantidad_fisica')->count();
@endphp

<div class="stocktake-page">
    <div class="stocktake-heading">
        <div>
            <a href="{{ route('inventario-bodega.index', ['vista' => 'conteos']) }}" class="stocktake-back"><i class="bi bi-arrow-left"></i>Volver a conteos</a>
            <p>Conteo fisico · {{ $conteo->ubicacion->nombre }}</p>
            <h1>{{ $conteo->codigo }}</h1>
            <span>Fecha de corte: {{ optional($conteo->fecha_corte)->format('d/m/Y') }}</span>
        </div>
        <div class="stocktake-heading-meta">
            <div class="stocktake-state {{ strtolower($conteo->estado) }}">{{ str_replace('_', ' ', $conteo->estado) }}</div>
            @if($canEdit && $conteo->puedeEliminarse())
                <form method="POST" action="{{ route('inventario-bodega.conteos.destroy', $conteo) }}" onsubmit="return confirm('Se eliminará el conteo {{ $conteo->codigo }} y sus líneas. No toca el kardex. ¿Continuar?');">
                    @csrf @method('DELETE')
                    <button class="btn btn-light stocktake-btn text-danger" type="submit"><i class="bi bi-trash3"></i>Eliminar conteo</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success stocktake-alert"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger stocktake-alert"><i class="bi bi-exclamation-octagon-fill"></i>{{ $errors->first() }}</div>@endif

    <section class="stocktake-summary">
        <div><span>Lineas</span><strong>{{ $conteo->lineas->count() }}</strong></div>
        <div><span>Registradas</span><strong>{{ $completed }}</strong></div>
        <div><span>Pendientes</span><strong>{{ $conteo->lineas->count() - $completed }}</strong></div>
        <div><span>Diferencias</span><strong id="stocktake-differences">{{ $conteo->lineas->filter(fn($line) => $line->cantidad_fisica !== null && abs((float) $line->cantidad_fisica - (float) $line->cantidad_sistema) > 0.0001)->count() }}</strong></div>
    </section>

    <section class="stocktake-section">
        <div class="stocktake-tools">
            <div><h2>Planilla de conteo</h2><p>Ingresa la cantidad fisica. La diferencia se calcula al instante; el ajuste solo existe cuando se aprueba.</p></div>
            <label class="stocktake-search"><i class="bi bi-search"></i><input type="search" id="stocktake-search" class="form-control" placeholder="Buscar articulo o talla"></label>
        </div>
        @if($conteo->observacion)<div class="stocktake-note"><i class="bi bi-chat-left-text"></i>{{ $conteo->observacion }}</div>@endif
        <form method="POST" action="{{ route('inventario-bodega.conteos.update', $conteo) }}">
            @csrf @method('PUT')
            <div class="stocktake-table-wrap">
                <table class="stocktake-table">
                    <thead><tr><th>Articulo</th><th>Talla</th><th class="text-end">Sistema</th><th class="physical-col">Fisico</th><th class="text-end">Diferencia</th><th>Observacion</th></tr></thead>
                    <tbody>
                    @foreach($conteo->lineas as $line)
                        @php $difference = $line->cantidad_fisica === null ? null : (float) $line->cantidad_fisica - (float) $line->cantidad_sistema; @endphp
                        <tr data-stocktake-row data-search="{{ strtolower($line->producto->codigo . ' ' . $line->producto->nombre . ' ' . $line->variante->talla) }}">
                            <td><strong>{{ $line->producto->nombre }}</strong><small>{{ $line->producto->codigo }}</small></td>
                            <td>{{ $line->variante->talla }}</td>
                            <td class="text-end" data-system-value="{{ (float) $line->cantidad_sistema }}">{{ rtrim(rtrim(number_format((float) $line->cantidad_sistema, 3, ',', '.'), '0'), ',') }}</td>
                            <td class="physical-col"><input name="lineas[{{ $line->id }}][cantidad_fisica]" type="number" min="0" step="0.001" class="form-control stocktake-physical" value="{{ $line->cantidad_fisica }}" @disabled($isLocked)></td>
                            <td class="text-end stocktake-difference {{ $difference !== null && $difference != 0 ? ($difference > 0 ? 'positive' : 'negative') : '' }}" data-difference>{{ $difference === null ? '-' : ($difference > 0 ? '+' : '') . rtrim(rtrim(number_format($difference, 3, ',', '.'), '0'), ',') }}</td>
                            <td><input name="lineas[{{ $line->id }}][observacion]" class="form-control" maxlength="300" value="{{ $line->observacion }}" placeholder="Opcional" @disabled($isLocked)></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if(! $isLocked)
                <div class="stocktake-actions"><button class="btn btn-primary stocktake-btn" type="submit"><i class="bi bi-save2"></i>Guardar conteo</button></div>
            @endif
        </form>
    </section>

    @if($canApprove)
        <section class="stocktake-approval">
            <div><i class="bi bi-shield-check"></i><div><h2>Conteo listo para aprobar</h2><p>Al aprobar, las diferencias se registran como ajustes. No se modifican los movimientos ya existentes.</p></div></div>
            <form method="POST" action="{{ route('inventario-bodega.conteos.aprobar', $conteo) }}" onsubmit="return confirm('Se registraran ajustes por las diferencias encontradas. Deseas aprobar este conteo?');">@csrf<button class="btn btn-success stocktake-btn" type="submit"><i class="bi bi-check2-circle"></i>Aprobar y ajustar stock</button></form>
        </section>
    @elseif($conteo->estado === 'BORRADOR')
        <div class="stocktake-reminder"><i class="bi bi-info-circle"></i>Completa todas las cantidades fisicas y guarda para dejar este conteo listo para revision.</div>
    @endif
</div>

<style>
    .stocktake-page { padding:1.35rem 1.75rem 2.5rem; color:#17213a; }.stocktake-heading,.stocktake-tools,.stocktake-actions,.stocktake-approval,.stocktake-summary,.stocktake-heading-meta { display:flex; align-items:center; }.stocktake-heading { justify-content:space-between; gap:1rem; margin-bottom:1rem; }.stocktake-heading-meta { gap:.65rem; flex-wrap:wrap; justify-content:flex-end; }.stocktake-heading-meta form { margin:0; }.stocktake-back { color:#49308b; text-decoration:none; display:inline-flex; gap:.4rem; align-items:center; font-size:.84rem; font-weight:750; }.stocktake-heading p { color:#67758c; margin:.55rem 0 .1rem; font-size:.84rem; }.stocktake-heading h1 { margin:0; font-size:1.6rem; font-weight:850; }.stocktake-heading span { color:#748198; font-size:.82rem; }.stocktake-state { border-radius:999px; padding:.38rem .7rem; font-size:.75rem; font-weight:850; background:#eef1f5; color:#617087; white-space:nowrap; }.stocktake-state.en_revision { background:#eee8ff; color:#5632b5; }.stocktake-state.aprobado { background:#e7f8ef; color:#087245; }.stocktake-alert { display:flex; gap:.6rem; align-items:center; }.stocktake-summary { gap:.8rem; margin-bottom:1rem; }.stocktake-summary > div { flex:1; border:1px solid #e2e7f0; background:#fff; border-radius:.6rem; padding:.75rem .9rem; }.stocktake-summary span { display:block; font-size:.68rem; text-transform:uppercase; font-weight:800; letter-spacing:.045em; color:#748198; }.stocktake-summary strong { font-size:1.35rem; display:block; margin-top:.18rem; }.stocktake-section { padding:1rem; background:#fff; border:1px solid #e1e7f0; border-radius:.65rem; box-shadow:0 .4rem 1.2rem rgba(29,41,67,.05); }.stocktake-tools { justify-content:space-between; gap:1rem; margin-bottom:.8rem; }.stocktake-tools h2,.stocktake-approval h2 { font-size:1rem; font-weight:850; margin:0 0 .15rem; }.stocktake-tools p,.stocktake-approval p { margin:0; color:#68758b; font-size:.82rem; }.stocktake-search { position:relative; min-width:260px; }.stocktake-search i { position:absolute; left:.7rem; top:.65rem; color:#79869a; }.stocktake-search input { padding-left:2rem; font-size:.84rem; }.stocktake-note,.stocktake-reminder { display:flex; gap:.55rem; align-items:center; border-radius:.45rem; padding:.7rem; margin-bottom:.8rem; font-size:.82rem; }.stocktake-note { background:#f4f2ff; color:#4f3d85; }.stocktake-reminder { background:#fff8e8; color:#80520e; margin-top:1rem; }.stocktake-table-wrap { overflow:auto; }.stocktake-table { width:100%; min-width:840px; border-collapse:collapse; font-size:.84rem; }.stocktake-table th { padding:.58rem .55rem; font-size:.68rem; letter-spacing:.045em; text-transform:uppercase; color:#68758b; border-bottom:1px solid #dee5ef; white-space:nowrap; }.stocktake-table td { padding:.56rem .55rem; border-bottom:1px solid #edf0f5; vertical-align:middle; }.stocktake-table td small { display:block; color:#748198; font-size:.72rem; margin-top:.12rem; }.stocktake-table tbody tr:last-child td { border-bottom:0; }.stocktake-table .form-control { min-height:2.3rem; font-size:.84rem; }.stocktake-difference.positive { color:#087245; font-weight:800; }.stocktake-difference.negative { color:#ba2936; font-weight:800; }.stocktake-actions { justify-content:flex-end; border-top:1px solid #e8edf4; padding-top:.85rem; margin-top:.7rem; }.stocktake-btn { border-radius:.48rem; min-height:2.5rem; font-size:.86rem; font-weight:800; display:inline-flex; gap:.45rem; align-items:center; }.stocktake-btn.btn-primary { background:#23085d; border-color:#23085d; }.stocktake-approval { justify-content:space-between; gap:1rem; margin-top:1rem; padding:1rem; border:1px solid #aee5c7; border-left:4px solid #1aa364; background:#f0fbf5; border-radius:.6rem; }.stocktake-approval > div { display:flex; gap:.7rem; align-items:flex-start; }.stocktake-approval > div > i { color:#087245; font-size:1.15rem; }.stocktake-approval .btn-success { background:#087245; border-color:#087245; }
    @media (max-width:900px) { .stocktake-page { padding:1rem .85rem 5rem; }.stocktake-heading { align-items:flex-start; flex-wrap:wrap; }.stocktake-heading-meta { width:100%; }.stocktake-heading-meta form,.stocktake-heading-meta button { width:100%; }.stocktake-heading-meta button { justify-content:center; }.stocktake-summary { display:grid; grid-template-columns:repeat(2,1fr); }.stocktake-tools { align-items:flex-start; flex-direction:column; }.stocktake-search { width:100%; min-width:0; }.stocktake-approval { align-items:flex-start; flex-direction:column; }.stocktake-approval form,.stocktake-approval button { width:100%; }.stocktake-approval button { justify-content:center; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var search = document.getElementById('stocktake-search');
    var differences = document.getElementById('stocktake-differences');
    function trimNumber(value) { return value.toLocaleString('es-CL', { maximumFractionDigits: 3 }); }
    function refreshDifference(input) {
        var row = input.closest('tr');
        var cell = row.querySelector('[data-difference]');
        var system = Number(row.querySelector('[data-system-value]').dataset.systemValue || 0);
        if (input.value === '') { cell.textContent = '-'; cell.className = 'text-end stocktake-difference'; return; }
        var difference = Number(input.value) - system;
        cell.textContent = (difference > 0 ? '+' : '') + trimNumber(difference);
        cell.className = 'text-end stocktake-difference ' + (difference > 0 ? 'positive' : (difference < 0 ? 'negative' : ''));
    }
    function refreshCount() { var count = Array.from(document.querySelectorAll('[data-difference]')).filter(function (cell) { return cell.textContent.trim() !== '-' && cell.textContent.trim() !== '0'; }).length; if (differences) differences.textContent = count; }
    document.querySelectorAll('.stocktake-physical').forEach(function (input) { input.addEventListener('input', function () { refreshDifference(input); refreshCount(); }); });
    if (search) search.addEventListener('input', function () { var term = search.value.toLowerCase().trim(); document.querySelectorAll('[data-stocktake-row]').forEach(function (row) { row.hidden = term !== '' && !row.dataset.search.includes(term); }); });
});
</script>
@endsection
