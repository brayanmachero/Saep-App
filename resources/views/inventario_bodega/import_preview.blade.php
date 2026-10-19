@extends('layouts.app')
@section('content')
<div style="max-width:1200px;margin:24px auto;padding:20px;background:var(--bs-body-bg,#fff);border:1px solid #ddd;border-radius:12px">
    <h1 style="font-size:24px">Revisar importación de catálogo</h1>
    <p>{{ count($preview['rows']) }} filas revisadas. Todavía no se ha guardado ningún producto ni movimiento.</p>
    <p>Una fila por talla. Los datos vacíos de clasificación conservan la información existente. El precio vacío o cero no reemplaza un precio registrado.</p>
    <p><strong>Stock inicial fija el saldo de la talla y bodega.</strong> Para sumar una compra y registrar proveedor/factura utiliza Importar ingresos.</p>
    @if($preview['errors'])
        <div role="alert" style="padding:16px;border:1px solid #dc3545;border-radius:8px;margin:16px 0">
            <strong>Corrige el archivo antes de importarlo</strong>
            <ul>@foreach($preview['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div style="overflow:auto;max-height:55vh;margin:16px 0">
        <table class="table table-striped" style="min-width:850px">
            <thead><tr><th>Fila</th><th>Acción</th><th>Código</th><th>Producto</th><th>Categoría / subcategoría</th><th>Talla</th><th>Precio neto informado</th><th>Bodega</th><th>Saldo a fijar</th></tr></thead>
            <tbody>@foreach($preview['rows'] as $row)<tr>
                <td>{{ $row['fila'] }}</td><td>{{ $row['accion'] }}</td><td>{{ $row['codigo'] ?: 'Automático' }}</td><td>{{ $row['producto'] }}</td><td>{{ $row['categoria'] }} / {{ $row['subcategoria'] }}</td><td>{{ $row['talla'] }}</td><td>{{ $row['precio'] === '' ? 'Sin cambio / sin información' : $row['precio'] }}</td><td>{{ $row['ubicacion'] ?: 'No aplica' }}</td><td>{{ $row['saldo'] === '' ? 'Sin cambio' : $row['saldo'] }}</td>
            </tr>@endforeach</tbody>
        </table>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
        <a class="btn btn-light" href="{{ route('inventario-bodega.index', ['vista'=>'catalogo']) }}">Volver sin importar</a>
        @if($token && ! $preview['errors'])
            <form method="POST" action="{{ route('inventario-bodega.productos.importar') }}">@csrf
                <input type="hidden" name="confirmacion" value="{{ $token }}">
                <button class="btn btn-primary" type="submit">Confirmar importación</button>
            </form>
            <small>Esta revisión vence en 30 minutos. Al confirmar se vuelve a validar el archivo.</small>
        @endif
    </div>
</div>
@endsection
