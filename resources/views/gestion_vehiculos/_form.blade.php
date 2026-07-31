@php($editing = $vehiculo !== null)
<div class="fleet-form-grid">
    <div class="fleet-field"><label>Codigo operativo</label><input name="codigo_interno" maxlength="30" value="{{ old('codigo_interno', $vehiculo?->codigo_interno) }}" placeholder="Ej: 41"></div>
    <div class="fleet-field"><label>Patente</label><input name="patente" maxlength="16" required value="{{ old('patente', $vehiculo?->patente) }}" placeholder="Ej: AB1234"></div>
    <div class="fleet-field"><label>Nombre operativo</label><input name="nombre" maxlength="120" value="{{ old('nombre', $vehiculo?->nombre) }}" placeholder="Ej: Movil Bodega 1"></div>
    <div class="fleet-field"><label>Marca</label><input name="marca" maxlength="80" value="{{ old('marca', $vehiculo?->marca) }}" placeholder="Ej: Toyota"></div>
    <div class="fleet-field"><label>Modelo</label><input name="modelo" maxlength="120" value="{{ old('modelo', $vehiculo?->modelo) }}" placeholder="Ej: Hilux"></div>
    <div class="fleet-field"><label>Tipo</label><input name="tipo" maxlength="40" required value="{{ old('tipo', $vehiculo?->tipo ?: 'AUTOMOVIL') }}" placeholder="Automovil, camioneta..."></div>
    <div class="fleet-field"><label>Capacidad</label><input name="capacidad" type="number" min="1" max="99" value="{{ old('capacidad', $vehiculo?->capacidad) }}" placeholder="Personas"></div>
    <div class="fleet-field"><label>Sede</label><input name="sede" maxlength="120" value="{{ old('sede', $vehiculo?->sede) }}" placeholder="Ej: Casa matriz"></div>
    <div class="fleet-field"><label>Color</label><input name="color" maxlength="60" value="{{ old('color', $vehiculo?->color) }}"></div>
    <div class="fleet-field"><label>Estado</label><select name="estado" required>@foreach(\App\Models\Vehiculo::ESTADOS as $value => $label)<option value="{{ $value }}" @selected(old('estado', $vehiculo?->estado ?: 'DISPONIBLE') === $value)>{{ $label }}</option>@endforeach</select></div>
    <label class="fleet-checkbox"><input type="hidden" name="reservas_habilitadas" value="0"><input type="checkbox" name="reservas_habilitadas" value="1" @checked(old('reservas_habilitadas', $vehiculo?->reservas_habilitadas ?? true))> Habilitado para reservas</label>
    <div class="fleet-field wide"><label>Observacion interna</label><textarea name="observacion" maxlength="2000" placeholder="Restricciones, mantencion programada u otra referencia operativa">{{ old('observacion', $vehiculo?->observacion) }}</textarea></div>
</div>
