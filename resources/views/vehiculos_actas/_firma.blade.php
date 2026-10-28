<div class="sig-panel"><div class="sig"><canvas width="900" height="300" data-signature="{{ $campo }}" aria-label="{{ $label }}" role="img"></canvas><small>Firma con tu dedo, lápiz o mouse</small></div>
<input type="hidden" name="{{ $campo }}" value="" data-signature-value="{{ $campo }}">
<div class="sig-tools"><span class="muted" data-signature-status="{{ $campo }}">Sin firma</span><button type="button" data-signature-expand aria-expanded="false">Ampliar espacio de firma</button><button type="button" data-signature-clear="{{ $campo }}">Borrar y volver a firmar</button></div>
</div>
