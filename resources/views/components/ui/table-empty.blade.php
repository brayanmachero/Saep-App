@props([
    'colspan' => 1,
    'title' => 'No hay información para mostrar',
    'message' => 'Prueba cambiando los filtros o vuelve a intentarlo más tarde.',
    'actionLabel' => null,
    'actionUrl' => null,
])

<tr {{ $attributes }}>
    <td colspan="{{ $colspan }}" class="saep-table-empty-cell">
        <x-ui.data-state
            state="empty"
            compact
            :title="$title"
            :message="$message"
            :action-label="$actionLabel"
            :action-url="$actionUrl"
        />
    </td>
</tr>
