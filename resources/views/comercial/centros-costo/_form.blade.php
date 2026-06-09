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
        <label>Código <span class="required">*</span></label>
        <input type="text" name="codigo" value="{{ old('codigo', $centroCosto?->codigo) }}" class="form-control @error('codigo') is-invalid @enderror" required>
        @error('codigo')<span class="form-error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label>Nombre <span class="required">*</span></label>
        <input type="text" name="nombre" value="{{ old('nombre', $centroCosto?->nombre) }}" class="form-control @error('nombre') is-invalid @enderror" required>
        @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label>Ubicación</label>
        <input type="text" name="ubicacion" value="{{ old('ubicacion', $centroCosto?->ubicacion) }}" class="form-control">
    </div>
    <div class="form-group">
        <label>Responsable</label>
        <input type="text" name="responsable" value="{{ old('responsable', $centroCosto?->responsable) }}" class="form-control">
    </div>
    <div class="form-group">
        <label>Email Responsable</label>
        <input type="email" name="email_responsable" value="{{ old('email_responsable', $centroCosto?->email_responsable) }}" class="form-control">
    </div>
    <div class="form-group">
        <label>Estado</label>
        <select name="estado" class="form-control">
            <option value="activo" {{ old('estado', $centroCosto?->estado ?? 'activo') === 'activo' ? 'selected' : '' }}>Activo</option>
            <option value="inactivo" {{ old('estado', $centroCosto?->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
        </select>
    </div>
</div>
<div class="form-group">
    <label>Descripción</label>
    <textarea name="descripcion" class="form-control" rows="3">{{ old('descripcion', $centroCosto?->descripcion) }}</textarea>
</div>
