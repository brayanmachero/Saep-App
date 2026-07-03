@php
    $saepFooterNote = $note ?? 'Correo generado automaticamente por SAEP.';
    $saepFooterContext = $context ?? null;
    $saepFooterUrl = $siteUrl ?? 'https://saep.cl';
    $saepFooterLabel = $siteLabel ?? 'saep.cl';
@endphp
<tr>
    <td style="background:#0f1b4c;padding:20px 36px;text-align:center;border-top:4px solid #f97316;">
        <p style="font-size:11px;color:rgba(255,255,255,0.68);margin:0 0 8px;line-height:1.6;">
            {{ $saepFooterNote }}
            @if($saepFooterContext)
                <br>{{ $saepFooterContext }}
            @endif
        </p>
        <p style="font-size:11px;color:rgba(255,255,255,0.86);margin:0;line-height:1.6;">
            &copy; {{ date('Y') }} SAEP Servicios Profesionales ·
            <a href="{{ $saepFooterUrl }}" style="color:#ffffff;text-decoration:none;">{{ $saepFooterLabel }}</a>
        </p>
    </td>
</tr>
