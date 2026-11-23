@if(auth()->user()->esAdminSistema() && auth()->user()->tieneAcceso('comercial', 'puede_eliminar'))
@once
@push('styles')
<style>
    .quote-delete-dialog {
        position: fixed;
        inset: 0;
        margin: auto;
        width: min(520px, calc(100vw - 32px));
        max-height: calc(100dvh - 32px);
        overflow-y: auto;
        padding: 24px;
        border: 1px solid var(--surface-border, #e5e7eb);
        border-radius: 16px;
        background: var(--surface-color, #fff);
        color: var(--text-primary, #111827);
        box-shadow: 0 24px 64px rgba(0, 0, 0, .25);
        text-align: left;
        white-space: normal;
        font-size: 14px;
        line-height: 1.55;
    }
    .quote-delete-dialog::backdrop { background: rgba(15, 23, 42, .55); }
    .quote-delete-dialog form { display: grid; gap: 16px; }
    .quote-delete-dialog h2 { margin: 0; font-size: 20px; line-height: 1.3; }
    .quote-delete-reference { padding: 12px; background: var(--bg-color, #f3f4f6); border-radius: 8px; overflow-wrap: anywhere; }
    .quote-delete-reference strong, .quote-delete-reference span { display: block; }
    .quote-delete-reference span { margin-top: 4px; color: var(--text-muted, #6b7280); }
    .quote-delete-dialog label { display: block; font-weight: 600; margin-bottom: 6px; }
    .quote-delete-dialog textarea { display: block; width: 100%; min-height: 100px; padding: 12px; border: 1px solid var(--surface-border, #e5e7eb); border-radius: 8px; background: var(--surface-color, #fff); color: inherit; font: inherit; resize: vertical; }
    .quote-delete-dialog textarea:focus-visible { outline: 2px solid var(--primary-color, #1e3a8a); outline-offset: 2px; }
    .quote-delete-actions { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    .quote-delete-actions button { min-height: 44px; }
    .quote-delete-confirm { background: #b91c1c; color: #fff; border: 1px solid #b91c1c; border-radius: 8px; padding: 10px 16px; font: inherit; font-weight: 600; cursor: pointer; }
    .quote-delete-confirm:hover { background: #991b1b; }
    @media (max-width: 480px) { .quote-delete-dialog { padding: 20px; } .quote-delete-actions button { flex: 1 1 100%; } }
</style>
@endpush
@endonce
<button type="button" class="btn-secondary" style="color:var(--danger-color)" onclick="document.getElementById('eliminar-cotizacion-{{ $cotizacion->id }}').showModal()" aria-label="Eliminar cotización {{ $cotizacion->numero }}">
    <i class="bi bi-trash-fill" aria-hidden="true"></i> Eliminar
</button>
<dialog class="quote-delete-dialog" id="eliminar-cotizacion-{{ $cotizacion->id }}" aria-labelledby="eliminar-titulo-{{ $cotizacion->id }}" aria-describedby="eliminar-descripcion-{{ $cotizacion->id }}">
    <form method="POST" action="{{ route('comercial.cotizaciones.destroy', $cotizacion) }}">
        @csrf @method('DELETE')
        <h2 id="eliminar-titulo-{{ $cotizacion->id }}">Eliminar cotización</h2>
        <div class="quote-delete-reference"><strong>{{ $cotizacion->numero }}</strong><span>{{ $cotizacion->cargo }} · {{ $cotizacion->titulo }}</span></div>
        <p id="eliminar-descripcion-{{ $cotizacion->id }}">Se retirará del cotizador y de las consultas de precios para prefacturar. Se conservarán sus antecedentes y el registro de eliminación.</p>
        <div>
        <label for="eliminar-motivo-{{ $cotizacion->id }}">Motivo de eliminación</label>
        <textarea id="eliminar-motivo-{{ $cotizacion->id }}" name="motivo" required minlength="5" maxlength="1000" rows="3" placeholder="Por ejemplo: cotización creada para pruebas"></textarea>
        </div>
        <div class="quote-delete-actions">
            <button type="button" class="btn-secondary" onclick="this.closest('dialog').close()">Cancelar</button>
            <button type="submit" class="quote-delete-confirm">Confirmar eliminación</button>
        </div>
    </form>
</dialog>
@endif
