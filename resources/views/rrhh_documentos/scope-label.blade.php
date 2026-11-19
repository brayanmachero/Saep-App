@php($visibleScope=array_filter($scope ?? [],fn($value)=>filled($value)))
@forelse($visibleScope as $field=>$value)
{{ ['empresa_id'=>'Empresa','centro_costo_nombre'=>'Centro','cargo_nombre'=>'Cargo','tipo_contrato_nombre'=>'Contrato'][$field] ?? $field }}:
{{ $field==='empresa_id' ? ($companies->firstWhere('empresa_id',$value)?->empresa_nombre ?? ('#'.$value)) : $value }}{{ !$loop->last ? ' · ' : '' }}
@empty Todos los trabajadores @endforelse
