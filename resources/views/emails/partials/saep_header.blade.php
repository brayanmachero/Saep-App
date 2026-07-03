@php
    $saepHeaderLogo = $logoUrl ?? asset('brand/wp/logo-saep-email.png');
    $saepHeaderSubtitle = $subtitle ?? 'Sistema de gestion operacional y prevencion';
    $saepHeaderModule = $module ?? null;
    $saepHeaderBadge = $badge ?? null;
    $saepHeaderBadgeColor = $badgeColor ?? '#10b981';
    $saepHeaderAccent = $accentColor ?? '#f97316';
    $saepHeaderBackground = $background ?? '#0f1b4c';
@endphp
<tr>
    <td style="background:{{ $saepHeaderBackground }};padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td style="padding:24px 36px;vertical-align:middle;">
                    <img src="{{ $saepHeaderLogo }}" alt="SAEP" width="160" style="display:block;width:160px;max-width:160px;height:auto;border:0;">
                    <p style="color:rgba(255,255,255,0.72);font-size:11px;margin:8px 0 0;text-transform:uppercase;font-weight:700;">
                        {{ $saepHeaderSubtitle }}
                    </p>
                    @if($saepHeaderModule)
                        <p style="color:rgba(255,255,255,0.86);font-size:13px;margin:8px 0 0;">
                            {{ $saepHeaderModule }}
                        </p>
                    @endif
                </td>
                @if($saepHeaderBadge)
                    <td width="190" style="padding:24px 36px 24px 0;text-align:right;vertical-align:middle;">
                        <span style="background:{{ $saepHeaderBadgeColor }};color:#ffffff;padding:7px 14px;border-radius:999px;font-size:11px;font-weight:800;text-transform:uppercase;display:inline-block;">
                            {{ $saepHeaderBadge }}
                        </span>
                    </td>
                @endif
            </tr>
        </table>
    </td>
</tr>
<tr>
    <td style="height:4px;background:{{ $saepHeaderAccent }};font-size:0;line-height:0;">&nbsp;</td>
</tr>
