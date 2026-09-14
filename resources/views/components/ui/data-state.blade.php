@props([
    'state' => 'empty',
    'title' => null,
    'message' => null,
    'actionLabel' => null,
    'actionUrl' => null,
    'retryEvent' => null,
    'compact' => false,
])

@php
    $states = [
        'loading' => [
            'icon' => 'arrow-repeat',
            'title' => 'Cargando información',
            'message' => 'Estamos preparando los datos solicitados.',
        ],
        'empty' => [
            'icon' => 'inbox',
            'title' => 'No hay información para mostrar',
            'message' => 'Prueba cambiando los filtros o vuelve a intentarlo más tarde.',
        ],
        'error' => [
            'icon' => 'exclamation-triangle',
            'title' => 'No fue posible cargar la información',
            'message' => 'La información no se modificó. Puedes volver a intentarlo.',
        ],
    ];
    $state = array_key_exists($state, $states) ? $state : 'empty';
    $copy = $states[$state];
@endphp

<section
    {{ $attributes->class([
        'saep-data-state',
        'saep-data-state--compact' => $compact,
        'is-' . $state,
    ]) }}
    data-saep-state="{{ $state }}"
    role="{{ $state === 'error' ? 'alert' : 'status' }}"
    aria-live="{{ $state === 'error' ? 'assertive' : 'polite' }}"
    @if($state === 'loading') aria-busy="true" @endif
>
    <i class="bi bi-{{ $copy['icon'] }} saep-data-state__icon {{ $state === 'loading' ? 'saep-data-state__spinner' : '' }}" aria-hidden="true"></i>
    <div class="saep-data-state__body">
        <h3 class="saep-data-state__title">{{ $title ?? $copy['title'] }}</h3>
        <p class="saep-data-state__message">{{ $message ?? $copy['message'] }}</p>

        @if(in_array($state, ['empty', 'error'], true) && $actionUrl)
            <a href="{{ $actionUrl }}" class="btn-ghost saep-data-state__action">
                <i class="bi bi-{{ $state === 'error' ? 'arrow-clockwise' : 'plus-lg' }}"></i> {{ $actionLabel ?? ($state === 'error' ? 'Reintentar' : 'Crear registro') }}
            </a>
        @elseif($state === 'error' && $retryEvent)
            <button type="button" class="btn-ghost saep-data-state__action" data-saep-state-retry data-saep-retry-event="{{ $retryEvent }}">
                <i class="bi bi-arrow-clockwise"></i> {{ $actionLabel ?? 'Reintentar' }}
            </button>
        @endif
    </div>
</section>
