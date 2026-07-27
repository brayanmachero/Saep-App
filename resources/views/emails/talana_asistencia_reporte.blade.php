<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporte Asistencia Talana</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background:#f4f6f9; color:#1a202c; font-size:14px; }
    .container { max-width:860px; margin:20px auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.10); }

    /* Header */
    .header { background:#0f1b4c; padding:28px 36px; color:#fff; border-bottom:4px solid #f97316; }
    .header img { display:block; width:160px; max-width:160px; height:auto; margin-bottom:12px; }
    .header h1 { font-size:22px; font-weight:700; }
    .header p  { margin-top:6px; opacity:.85; font-size:13px; }
    .header .eyebrow { font-size:11px; font-weight:700; text-transform:uppercase; opacity:.72; margin:0 0 10px; }

    /* Resumen cards */
    .summary { display:flex; gap:12px; padding:24px 36px 8px; flex-wrap:wrap; }
    .card { flex:1; min-width:140px; border-radius:10px; padding:16px 18px; text-align:center; }
    .card-total    { background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe; }
    .card-ok       { background:#ecfdf5; color:#065f46; border:1px solid #6ee7b7; }
    .card-warn     { background:#fffbeb; color:#92400e; border:1px solid #fcd34d; }
    .card-error    { background:#fef2f2; color:#991b1b; border:1px solid #fca5a5; }
    .card-new      { background:#f0fdf4; color:#166534; border:1px solid #86efac; }
    .card .num     { font-size:32px; font-weight:800; line-height:1; }
    .card .label   { font-size:11px; margin-top:4px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; opacity:.8; }

    /* Sections */
    .section { padding:20px 36px; border-top:1px solid #e8ecf0; }
    .section-title { font-size:16px; font-weight:700; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
    .badge-warn  { background:#fef3c7; color:#92400e; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600; }
    .badge-error { background:#fee2e2; color:#991b1b; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600; }
    .badge-new   { background:#dcfce7; color:#166534; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600; }
    .badge-ok    { background:#d1fae5; color:#065f46; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600; }

    /* CC Group */
    .cc-group { margin-bottom:16px; }
    .cc-title { font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.6px; margin-bottom:6px; padding:4px 8px; background:#f8fafc; border-left:3px solid #64748b; border-radius:0 4px 4px 0; }

    /* Table */
    table { width:100%; border-collapse:collapse; font-size:13px; }
    th { background:#1e3a5f; color:#fff; text-align:left; padding:8px 10px; font-size:12px; font-weight:600; }
    td { padding:7px 10px; border-bottom:1px solid #edf2f7; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:#f8fafc; }
    .cat-solo_entrada  { color:#b45309; font-weight:600; }
    .cat-solo_salida   { color:#7c3aed; font-weight:600; }
    .cat-incompleto    { color:#dc2626; font-weight:600; }
    .cat-multiple      { color:#0891b2; font-weight:600; }
    .marca-hora { font-family:monospace; font-size:12px; }

    /* Footer */
    .footer { padding:20px 36px; background:#0f1b4c; font-size:12px; color:rgba(255,255,255,.72); text-align:center; border-top:4px solid #f97316; }
    .footer strong { color:#fff; }
    .notice { background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:12px 16px; font-size:12px; color:#78350f; margin:0 36px 20px; }

    @media (max-width:600px) {
        .summary { flex-direction:column; }
        .section { padding:16px 16px; }
        .header  { padding:20px 16px; }
    }
</style>
</head>
<body>
<div class="container">

    {{-- HEADER --}}
    <div class="header">
        <img src="{{ asset('brand/wp/logo-saep-email.png') }}" alt="SAEP">
        <div class="eyebrow">Sistema de gestion operacional y prevencion</div>
        <h1>Reporte Asistencia Talana</h1>
        <p>{{ ucfirst($dia) }} · Generado {{ $generadoEn }}</p>
    </div>

    {{-- RESUMEN CARDS --}}
    <div class="summary">
        <div class="card card-total">
            <div class="num">{{ $reporte['total_activos'] }}</div>
            <div class="label">Activos</div>
        </div>
        <div class="card card-ok">
            <div class="num">{{ $reporte['total_completos'] }}</div>
            <div class="label">Completos ✅</div>
        </div>
        <div class="card card-warn">
            <div class="num">{{ $reporte['total_incompletas'] }}</div>
            <div class="label">Incompletos ⚠️</div>
        </div>
        <div class="card card-error">
            <div class="num">{{ $reporte['total_sin_marcacion'] }}</div>
            <div class="label">Sin marca confirmada ❌</div>
        </div>
        <div class="card card-new">
            <div class="num">{{ $reporte['total_sin_historial'] ?? 0 }}</div>
            <div class="label">Sin historial 🆕</div>
        </div>
        <div class="card" style="background:#f8fafc;color:#475569;border:1px solid #cbd5e1;">
            <div class="num">{{ $reporte['total_sin_evaluacion'] ?? 0 }}</div>
            <div class="label">Sin evaluación ◌</div>
        </div>
        @if(($reporte['total_revision'] ?? 0) > 0)
        <div class="card" style="background:#fdf4ff;color:#6b21a8;border:1px solid #d8b4fe;">
            <div class="num">{{ $reporte['total_revision'] }}</div>
            <div class="label">Revisión 🔍</div>
        </div>
        @endif
    </div>

    {{-- AVISO sin turno --}}
    <div class="notice">
        <strong>Alcance de la evaluación:</strong> Talana entregó jornada confirmada para
        <strong>{{ $reporte['total_jornadas_cubiertas'] ?? 0 }}</strong> de {{ $reporte['total_activos'] }} contratos activos.
        Sólo se alerta <em>sin marca</em> cuando la jornada laboral está confirmada. Los demás casos quedan como
        <em>sin evaluación</em> en el Excel, no como ausencia.
    </div>

    {{-- SECCIÓN: MARCACIÓN INCOMPLETA --}}
    @if($reporte['total_incompletas'] > 0)
    <div class="section">
        <div class="section-title">
            ⚠️ Marcación incompleta
            <span class="badge-warn">{{ $reporte['total_incompletas'] }} trabajador(es)</span>
        </div>
        <p style="font-size:12px;color:#78350f;margin-bottom:12px;">
            Solo registraron una marca (entrada SIN salida, o salida SIN entrada). Requiere corrección en Talana.
        </p>

        @foreach($incompletasPorEmpresaCC as $empresa => $ccGroups)
        <div style="margin-bottom:20px;">
            <div style="font-size:13px;font-weight:700;background:#1e3a5f;color:#fff;padding:6px 14px;border-radius:6px;margin-bottom:8px;">
                🏢 Empresa {{ $empresa }}
            </div>
            @foreach($ccGroups as $cc => $trabajadores)
            <div class="cc-group">
                <div class="cc-title">{{ $cc }}</div>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>RUT</th>
                            <th>Cargo</th>
                            <th>Marca(s) del día</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trabajadores as $t)
                        <tr>
                            <td>{{ $t['nombre'] }}</td>
                            <td>{{ $t['rut'] }}</td>
                            <td style="font-size:12px;color:#64748b;">{{ $t['cargo'] }}</td>
                            <td class="marca-hora">{{ $t['marcas'] ?: '—' }}</td>
                            <td>
                                @if($t['categoria'] === 'solo_entrada')
                                    <span class="cat-solo_entrada">Solo entrada</span>
                                @elseif($t['categoria'] === 'solo_salida')
                                    <span class="cat-solo_salida">Solo salida</span>
                                @elseif($t['categoria'] === 'multiple')
                                    <span class="cat-multiple">Múltiples ({{ $t['total_marcas'] }})</span>
                                @else
                                    <span class="cat-incompleto">Incompleto</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endforeach
        </div>
        @endforeach
    </div>
    @endif

    {{-- SECCIÓN: CONTRATO RECIENTE SIN HISTORIAL --}}
    @if(($reporte['total_sin_historial'] ?? 0) > 0)
    <div class="section">
        <div class="section-title">
            🆕 Contratos recientes sin historial de marca
            <span class="badge-new">{{ $reporte['total_sin_historial'] }} trabajador(es)</span>
        </div>
        <p style="font-size:12px;color:#166534;margin-bottom:12px;">
            Tienen contrato vigente reciente y <strong>no registran marcas en los últimos 7 días</strong>.
            Este dato no confirma falta de enrolamiento; sirve para revisar habilitación, turno o incorporación.
        </p>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>RUT</th>
                    <th>Centro Costo</th>
                    <th>Cargo</th>
                    <th>Inicio Contrato</th>
                    <th>Días en empresa</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reporte['sin_historial'] as $t)
                @php
                    $dias = $t['desde'] ? \Carbon\Carbon::parse($t['desde'])->diffInDays(\Carbon\Carbon::parse($fecha)) : null;
                @endphp
                <tr>
                    <td>{{ $t['nombre'] }}</td>
                    <td>{{ $t['rut'] }}</td>
                    <td>{{ $t['centro_costo'] }}</td>
                    <td style="font-size:12px;color:#64748b;">{{ $t['cargo'] }}</td>
                    <td class="marca-hora">{{ $t['desde'] ? \Carbon\Carbon::parse($t['desde'])->format('d/m/Y') : '—' }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $dias !== null ? $dias : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- SECCIÓN: SIN MARCACIÓN --}}
    @if($reporte['total_sin_marcacion'] > 0)
    <div class="section">
        <div class="section-title">
            ❌ Sin marca con jornada laboral confirmada
            <span class="badge-error">{{ $reporte['total_sin_marcacion'] }} trabajador(es)</span>
        </div>
        <p style="font-size:12px;color:#991b1b;margin-bottom:12px;">
            Estos trabajadores tienen jornada laboral confirmada por Talana y <strong>no registraron ninguna marca</strong>
            el día {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}. Requiere revisión en Talana.
        </p>

        @foreach($sinMarcacionPorEmpresaCC as $empresa => $ccGroups)
        <div style="margin-bottom:20px;">
            <div style="font-size:13px;font-weight:700;background:#1e3a5f;color:#fff;padding:6px 14px;border-radius:6px;margin-bottom:8px;">
                🏢 Empresa {{ $empresa }}
            </div>
            @foreach($ccGroups as $cc => $trabajadores)
            <div class="cc-group">
                <div class="cc-title">{{ $cc }} ({{ count($trabajadores) }})</div>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>RUT</th>
                            <th>Cargo</th>
                            <th>Vence</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trabajadores as $t)
                        <tr>
                            <td>{{ $t['nombre'] }}</td>
                            <td>{{ $t['rut'] }}</td>
                            <td style="font-size:12px;color:#64748b;">{{ $t['cargo'] }}</td>
                            <td class="marca-hora" style="color:{{ $t['hasta'] ? '#dc2626' : '#16a34a' }};">
                                {{ $t['hasta'] ? \Carbon\Carbon::parse($t['hasta'])->format('d/m/Y') : 'Indefinido' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endforeach
        </div>
        @endforeach
    </div>
    @endif

    {{-- SECCIÓN: SIN EVALUACIÓN --}}
    @if(($reporte['total_sin_evaluacion'] ?? 0) > 0)
    <div class="section">
        <div class="section-title">
            ◌ Sin evaluación de jornada
            <span style="background:#e2e8f0;color:#475569;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;">{{ $reporte['total_sin_evaluacion'] }} trabajador(es)</span>
        </div>
        <p style="font-size:12px;color:#475569;">
            No tienen marca, jornada ni ausencia confirmada en los datos disponibles de Talana. No se consideran alerta;
            el detalle completo queda en la hoja <strong>Sin evaluar</strong> del archivo Excel.
        </p>
    </div>
    @endif

    {{-- SECCIÓN: REVISIÓN (descanso + horas anómalas) --}}
    @if(($reporte['total_revision'] ?? 0) > 0)
    <div class="section">
        <div class="section-title">
            🔍 Requieren revisión
            <span style="background:#f5f3ff;color:#6b21a8;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;">{{ $reporte['total_revision'] }} trabajador(es)</span>
        </div>
        <p style="font-size:12px;color:#6b21a8;margin-bottom:12px;">
            Anomalías detectadas: marcas en día de descanso, horas excesivas o horas insuficientes. Requieren revisión manual.
        </p>

        @foreach($revisionPorEmpresaCC as $empresa => $ccGroups)
        <div style="margin-bottom:20px;">
            <div style="font-size:13px;font-weight:700;background:#6b21a8;color:#fff;padding:6px 14px;border-radius:6px;margin-bottom:8px;">
                🏢 Empresa {{ $empresa }}
            </div>
            @foreach($ccGroups as $cc => $trabajadores)
            <div class="cc-group">
                <div class="cc-title">{{ $cc }}</div>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>RUT</th>
                            <th>Cargo</th>
                            <th>Motivo</th>
                            <th>Marca(s) del día</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trabajadores as $t)
                        <tr>
                            <td>{{ $t['nombre'] }}</td>
                            <td>{{ $t['rut'] }}</td>
                            <td style="font-size:12px;color:#64748b;">{{ $t['cargo'] }}</td>
                            <td style="font-size:12px;color:#6b21a8;font-weight:600;">{{ $t['motivo'] ?? '—' }}</td>
                            <td class="marca-hora">{{ $t['marcas'] ?: '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endforeach
        </div>
        @endforeach
    </div>
    @endif

    {{-- Sin anomalías --}}
    @if(($reporte['total_alertas'] ?? 0) === 0)
    <div class="section" style="text-align:center;padding:40px 36px;">
        <div style="font-size:48px;">✅</div>
        <div style="font-size:18px;font-weight:700;color:#065f46;margin-top:12px;">Sin alertas verificables</div>
        <div style="color:#64748b;margin-top:6px;">No se detectaron marcas incompletas, jornadas confirmadas sin marca ni anomalías de jornada.</div>
    </div>
    @endif

    {{-- FOOTER --}}
    <div class="footer">
        Reporte automatico generado por <strong>SAEP</strong> · {{ $generadoEn }} · Adjunto: Excel con detalle completo
    </div>

</div>
</body>
</html>
