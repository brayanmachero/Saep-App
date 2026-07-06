@php
    $puedeGestionarCostos = auth()->user()->puedeGestionarCostosDescargaContenedores();
    $items = [
        ['route' => 'descarga-contenedores.index', 'match' => 'descarga-contenedores.index', 'label' => 'Registros', 'icon' => 'bi-table', 'help' => 'Listado operativo para revisar, validar y editar descargas.'],
        ['route' => 'descarga-contenedores.carga-rapida', 'match' => 'descarga-contenedores.carga-rapida', 'label' => 'Programación', 'icon' => 'bi-clipboard-plus', 'ability' => 'puede_crear', 'help' => 'Carga masiva desde tablas copiadas de correo o Excel.'],
        ['route' => 'descarga-contenedores.cargas', 'match' => 'descarga-contenedores.cargas', 'label' => 'Cargas', 'icon' => 'bi-clock-history', 'ability' => 'puede_crear', 'help' => 'Historial de tandas importadas desde programación.'],
        ['route' => 'descarga-contenedores.dotacion', 'match' => 'descarga-contenedores.dotacion', 'label' => 'Dotación', 'icon' => 'bi-people', 'requires_costs' => true, 'help' => 'Nómina Talana disponible para asignar a descargas.'],
        ['route' => 'descarga-contenedores.liquidacion', 'match' => 'descarga-contenedores.liquidacion', 'label' => 'Liquidación', 'icon' => 'bi-cash-stack', 'requires_costs' => true, 'help' => 'Resumen económico por trabajador según registros validados.'],
        ['route' => 'descarga-contenedores.reportes', 'match' => 'descarga-contenedores.reportes', 'label' => 'Reportes', 'icon' => 'bi-bar-chart', 'requires_costs' => true, 'help' => 'Análisis por operación, centro, FACT y periodo.'],
        ['route' => 'descarga-contenedores.tarifas', 'match' => 'descarga-contenedores.tarifas', 'label' => 'Tarifas FACT', 'icon' => 'bi-tags', 'requires_costs' => true, 'help' => 'Mantenedor de códigos FACT, costos y pagos de referencia.'],
    ];
@endphp

<nav class="contenedores-nav" aria-label="Secciones de Contenedores">
    @foreach($items as $item)
        @continue(isset($item['ability']) && !auth()->user()->tieneAcceso('descarga_contenedores', $item['ability']))
        @continue(($item['requires_costs'] ?? false) && !$puedeGestionarCostos)
        <a href="{{ route($item['route']) }}"
           class="contenedores-nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}"
           title="{{ $item['help'] }}"
           aria-label="{{ $item['label'] }}: {{ $item['help'] }}">
            <i class="bi {{ $item['icon'] }}"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>

<style>
.contenedores-nav {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    margin: 0 0 1rem;
    padding: .45rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-card);
}
.contenedores-nav-link {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    min-height: 38px;
    padding: .5rem .72rem;
    border-radius: 7px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: .86rem;
    font-weight: 700;
    white-space: nowrap;
}
.contenedores-nav-link:hover,
.contenedores-nav-link.active {
    color: var(--primary-color);
    background: rgba(15, 27, 76, .08);
}
@media (max-width: 640px) {
    .contenedores-nav-link { flex: 1 1 45%; justify-content: center; }
}
</style>
