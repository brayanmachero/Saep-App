@php
    $saepButtonUrl = $url ?? '#';
    $saepButtonLabel = $label ?? 'Ver detalle';
    $saepButtonColor = $color ?? '#0f1b4c';
@endphp
<table cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 24px;">
    <tr>
        <td style="background:{{ $saepButtonColor }};border-radius:8px;padding:13px 30px;text-align:center;">
            <a href="{{ $saepButtonUrl }}" style="color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;display:inline-block;">
                {{ $saepButtonLabel }}
            </a>
        </td>
    </tr>
</table>
