@extends('layouts.app')
@section('content')
<div class="catalog-review" style="max-width:1200px;margin:24px auto;padding:20px;background:var(--bs-body-bg,#fff);border:1px solid #ddd;border-radius:12px">
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
<style>
.catalog-review{color:#19233b;box-shadow:0 4px 18px #17223b0a}
.catalog-review h1{font-weight:700;margin:0 0 12px}
.catalog-review p{font-size:14px;line-height:1.6;margin:8px 0;color:#56627a}
.catalog-review table{width:100%;border-collapse:collapse;font-size:13px;text-align:left}
.catalog-review th{padding:12px;background:#f2f4f8;color:#526079;font-size:11px;text-transform:uppercase;position:sticky;top:0}
.catalog-review td{padding:12px;border-bottom:1px solid #e4e8ef;vertical-align:top}
.catalog-review tbody tr:nth-child(even){background:#f8fafc}
.catalog-review .btn{display:inline-flex;align-items:center;min-height:40px;padding:9px 16px;border:1px solid #dce1ea;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer}
.catalog-review .btn-light{background:#fff;color:#293552}
.catalog-review .btn-primary{background:#2d0b64;border-color:#2d0b64;color:#fff}
.catalog-review small{font-size:12px;color:#65718a}
body.dark-mode .catalog-review{background:#172033!important;color:#e2e8f0}
body.dark-mode .catalog-review p,body.dark-mode .catalog-review small{color:#cbd5e1}
body.dark-mode .catalog-review th,body.dark-mode .catalog-review tbody tr:nth-child(even){background:#202c42;color:#e2e8f0}
@media(max-width:600px){.catalog-review{margin:12px 0!important;padding:16px!important}.catalog-review h1{font-size:20px!important}}
</style>
@endsection
