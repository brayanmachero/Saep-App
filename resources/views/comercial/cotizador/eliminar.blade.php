@if(auth()->user()->esAdminSistema() && auth()->user()->tieneAcceso('comercial', 'puede_eliminar'))
<button type="button" class="btn-secondary" style="color:var(--danger-color)" onclick="document.getElementById('eliminar-cotizacion-{{ $cotizacion->id }}').showModal()" aria-label="Eliminar cotización {{ $cotizacion->numero }}">
    <i class="bi bi-trash-fill" aria-hidden="true"></i> Eliminar
</button>
<dialog id="eliminar-cotizacion-{{ $cotizacion->id }}" aria-labelledby="eliminar-titulo-{{ $cotizacion->id }}" style="width:min(480px,90vw);border:1px solid var(--surface-border);border-radius:12px;padding:1.5rem;background:var(--surface-color);color:var(--text-primary);text-align:left;white-space:normal">
    <form method="POST" action="{{ route('comercial.cotizaciones.destroy', $cotizacion) }}">
        @csrf @method('DELETE')
        <h2 id="eliminar-titulo-{{ $cotizacion->id }}" style="font-size:1.1rem">Eliminar {{ $cotizacion->numero }}</h2>
        <p>{{ $cotizacion->cargo }} · {{ $cotizacion->titulo }}</p>
        <p>Se retirará del cotizador y de las consultas de precios para prefacturar. Se conservarán sus antecedentes y el registro de eliminación.</p>
        <label for="eliminar-motivo-{{ $cotizacion->id }}">Motivo de eliminación</label>
        <textarea id="eliminar-motivo-{{ $cotizacion->id }}" name="motivo" required minlength="5" maxlength="1000" rows="3" style="display:block;width:100%;margin:.5rem 0 1rem" placeholder="Por ejemplo: cotización creada para pruebas"></textarea>
        <div style="display:flex;gap:.5rem;justify-content:flex-end">
            <button type="button" class="btn-secondary" onclick="this.closest('dialog').close()">Cancelar</button>
            <button type="submit" class="btn-premium">Confirmar eliminación</button>
        </div>
    </form>
</dialog>
@endif
