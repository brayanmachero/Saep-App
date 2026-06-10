<div class="form-grid">
    <div class="form-group">
        <label>Cliente <span class="required">*</span></label>
        <select name="cliente_id" class="form-control @error('cliente_id') is-invalid @enderror" required>
            <option value="">-- Seleccionar Cliente --</option>
            @foreach($clientes as $cliente)
            <option value="{{ $cliente->id }}" {{ old('cliente_id', $centroCosto?->cliente_id) == $cliente->id ? 'selected' : '' }}>
                {{ $cliente->nombre_comercial ?? $cliente->nombre }}
            </option>
            @endforeach
        </select>
        @error('cliente_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label>Nombre Centro de Costo <span class="required">*</span></label>
        <input type="text" name="nombre" value="{{ old('nombre', $centroCosto?->nombre) }}"
               class="form-control @error('nombre') is-invalid @enderror"
               placeholder="Ej: Planta Renca, CD Quilicura" required>
        @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>
