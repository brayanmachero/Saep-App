<div class="doc-composition-fields">
<label>Función de esta batería<select name="kind">
@foreach(['base'=>'Base común obligatoria','specific'=>'Específica (con base heredada)'] as $value=>$label)<option value="{{ $value }}" @selected(old('kind',$record->kind ?? 'base')===$value)>{{ $label }}</option>@endforeach
@if(isset($record) && $record->kind==='independent')<option value="independent" @selected(old('kind',$record->kind)==='independent')>Anterior · pendiente de clasificar</option>@endif
</select></label>
<p class="muted">Base: incluye sólo lo obligatorio para todos en su alcance (por ejemplo, empresa EST). Específica: selecciona la base y, si corresponde, otra batería de cliente o centro; agrega aquí únicamente los documentos adicionales. Los alcances se combinan.</p>
<fieldset><legend>Heredar documentos de estas versiones</legend>
<p class="muted">Sólo para baterías específicas. Puedes combinar centro y cargo si sus reglas son compatibles. Puedes preparar la composición mientras la base está en borrador; ambas deben validarse y la base activarse primero. No selecciones versiones si estás creando una base.</p>
<div class="doc-parent-choices">
@forelse($parentChoices as $parent)
<label class="check"><input type="checkbox" name="parent_ids[]" value="{{ $parent->id }}" @checked(in_array($parent->id,old('parent_ids',isset($record) ? (json_decode($record->parent_ids ?? '[]',true) ?: []) : [])))><span><strong>{{ $parent->nombre }} · v{{ $parent->version }} · {{ $parent->estado==='ACTIVA' ? 'Activa' : 'En preparación' }}</strong><small>{{ $parent->kind==='base' ? 'Base obligatoria' : 'Batería específica' }} · @include('rrhh_documentos.scope-label',['scope'=>json_decode($parent->reglas,true)])</small></span></label>
@empty<p class="note">Aún no hay bases disponibles. Primero crea la base común; después podrás vincularla a una específica.</p>@endforelse
</div></fieldset>
</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('.doc-composition-fields').forEach(root=>{
  const kind=root.querySelector('[name=kind]'), parents=root.querySelector('fieldset');
  const update=()=>{parents.hidden=kind.value!=='specific';parents.disabled=kind.value!=='specific';};
  kind.addEventListener('change',update);update();
 });
});
</script>
@endpush
@endonce
