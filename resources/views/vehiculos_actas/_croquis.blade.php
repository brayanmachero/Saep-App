<div class="vehicle-sketch" data-vehicle-sketch>
    <div class="sketch-heading"><span class="pill">Guía de carrocería</span><span class="muted">Izquierda y derecha según el conductor</span></div>
    <div class="sketch-tabs" role="group" aria-label="Vista del vehículo">
        @foreach(['izquierdo'=>'Lado izquierdo','derecho'=>'Lado derecho','frente'=>'Frente','trasera'=>'Parte trasera','superior'=>'Vista superior'] as $key=>$name)
            <button class="btn" type="button" data-sketch-view="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $name }} <span data-sketch-count="{{ $key }}"></span></button>
        @endforeach
    </div>
    <div class="sketch-surface"><canvas width="900" height="500" data-sketch-canvas aria-label="Guía del vehículo: toca una zona para marcar un detalle con un círculo" role="img"></canvas></div>
    <p class="muted sketch-hint">Toca el detalle para colocar un círculo rojo. Para elegir su tamaño, mantén presionado y arrastra al crear la marca.</p>
    <div class="actions"><button type="button" class="btn" data-sketch-undo disabled>Deshacer última marca</button><button type="button" class="btn" data-sketch-clear disabled>Limpiar esta vista</button></div>
    <p class="muted" data-sketch-status role="status" aria-live="polite">Sin marcas. La guía sin círculos no se adjunta al acta.</p>
    <input type="hidden" name="croquis" data-sketch-value value="">
    <p class="muted">Guía esquemática, no a escala. El acta incluirá las cinco vistas con las marcas que agregues. Describe cada detalle en las observaciones y adjunta fotografías.</p>
</div>
<style>
.sketch-heading{display:flex;gap:10px;justify-content:space-between;align-items:center;margin:16px 0}.sketch-tabs{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px}.sketch-tabs .btn{font-size:12px;padding:10px 12px;min-height:44px}.sketch-tabs [aria-pressed=true]{background:#18243d;color:white;border-color:#18243d}.sketch-surface{border:1px solid #dce3ed;border-radius:12px;overflow:hidden;background:white}.sketch-surface canvas{display:block;width:100%;height:auto;aspect-ratio:9/5;touch-action:none;cursor:crosshair}.sketch-hint{margin:10px 0 16px}.sketch-tabs span:not(:empty){border-radius:10px;background:#fce7e7;color:#b42318;padding:0 6px}@media(max-width:600px){.sketch-heading{display:block}.sketch-heading .muted{display:block;margin-top:8px}.sketch-tabs{display:grid;grid-template-columns:1fr 1fr}.sketch-tabs .btn{padding:10px 5px}.vehicle-sketch .actions{flex-direction:column}.vehicle-sketch .actions .btn{width:100%}}
</style>
@push('scripts')<script src="{{ asset('js/vehiculo-croquis.js') }}?v={{ filemtime(public_path('js/vehiculo-croquis.js')) }}" defer></script>@endpush
