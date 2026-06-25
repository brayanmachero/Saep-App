<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Contratos Vencidos con Trabajadores Activos</title></head>
<body style="font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:#eef1f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef1f6;padding:40px 16px;">
<tr><td align="center">
<table width="700" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(15,27,76,0.06);">

    {{-- Header --}}
    <tr><td style="background:#0f1b4c;padding:28px 40px;text-align:center;">
        <img src="{{ asset('brand/wp/Logo-Saep_footer.svg') }}" alt="SAEP" width="100" style="display:inline-block;">
    </td></tr>
    <tr><td style="height:4px;background:linear-gradient(90deg,#dc2626,#ef4444,#dc2626);"></td></tr>

    {{-- Título --}}
    <tr><td style="padding:36px 40px 20px;">
        <h1 style="font-size:20px;font-weight:700;color:#7f1d1d;margin:0 0 6px;">
            🔴 Contratos vencidos — trabajadores aún activos
        </h1>
        <p style="font-size:13px;color:#64748b;margin:0 0 20px;">
            Generado el {{ $generadoEn }} — Datos en tiempo real desde Talana Producción — {{ count($porCC) }} centro(s) de costo
        </p>

        {{-- Banner aviso --}}
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px 20px;margin-bottom:24px;">
            <p style="font-size:13px;color:#991b1b;margin:0;line-height:1.6;">
                ⚠️ Los siguientes <strong>{{ $total }} trabajador(es)</strong> tienen contrato con fecha de término ya pasada
                pero <strong>no han sido finiquitados</strong> en el sistema. Se recomienda revisar su situación contractual.
            </p>
        </div>

        {{-- Tarjetas resumen --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
        <tr>
            <td width="33%" style="padding-right:6px;">
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;text-align:center;">
                    <p style="font-size:26px;font-weight:800;color:#dc2626;margin:0;">{{ $cntCritico }}</p>
                    <p style="font-size:11px;color:#64748b;margin:4px 0 0;">🔴 Críticos (&gt;30 días)</p>
                </div>
            </td>
            <td width="33%" style="padding:0 3px;">
                <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:14px 16px;text-align:center;">
                    <p style="font-size:26px;font-weight:800;color:#ea580c;margin:0;">{{ $cntReciente }}</p>
                    <p style="font-size:11px;color:#64748b;margin:4px 0 0;">⚠️ Recientes (≤30 días)</p>
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
            $ccCritico  = count(array_filter($contratos, fn($c) => $c['diasVencido'] > 30));
            $hayCritico = $ccCritico > 0;
            $hBg        = $hayCritico ? '#fef2f2' : '#fff7ed';
            $hBorder    = $hayCritico ? '#fecaca' : '#fed7aa';
            $hColor     = $hayCritico ? '#7f1d1d' : '#7c2d12';
        @endphp
        <div style="margin-bottom:22px;">
            {{-- Encabezado CC --}}
            <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="background:{{ $hBg }};border:1px solid {{ $hBorder }};border-radius:8px 8px 0 0;padding:9px 14px;">
                    <span style="font-size:13px;font-weight:700;color:{{ $hColor }};">📍 {{ $cc }}</span>
                    <span style="font-size:11px;color:#64748b;margin-left:8px;">
                        {{ count($contratos) }} contrato(s)
                        @if($ccCritico > 0)
                            — <span style="color:#dc2626;font-weight:700;">{{ $ccCritico }} crítico(s)</span>
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
                    <th style="padding:7px 12px;text-align:left;color:{{ $hColor }};font-weight:700;border-bottom:1px solid {{ $hBorder }};">Venció el</th>
                    <th style="padding:7px 12px;text-align:center;color:{{ $hColor }};font-weight:700;border-bottom:1px solid {{ $hBorder }};">Días vencido</th>
                </tr>
                @foreach($contratos as $c)
                @php
                    $emp     = $c['empleadoDetails'];
                    $nombre  = trim("{$emp['nombre']} {$emp['apellidoPaterno']} " . ($emp['apellidoMaterno'] ?? ''));
                    $hasta   = \Carbon\Carbon::parse($c['hasta'])->format('d/m/Y');
                    $tipo    = $c['tipoContratoDetails']['nombre'] ?? '—';
                    $esCrit  = $c['diasVencido'] > 30;
                    $badgeBg = $esCrit ? '#dc2626' : '#ea580c';
                    $rowBg   = $loop->even ? ($esCrit ? '#fff5f5' : '#fff8f0') : '#ffffff';
                @endphp
                <tr style="background:{{ $rowBg }};border-bottom:1px solid {{ $loop->last ? 'transparent' : $hBorder }};">
                    <td style="padding:7px 12px;color:#1e293b;font-weight:600;">{{ $nombre }}</td>
                    <td style="padding:7px 12px;color:#475569;">{{ $emp['rut'] ?? '—' }}</td>
                    <td style="padding:7px 12px;color:#475569;">{{ $c['cargo'] ?? '—' }}</td>
                    <td style="padding:7px 12px;color:#475569;">{{ $tipo }}</td>
                    <td style="padding:7px 12px;color:{{ $esCrit ? '#dc2626' : '#ea580c' }};font-weight:700;">{{ $hasta }}</td>
                    <td style="padding:7px 12px;text-align:center;">
                        <span style="background:{{ $badgeBg }};color:#fff;border-radius:20px;padding:2px 10px;font-weight:700;font-size:11px;white-space:nowrap;">
                            {{ $c['diasVencido'] }} días
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

    {{-- Footer --}}
    <tr><td style="padding:0 40px;"><div style="border-top:1px solid #f1f5f9;"></div></td></tr>
    <tr><td style="padding:20px 40px 28px;text-align:center;">
        <p style="font-size:11px;color:#94a3b8;margin:0 0 6px;">Este correo fue enviado automáticamente por SAEP Platform.</p>
        <p style="font-size:11px;color:#cbd5e1;margin:0;">&copy; {{ date('Y') }} S.A.E.P. Ltda. &mdash; <a href="https://saep.cl" style="color:#94a3b8;text-decoration:none;">saep.cl</a></p>
    </td></tr>
</table>
</td></tr></table>
</body>
</html>
