<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte Observaciones de Conducta CCU</title>
    <style>
        @media only screen and (max-width: 600px) {
            table { table-layout: fixed !important; }
            td { word-break: break-word !important; }
            td.ccu-kpi-card { display: inline-block !important; width: 50% !important; padding: 0 4px 8px 0 !important; vertical-align: top !important; }
            td.ccu-kpi-card.ccu-kpi-last { width: 100% !important; padding-right: 0 !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#eef1f5;font-family:Arial,Helvetica,sans-serif;color:#111827;">
@php
    $periodo = trim(($filters['fecha_desde'] ?? 'Inicio') . ' a ' . ($filters['fecha_hasta'] ?? 'hoy'));
    $centros = $analytics['centros'] ?? [];
    $cargos = $analytics['cargos'] ?? [];
    $antiguedades = $analytics['antiguedades'] ?? [];
    $medidas = $analytics['medidas'] ?? [];
    $topWorkers = $analytics['top_trabajadores_negativos'] ?? [];
    $topObservers = $analytics['top_observadores'] ?? [];
    $recent = $records->take(10);
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;margin:0;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:820px;background:#ffffff;border:1px solid #d8dee9;border-radius:8px;overflow:hidden;">
                <tr>
                    <td style="background:#21064f;padding:24px 30px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="vertical-align:top;">
                                    <span style="display:inline-block;background:#ffffff;border-radius:6px;padding:8px 12px;">
                                        <img src="{{ asset('brand/wp/Logo_Saep_email.png') }}" alt="SAEP" width="132" style="display:block;max-width:132px;height:auto;">
                                    </span>
                                    <div style="font-size:11px;color:#ddd6fe;margin-top:9px;text-transform:uppercase;letter-spacing:.08em;">Prevención de Riesgos · Observaciones de Conducta CCU</div>
                                </td>
                                <td align="right" style="vertical-align:top;">
                                    <span style="display:inline-block;background:#ff6b35;color:#ffffff;font-size:11px;font-weight:800;letter-spacing:.08em;padding:7px 12px;border-radius:4px;">REPORTE SOLICITADO</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 30px;">
                        <h1 style="font-size:23px;line-height:1.25;color:#0f172a;margin:0 0 8px;">Reporte de observaciones CCU</h1>
                        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 22px;">
                            Hola <strong style="color:#111827;">{{ $recipientName }}</strong>, se preparó el reporte con los filtros solicitados para el período
                            <strong style="color:#111827;">{{ $periodo }}</strong>. El detalle completo se adjunta en Excel.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
                            <tr>
                                @foreach([
                                    ['Observaciones', $analytics['total'] ?? 0, '#21064f'],
                                    ['Conductas seguras', $analytics['positivas'] ?? 0, '#15803d'],
                                    ['Hallazgos', $analytics['negativas'] ?? 0, '#dc2626'],
                                    ['Por revisar', $analytics['por_revisar'] ?? 0, '#475569'],
                                    ['Resultado positivo', number_format($analytics['porcentaje_positivo'] ?? 0, 1) . '%', '#d97706'],
                                ] as $index => [$label, $value, $color])
                                    <td class="ccu-kpi-card {{ $index === 4 ? 'ccu-kpi-last' : '' }}" style="padding:{{ $index === 4 ? '0 0 8px 0' : '0 8px 8px 0' }};width:20%;vertical-align:middle;">
                                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:12px 8px;text-align:center;">
                                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:7px;">{{ $label }}</div>
                                            <div style="font-size:23px;line-height:1;font-weight:800;color:{{ $color }};">{{ $value }}</div>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                            <tr>
                                <td style="width:50%;padding:0 8px 0 0;vertical-align:top;">
                                    <div style="border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
                                        <div style="background:#f8fafc;padding:10px 12px;font-size:12px;font-weight:800;color:#334155;">Centros con más observaciones</div>
                                        @forelse(array_slice($centros, 0, 5, true) as $label => $count)
                                            <div style="padding:8px 12px;border-top:1px solid #e2e8f0;font-size:12px;color:#334155;">{{ $label }} <strong style="float:right;color:#21064f;">{{ $count }}</strong></div>
                                        @empty
                                            <div style="padding:10px 12px;font-size:12px;color:#64748b;">Sin datos.</div>
                                        @endforelse
                                    </div>
                                </td>
                                <td style="width:50%;padding:0 0 0 8px;vertical-align:top;">
                                    <div style="border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
                                        <div style="background:#f8fafc;padding:10px 12px;font-size:12px;font-weight:800;color:#334155;">Medidas de control</div>
                                        @forelse(array_slice($medidas, 0, 5, true) as $label => $count)
                                            <div style="padding:8px 12px;border-top:1px solid #e2e8f0;font-size:12px;color:#334155;">{{ $label }} <strong style="float:right;color:#b91c1c;">{{ $count }}</strong></div>
                                        @empty
                                            <div style="padding:10px 12px;font-size:12px;color:#64748b;">Sin medidas asociadas.</div>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                            <tr>
                                <td style="width:50%;padding:0 8px 0 0;vertical-align:top;">
                                    <div style="border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
                                        <div style="background:#f8fafc;padding:10px 12px;font-size:12px;font-weight:800;color:#334155;">Cargos observados</div>
                                        @forelse(array_slice($cargos, 0, 5, true) as $label => $count)
                                            <div style="padding:8px 12px;border-top:1px solid #e2e8f0;font-size:12px;color:#334155;">{{ $label }} <strong style="float:right;color:#21064f;">{{ $count }}</strong></div>
                                        @empty
                                            <div style="padding:10px 12px;font-size:12px;color:#64748b;">Sin cargos informados.</div>
                                        @endforelse
                                    </div>
                                </td>
                                <td style="width:50%;padding:0 0 0 8px;vertical-align:top;">
                                    <div style="border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
                                        <div style="background:#f8fafc;padding:10px 12px;font-size:12px;font-weight:800;color:#334155;">Antigüedad en el cargo</div>
                                        @forelse(array_slice($antiguedades, 0, 5, true) as $label => $count)
                                            <div style="padding:8px 12px;border-top:1px solid #e2e8f0;font-size:12px;color:#334155;">{{ $label }} <strong style="float:right;color:#21064f;">{{ $count }}</strong></div>
                                        @empty
                                            <div style="padding:10px 12px;font-size:12px;color:#64748b;">Sin antigüedad informada.</div>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        </table>

                        @if(!empty($topWorkers) || !empty($topObservers))
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                                <tr>
                                    <td style="width:50%;padding:0 8px 0 0;vertical-align:top;">
                                        <div style="border-left:4px solid #dc2626;background:#fff7f7;padding:12px 14px;">
                                            <div style="font-size:12px;font-weight:800;color:#991b1b;margin-bottom:7px;">Trabajadores con más hallazgos</div>
                                            @forelse(array_slice($topWorkers, 0, 5, true) as $label => $count)
                                                <div style="font-size:12px;color:#475569;line-height:1.65;">{{ $label }} <strong style="float:right;color:#b91c1c;">{{ $count }}</strong></div>
                                            @empty
                                                <div style="font-size:12px;color:#64748b;">No hay hallazgos negativos en el período.</div>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td style="width:50%;padding:0 0 0 8px;vertical-align:top;">
                                        <div style="border-left:4px solid #15803d;background:#f0fdf4;padding:12px 14px;">
                                            <div style="font-size:12px;font-weight:800;color:#166534;margin-bottom:7px;">Observadores con más registros</div>
                                            @forelse(array_slice($topObservers, 0, 5, true) as $label => $count)
                                                <div style="font-size:12px;color:#475569;line-height:1.65;">{{ $label }} <strong style="float:right;color:#166534;">{{ $count }}</strong></div>
                                            @empty
                                                <div style="font-size:12px;color:#64748b;">Sin observadores para mostrar.</div>
                                            @endforelse
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <div style="font-size:13px;font-weight:800;color:#334155;margin:0 0 8px;">Últimos registros del período</div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;margin-bottom:20px;">
                            <tr>
                                <td style="background:#f8fafc;padding:9px 10px;font-size:10px;font-weight:800;color:#475569;text-transform:uppercase;">Fecha</td>
                                <td style="background:#f8fafc;padding:9px 10px;font-size:10px;font-weight:800;color:#475569;text-transform:uppercase;">Trabajador / Centro</td>
                                <td style="background:#f8fafc;padding:9px 10px;font-size:10px;font-weight:800;color:#475569;text-transform:uppercase;">Resultado</td>
                                <td style="background:#f8fafc;padding:9px 10px;font-size:10px;font-weight:800;color:#475569;text-transform:uppercase;">Medida</td>
                            </tr>
                            @forelse($recent as $record)
                                @php
                                    $resultColor = $record->clasificacion === 'Negativa' ? '#b91c1c' : ($record->clasificacion === 'Positiva' ? '#166534' : '#475569');
                                @endphp
                                <tr>
                                    <td style="padding:10px;border-top:1px solid #e2e8f0;font-size:11px;color:#475569;vertical-align:top;white-space:nowrap;">{{ $record->fecha_observacion?->format('d/m/Y') ?? 'Sin fecha' }}</td>
                                    <td style="padding:10px;border-top:1px solid #e2e8f0;vertical-align:top;"><div style="font-size:12px;font-weight:800;color:#111827;">{{ $record->trabajador_nombre ?: 'Sin identificar' }}</div><div style="font-size:11px;color:#64748b;margin-top:3px;">{{ $record->centro ?: 'Sin centro' }} · {{ $record->trabajador_cargo }}</div></td>
                                    <td style="padding:10px;border-top:1px solid #e2e8f0;vertical-align:top;"><span style="display:inline-block;background:{{ $resultColor }}18;color:{{ $resultColor }};border:1px solid {{ $resultColor }}44;font-size:10px;font-weight:800;padding:4px 6px;border-radius:4px;">{{ $record->clasificacion }}</span></td>
                                    <td style="padding:10px;border-top:1px solid #e2e8f0;font-size:11px;color:#475569;vertical-align:top;">{{ strtoupper(trim((string) $record->medida_control)) === 'RI' ? 'Reinducción inmediata (RI)' : ($record->medida_control ?: 'Sin medida') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="padding:16px;text-align:center;font-size:12px;color:#64748b;border-top:1px solid #e2e8f0;">No hay registros para estos filtros.</td></tr>
                            @endforelse
                        </table>

                        <div style="background:#f8fafc;border-left:4px solid #ff6b35;border-radius:6px;padding:13px 15px;margin-bottom:22px;">
                            <p style="font-size:12px;line-height:1.55;color:#475569;margin:0;">
                                <strong style="color:#334155;">Criterio de lectura:</strong> “Por revisar” identifica selecciones múltiples que combinan conductas seguras y de riesgo en Kizeo. Se mantiene separada para no alterar los indicadores positivos ni negativos.
                            </p>
                        </div>

                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;">
                            <tr><td align="center" style="background:#21064f;border-radius:5px;"><a href="{{ $dashboardUrl }}" style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-size:12px;font-weight:800;">Abrir dashboard con estos filtros</a></td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="background:#21064f;padding:16px 30px;text-align:center;"><p style="font-size:11px;line-height:1.5;color:rgba(255,255,255,.58);margin:0;">&copy; {{ date('Y') }} SAEP Platform · Reporte solicitado desde Observaciones de Conducta CCU</p></td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
