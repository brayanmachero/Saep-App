<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporte Asistencia Talana</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #f3f5f8; color: #172033; font-family: Arial, sans-serif; font-size: 14px; }
    .container { max-width: 760px; margin: 20px auto; background: #ffffff; border: 1px solid #e3e8ef; }
    .header { background: #170051; border-bottom: 4px solid #ff6b2c; color: #ffffff; padding: 28px 32px; }
    .logo { display: block; width: 144px; height: auto; margin: 0 0 18px; }
    .eyebrow { margin: 0 0 8px; color: #cdc4ea; font-size: 11px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; }
    h1 { margin: 0; font-size: 24px; line-height: 1.2; }
    .header p { margin: 8px 0 0; color: #e8e4f5; }
    .section { padding: 24px 32px; border-top: 1px solid #e5eaf0; }
    .section h2 { margin: 0 0 10px; font-size: 18px; line-height: 1.3; }
    .section h3 { margin: 0; font-size: 15px; }
    .lead { margin: 0; color: #516176; line-height: 1.5; }
    .action-banner { margin: 24px 32px 0; padding: 18px 20px; border-left: 5px solid #d92d20; background: #fff4f2; }
    .action-banner h2 { margin: 4px 0 6px; color: #7a271a; font-size: 20px; }
    .action-banner p { margin: 0; color: #8b3f31; line-height: 1.45; }
    .status-tag { color: #b42318; font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
    .ok-banner { margin: 24px 32px 0; padding: 18px 20px; border-left: 5px solid #12b76a; background: #ecfdf3; }
    .ok-banner h2 { margin: 4px 0 6px; color: #05603a; font-size: 20px; }
    .ok-banner p { margin: 0; color: #16724f; line-height: 1.45; }
    .grid { display: table; width: 100%; border-spacing: 10px; margin: -10px; }
    .grid-row { display: table-row; }
    .metric { display: table-cell; width: 33.333%; padding: 16px; border: 1px solid #e0e6ed; vertical-align: top; }
    .metric-number { display: block; margin-bottom: 4px; color: #170051; font-size: 28px; font-weight: 700; line-height: 1; }
    .metric-title { display: block; color: #263249; font-size: 13px; font-weight: 700; }
    .metric-copy { display: block; margin-top: 6px; color: #627187; font-size: 12px; line-height: 1.4; }
    .metric-warning { border-top: 4px solid #f79009; }
    .metric-danger { border-top: 4px solid #d92d20; }
    .metric-review { border-top: 4px solid #7f56d9; }
    .metric-neutral { border-top: 4px solid #98a2b3; }
    .metric-ok { border-top: 4px solid #12b76a; }
    .coverage { padding: 14px 16px; background: #f7f8fc; border: 1px solid #dfe3f2; color: #42526b; line-height: 1.5; }
    .coverage strong { color: #170051; }
    .steps { margin: 12px 0 0; padding-left: 20px; color: #415168; line-height: 1.65; }
    .excel-note { padding: 16px; background: #f0f4ff; border: 1px solid #cbd5ff; color: #33406b; line-height: 1.5; }
    .excel-note strong { color: #170051; }
    .issue { margin-top: 22px; border: 1px solid #e1e6ed; }
    .issue-head { padding: 14px 16px; background: #f8fafc; border-bottom: 1px solid #e1e6ed; }
    .issue-head h3 { color: #1f2937; }
    .issue-head p { margin: 6px 0 0; color: #5c6b7f; font-size: 12px; line-height: 1.4; }
    .badge { display: inline-block; margin-left: 6px; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; vertical-align: 1px; }
    .badge-warning { background: #fef0c7; color: #93370d; }
    .badge-danger { background: #fee4e2; color: #b42318; }
    .badge-review { background: #f4f3ff; color: #5925dc; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th { padding: 10px 12px; background: #172b4d; color: #ffffff; text-align: left; font-size: 11px; }
    td { padding: 10px 12px; border-bottom: 1px solid #e9edf2; color: #3d4b5f; vertical-align: top; }
    tr:last-child td { border-bottom: 0; }
    .name { color: #172033; font-weight: 700; }
    .muted { color: #66758a; }
    .instruction { color: #170051; font-weight: 700; }
    .more { margin: 0; padding: 10px 12px; background: #fbfcfe; color: #526177; font-size: 12px; }
    .footer { padding: 20px 32px; background: #170051; border-top: 4px solid #ff6b2c; color: #d6d0e9; font-size: 12px; text-align: center; }
    .footer strong { color: #ffffff; }
    @media only screen and (max-width: 600px) {
        .container { margin: 0; border: 0; }
        .header, .section { padding-left: 18px; padding-right: 18px; }
        .action-banner, .ok-banner { margin-left: 18px; margin-right: 18px; }
        .grid, .grid-row, .metric { display: block; width: 100%; margin: 0; }
        .metric { margin-bottom: 10px; }
        th:nth-child(3), td:nth-child(3) { display: none; }
    }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <img class="logo" src="{{ asset('brand/wp/logo-saep-email.png') }}" alt="SAEP">
        <p class="eyebrow">Gestión de personas</p>
        <h1>Reporte de asistencia Talana</h1>
        @if($centroCosto)
            <p>Centro de costo: <strong>{{ $centroCosto }}</strong></p>
        @endif
        <p>{{ ucfirst($dia) }} · Generado {{ $generadoEn }}</p>
    </div>

    @if(($reporte['total_alertas'] ?? 0) > 0)
        <div class="action-banner">
            <div class="status-tag">Acción requerida</div>
            <h2>{{ $reporte['total_alertas'] }} registros requieren validación</h2>
            <p>Prioriza los casos de abajo. El resto de la información es referencial y no requiere corrección inmediata.</p>
        </div>
    @else
        <div class="ok-banner">
            <div class="status-tag" style="color:#05603a;">Sin alertas verificables</div>
            <h2>No hay registros pendientes de regularización</h2>
            <p>El detalle informativo está disponible en el Excel adjunto.</p>
        </div>
    @endif

    <div class="section">
        <h2>Qué se debe revisar</h2>
        <div class="grid">
            <div class="grid-row">
                <div class="metric metric-warning">
                    <span class="metric-number">{{ $reporte['total_incompletas'] }}</span>
                    <span class="metric-title">Marcas incompletas</span>
                    <span class="metric-copy">Validar y completar entrada o salida en Talana.</span>
                </div>
                <div class="metric metric-danger">
                    <span class="metric-number">{{ $reporte['total_sin_marcacion'] }}</span>
                    <span class="metric-title">Sin marca con jornada</span>
                    <span class="metric-copy">Confirmar asistencia antes de regularizar.</span>
                </div>
                <div class="metric metric-review">
                    <span class="metric-number">{{ $reporte['total_revision'] ?? 0 }}</span>
                    <span class="metric-title">Casos por validar</span>
                    <span class="metric-copy">Revisar el motivo antes de modificar información.</span>
                </div>
            </div>
        </div>
        <ol class="steps">
            <li>Confirma que la persona debía trabajar ese día.</li>
            <li>Verifica la marca y el motivo directamente en Talana.</li>
            <li>Regulariza sólo los casos cuya asistencia corresponda corregir.</li>
        </ol>
    </div>

    <div class="section">
        <h2>Información que no requiere acción inmediata</h2>
        <div class="grid">
            <div class="grid-row">
                <div class="metric metric-ok">
                    <span class="metric-number">{{ $reporte['total_completos'] }}</span>
                    <span class="metric-title">Marcas completas</span>
                    <span class="metric-copy">Registros conciliados correctamente.</span>
                </div>
                <div class="metric metric-neutral">
                    <span class="metric-number">{{ $reporte['total_sin_evaluacion'] ?? 0 }}</span>
                    <span class="metric-title">Sin jornada informada</span>
                    <span class="metric-copy">No se consideran ausencia ni alerta.</span>
                </div>
                <div class="metric metric-neutral">
                    <span class="metric-number">{{ $reporte['total_sin_historial'] ?? 0 }}</span>
                    <span class="metric-title">Contratos recientes</span>
                    <span class="metric-copy">Dato informativo; no acredita falta de enrolamiento.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="coverage">
            <strong>Cobertura entregada por Talana:</strong> {{ $reporte['total_jornadas_cubiertas'] ?? 0 }} de {{ $reporte['total_activos'] }} contratos tuvieron jornada confirmada.
            La ausencia de jornada no se interpreta como inasistencia. Las ausencias aprobadas se incorporarán cuando la sincronización esté vigente.
        </div>
    </div>

    <div class="section">
        <div class="excel-note">
            <strong>Detalle completo en el Excel adjunto.</strong> Este correo muestra hasta {{ $limiteDetalle }} casos por tipo para facilitar la lectura.
            Usa las hojas <strong>Incompletas</strong>, <strong>Sin marca</strong> y <strong>Revisión</strong> para gestionar todos los registros.
        </div>

        @if($reporte['total_incompletas'] > 0)
            <div class="issue">
                <div class="issue-head">
                    <h3>Marcas incompletas <span class="badge badge-warning">{{ $reporte['total_incompletas'] }}</span></h3>
                    <p>Completa la entrada o salida sólo después de validar la asistencia.</p>
                </div>
                <table>
                    <thead><tr><th>Persona</th><th>Centro</th><th>Marca</th><th>Acción</th></tr></thead>
                    <tbody>
                    @foreach($incompletasDestacadas as $t)
                        <tr>
                            <td><span class="name">{{ $t['nombre'] }}</span><br><span class="muted">{{ $t['rut'] }}</span></td>
                            <td>{{ $t['centro_costo'] }}</td>
                            <td>{{ $t['marcas'] ?: 'Sin detalle' }}</td>
                            <td class="instruction">Validar y completar</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if($reporte['total_incompletas'] > $limiteDetalle)<p class="more">Se muestran {{ $limiteDetalle }} de {{ $reporte['total_incompletas'] }} casos. El resto está en el Excel.</p>@endif
            </div>
        @endif

        @if($reporte['total_sin_marcacion'] > 0)
            <div class="issue">
                <div class="issue-head">
                    <h3>Sin marca con jornada confirmada <span class="badge badge-danger">{{ $reporte['total_sin_marcacion'] }}</span></h3>
                    <p>Talana indicó jornada laboral, pero no existe marca. Confirma el motivo antes de registrar una corrección.</p>
                </div>
                <table>
                    <thead><tr><th>Persona</th><th>Centro</th><th>Motivo</th><th>Acción</th></tr></thead>
                    <tbody>
                    @foreach($sinMarcacionDestacados as $t)
                        <tr>
                            <td><span class="name">{{ $t['nombre'] }}</span><br><span class="muted">{{ $t['rut'] }}</span></td>
                            <td>{{ $t['centro_costo'] }}</td>
                            <td>{{ $t['motivo'] ?? 'Jornada confirmada sin marca' }}</td>
                            <td class="instruction">Confirmar asistencia</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if($reporte['total_sin_marcacion'] > $limiteDetalle)<p class="more">Se muestran {{ $limiteDetalle }} de {{ $reporte['total_sin_marcacion'] }} casos. El resto está en el Excel.</p>@endif
            </div>
        @endif

        @if(($reporte['total_revision'] ?? 0) > 0)
            <div class="issue">
                <div class="issue-head">
                    <h3>Casos por validar <span class="badge badge-review">{{ $reporte['total_revision'] }}</span></h3>
                    <p>Puede tratarse de una marca en día de descanso o de otra inconsistencia. No se debe modificar hasta validar el motivo.</p>
                </div>
                <table>
                    <thead><tr><th>Persona</th><th>Centro</th><th>Motivo</th><th>Acción</th></tr></thead>
                    <tbody>
                    @foreach($revisionDestacados as $t)
                        <tr>
                            <td><span class="name">{{ $t['nombre'] }}</span><br><span class="muted">{{ $t['rut'] }}</span></td>
                            <td>{{ $t['centro_costo'] }}</td>
                            <td>{{ $t['motivo'] ?? 'Validar jornada y marca' }}</td>
                            <td class="instruction">Validar turno</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if($reporte['total_revision'] > $limiteDetalle)<p class="more">Se muestran {{ $limiteDetalle }} de {{ $reporte['total_revision'] }} casos. El resto está en el Excel.</p>@endif
            </div>
        @endif
    </div>

    <div class="footer">Reporte automático de <strong>SAEP</strong> · {{ $generadoEn }} · Detalle operativo en Excel adjunto</div>
</div>
</body>
</html>
