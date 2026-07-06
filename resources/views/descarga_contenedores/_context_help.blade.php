@php
    $title = $title ?? 'Ayuda';
    $items = collect($items ?? [])->filter();
    $tone = $tone ?? 'info';
@endphp

@if($items->isNotEmpty())
<div class="context-help context-help-{{ $tone }}">
    <div class="context-help-title">
        <i class="bi bi-info-circle"></i>
        <span>{{ $title }}</span>
    </div>
    <ul>
        @foreach($items as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</div>
@once
<style>
.context-help {
    display: grid;
    gap: .5rem;
    margin: 0 0 1rem;
    padding: .75rem .9rem;
    border: 1px solid var(--surface-border);
    border-left-width: 4px;
    border-radius: 8px;
    background: var(--surface-color);
    color: var(--text-main);
}
.context-help-info { border-left-color: var(--primary-color); }
.context-help-warning { border-left-color: #d97706; }
.context-help-success { border-left-color: var(--success-color); }
.context-help-title {
    display: flex;
    align-items: center;
    gap: .45rem;
    font-weight: 800;
    font-size: .86rem;
}
.context-help-title i { color: var(--primary-color); }
.context-help-warning .context-help-title i { color: #d97706; }
.context-help-success .context-help-title i { color: var(--success-color); }
.context-help ul {
    margin: 0;
    padding-left: 1.15rem;
    color: var(--text-muted);
    font-size: .82rem;
    line-height: 1.45;
}
.context-help li + li { margin-top: .2rem; }
.help-tooltip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    margin-left: .25rem;
    border-radius: 999px;
    border: 1px solid var(--surface-border);
    color: var(--text-muted);
    font-size: .68rem;
    cursor: help;
    vertical-align: middle;
}
.help-tooltip:hover {
    color: var(--primary-color);
    border-color: rgba(15,27,76,.28);
    background: rgba(15,27,76,.06);
}
</style>
@endonce
@endif
