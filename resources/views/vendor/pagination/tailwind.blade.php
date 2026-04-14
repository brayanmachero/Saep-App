@if ($paginator->hasPages())
<nav role="navigation" aria-label="Paginación" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-top:.75rem;">
    {{-- Info text --}}
    <div style="font-size:.78rem;color:var(--text-muted);">
        @if ($paginator->firstItem())
            Mostrando {{ $paginator->firstItem() }} a {{ $paginator->lastItem() }} de {{ $paginator->total() }} resultados
        @else
            Mostrando {{ $paginator->count() }} de {{ $paginator->total() }} resultados
        @endif
    </div>

    {{-- Page buttons --}}
    <div style="display:flex;align-items:center;gap:4px;">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid var(--border-color);color:var(--text-muted);opacity:.4;cursor:default;font-size:.8rem;">
                <i class="bi bi-chevron-left"></i>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid var(--border-color);color:var(--text-primary);text-decoration:none;font-size:.8rem;transition:all .15s;" onmouseover="this.style.borderColor='var(--primary-color)';this.style.color='var(--primary-color)'" onmouseout="this.style.borderColor='var(--border-color)';this.style.color='var(--text-primary)'" aria-label="Anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
        @endif

        {{-- Pages --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 6px;font-size:.78rem;color:var(--text-muted);">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 6px;border-radius:6px;background:var(--primary-color);color:#fff;font-size:.78rem;font-weight:700;">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}" style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 6px;border-radius:6px;border:1px solid var(--border-color);color:var(--text-primary);text-decoration:none;font-size:.78rem;font-weight:500;transition:all .15s;" onmouseover="this.style.borderColor='var(--primary-color)';this.style.color='var(--primary-color)'" onmouseout="this.style.borderColor='var(--border-color)';this.style.color='var(--text-primary)'" aria-label="Ir a página {{ $page }}">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid var(--border-color);color:var(--text-primary);text-decoration:none;font-size:.8rem;transition:all .15s;" onmouseover="this.style.borderColor='var(--primary-color)';this.style.color='var(--primary-color)'" onmouseout="this.style.borderColor='var(--border-color)';this.style.color='var(--text-primary)'" aria-label="Siguiente">
                <i class="bi bi-chevron-right"></i>
            </a>
        @else
            <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid var(--border-color);color:var(--text-muted);opacity:.4;cursor:default;font-size:.8rem;">
                <i class="bi bi-chevron-right"></i>
            </span>
        @endif
    </div>
</nav>
@endif
