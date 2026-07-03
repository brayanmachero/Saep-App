<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Contratos por Vencer</title></head>
<body style="font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:#eef1f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef1f6;padding:40px 16px;">
<tr><td align="center">
<table width="700" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(15,27,76,0.06);">

    @include('emails.partials.saep_header', [
        'module' => 'Talana · contratos',
        'badge' => $cntUrgente > 0 ? 'Alerta urgente' : 'Recordatorio',
        'badgeColor' => $cntUrgente > 0 ? '#dc2626' : '#f59e0b',
        'accentColor' => $cntUrgente > 0 ? '#dc2626' : '#f59e0b',
    ])

    {{-- Título --}}
    <tr><td style="padding:36px 40px 20px;">
        <h1 style="font-size:20px;font-weight:700;color:#0f1b4c;margin:0 0 6px;">
            @if($cntUrgente > 0) Alerta: contratos por vencer @else Recordatorio: contratos por vencer @endif
        </h1>
        <p style="font-size:13px;color:#64748b;margin:0 0 20px;">
            Generado el {{ $generadoEn }} — Umbral: {{ $umbralDias }} días — {{ count($porCC) }} centro(s) de costo
        </p>

        {{-- Resumen --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
        <tr>
            <td width="33%" style="padding-right:6px;">
                <div style="background:{{ $cntUrgente > 0 ? '#fef2f2' : '#f8fafc' }};border:1px solid {{ $cntUrgente > 0 ? '#fecaca' : '#e2e8f0' }};border-radius:10px;padding:14px 16px;text-align:center;">
                    <p style="font-size:26px;font-weight:800;color:{{ $cntUrgente > 0 ? '#dc2626' : '#94a3b8' }};margin:0;">{{ $cntUrgente }}</p>
                    <p style="font-size:11px;color:#64748b;margin:4px 0 0;">🔴 Urgentes (≤ 7 días)</p>
                </div>
            </td>
            <td width="33%" style="padding:0 3px;">
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;text-align:center;">
                    <p style="font-size:26px;font-weight:800;color:#f59e0b;margin:0;">{{ $cntNormal }}</p>
                    <p style="font-size:11px;color:#64748b;margin:4px 0 0;">⚠️ Próximos (8–{{ $umbralDias }}d)</p>
                </div>
            </td>
            <td width="33%" style="padding-left:6px;">
                <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:14px 16px;text-align:center;">
                    <p style="font-size:26px;font-weight:800;color:#0369a1;margin:0;">{{ count($porCC) }}</p>
                    <p style="font-size:11px;color:#64748b;margin:4px 0 0;">📍 Centros de costo</p>
                </div>
            </td>
        </tr>
        </table>

        {{-- Una sección por Centro de Costo --}}
        @foreach($porCC as $cc => $contratos)
        @php
            $ccUrgente   = count(array_filter($contratos, fn($c) => $c['diasRestantes'] <= 7));
            $hayUrgente  = $ccUrgente > 0;
            $hBg         = $hayUrgente ? '#fef2f2' : '#fffbeb';
            $hBorder     = $hayUrgente ? '#fecaca' : '#fde68a';
            $hColor      = $hayUrgente ? '#7f1d1d' : '#78350f';
        @endphp
        <div style="margin-bottom:22px;">
            {{-- Encabezado CC --}}
            <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="background:{{ $hBg }};border:1px solid {{ $hBorder }};border-radius:8px 8px 0 0;padding:9px 14px;">
                    <span style="font-size:13px;font-weight:700;color:{{ $hColor }};">📍 {{ $cc }}</span>
                    <span style="font-size:11px;color:#64748b;margin-left:8px;">
                        {{ count($contratos) }} contrato(s)
                        @if($ccUrgente > 0)
                            — <span style="color:#dc2626;font-weight:700;">{{ $ccUrgente }} urgente(s)</span>
                        @endif
                    </span>
                </td>
            </tr>
            </table>
            {{-- Tabla de trabajadores del CC --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $hBorder }};border-top:none;border-radius:0 0 8px 8px;overflow:hidden;font-size:12px;">
                <tr style="background:{{ $hBg }};">
                    <th style="padding:7px 12px;text-align:left;color:{{ $hColor }};font-weight:700;border-bottom:1px solid {{ $hBorder }};">Trabajador</th>
                    <th style="padding:7px 12px;text-align:left;color:{{ $hColor }};font-weight:700;border-bottom:1px solid {{ $hBorder }};">RUT</th>
                    <th style="padding:7px 12px;text-align:left;color:{{ $hColor }};font-weight:700;border-bottom:1px solid {{ $hBorder }};">Cargo</th>
                    <th style="padding:7px 12px;text-align:left;color:{{ $hColor }};font-weight:700;border-bottom:1px solid {{ $hBorder }};">Tipo Contrato</th>
                    <th style="padding:7px 12px;text-align:left;color:{{ $hColor }};font-weight:700;border-bottom:1px solid {{ $hBorder }};">Vence</th>
                    <th style="padding:7px 12px;text-align:center;color:{{ $hColor }};font-weight:700;border-bottom:1px solid {{ $hBorder }};">Días</th>
                </tr>
                @foreach($contratos as $c)
                @php
                    $emp     = $c['empleadoDetails'];
                    $nombre  = trim("{$emp['nombre']} {$emp['apellidoPaterno']} " . ($emp['apellidoMaterno'] ?? ''));
                    $hasta   = \Carbon\Carbon::parse($c['hasta'])->format('d/m/Y');
                    $tipo    = $c['tipoContratoDetails']['nombre'] ?? '—';
                    $esUrg   = $c['diasRestantes'] <= 7;
                    $badgeBg = $esUrg ? '#dc2626' : '#f59e0b';
                    $rowBg   = $loop->even ? ($esUrg ? '#fff5f5' : '#fffdf0') : '#ffffff';
                    $diasTxt = $c['diasRestantes'] == 0 ? 'HOY' : ($c['diasRestantes'] == 1 ? '1 día' : "{$c['diasRestantes']} días");
                @endphp
                <tr style="background:{{ $rowBg }};border-bottom:1px solid {{ $loop->last ? 'transparent' : $hBorder }};">
                    <td style="padding:7px 12px;color:#1e293b;font-weight:600;">{{ $nombre }}</td>
                    <td style="padding:7px 12px;color:#475569;">{{ $emp['rut'] ?? '—' }}</td>
                    <td style="padding:7px 12px;color:#475569;">{{ $c['cargo'] ?? '—' }}</td>
                    <td style="padding:7px 12px;color:#475569;">{{ $tipo }}</td>
                    <td style="padding:7px 12px;color:{{ $esUrg ? '#dc2626' : '#b45309' }};font-weight:700;">{{ $hasta }}</td>
                    <td style="padding:7px 12px;text-align:center;">
                        <span style="background:{{ $badgeBg }};color:#fff;border-radius:20px;padding:2px 10px;font-weight:700;font-size:11px;white-space:nowrap;">
                            {{ $diasTxt }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </table>
        </div>
        @endforeach

        <p style="font-size:12px;color:#94a3b8;margin:0;">
            Datos obtenidos en tiempo real desde <strong>Talana Producción</strong> — solo lectura.
        </p>
    </td></tr>

    @include('emails.partials.saep_footer', [
        'note' => 'Correo automatico de integracion Talana.',
        'context' => 'Datos obtenidos en modo solo lectura desde Talana Produccion.',
    ])
</table>
</td></tr></table>
</body>
</html>
