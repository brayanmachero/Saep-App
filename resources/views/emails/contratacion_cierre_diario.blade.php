<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cierre diario postulaciones RRHH - SAEP</title>
</head>
<body style="margin:0;padding:0;background:#eef1f5;font-family:Arial,Helvetica,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;margin:0;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="820" cellpadding="0" cellspacing="0" style="width:820px;max-width:100%;background:#ffffff;border:1px solid #d8dee9;border-radius:8px;overflow:hidden;">
                <tr>
                    <td style="background:#0f1b4c;padding:24px 30px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <span style="display:inline-block;background:#ffffff;border-radius:6px;padding:8px 12px;">
                                        <img src="{{ asset('brand/wp/Logo_Saep_email.png') }}" alt="SAEP" width="132" style="display:block;max-width:132px;height:auto;">
                                    </span>
                                    <div style="font-size:11px;color:#cbd5e1;margin-top:9px;text-transform:uppercase;letter-spacing:.08em;">RRHH · Cierre diario de postulaciones</div>
                                </td>
                                <td align="right" style="vertical-align:top;">
                                    <span style="display:inline-block;background:#ff6b35;color:#ffffff;font-size:11px;font-weight:800;letter-spacing:.08em;padding:7px 12px;border-radius:4px;">CIERRE RRHH</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px 30px;">
                        <h1 style="font-size:23px;line-height:1.25;color:#0f172a;margin:0 0 8px;">Cierre diario de postulaciones</h1>
                        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 22px;">
                            Resumen de postulantes registrados el <strong style="color:#111827;">{{ $fecha->format('d/m/Y') }}</strong>.
                            Los enlaces de SharePoint apuntan a la carpeta o ficha disponible para cada postulante.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
                            <tr>
                                <td style="padding:0 8px 8px 0;width:25%;vertical-align:middle;">
                                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 12px;text-align:center;">
                                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:7px;">Total dia</div>
                                        <div style="font-size:28px;line-height:1;font-weight:800;color:#0f1b4c;">{{ $resumen['total'] }}</div>
                                    </div>
                                </td>
                                <td style="padding:0 8px 8px 0;width:25%;vertical-align:middle;">
                                    <div style="background:#effdf5;border:1px solid #bbf7d0;border-radius:8px;padding:14px 12px;text-align:center;">
                                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#166534;margin-bottom:7px;">Completos</div>
                                        <div style="font-size:28px;line-height:1;font-weight:800;color:#15803d;">{{ $resumen['documentos_completos'] }}</div>
                                    </div>
                                </td>
                                <td style="padding:0 8px 8px 0;width:25%;vertical-align:middle;">
                                    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:14px 12px;text-align:center;">
                                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#9a3412;margin-bottom:7px;">Pendientes docs.</div>
                                        <div style="font-size:28px;line-height:1;font-weight:800;color:#ea580c;">{{ $resumen['documentos_pendientes'] }}</div>
                                    </div>
                                </td>
                                <td style="padding:0 0 8px 0;width:25%;vertical-align:middle;">
                                    <div style="background:#eef6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 12px;text-align:center;">
                                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#1d4ed8;margin-bottom:7px;">En revision</div>
                                        <div style="font-size:28px;line-height:1;font-weight:800;color:#1d4ed8;">{{ $resumen['en_revision'] }}</div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        @if($postulantes->isEmpty())
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;">
                                <tr>
                                    <td style="padding:22px;text-align:center;">
                                        <div style="font-size:15px;font-weight:700;color:#334155;margin-bottom:6px;">Sin postulantes registrados</div>
                                        <div style="font-size:13px;color:#64748b;">No se ingresaron postulaciones durante esta jornada.</div>
                                    </td>
                                </tr>
                            </table>
                        @else
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:20px;">
                                <tr>
                                    <td style="background:#f8fafc;padding:10px 12px;font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.05em;">Hora</td>
                                    <td style="background:#f8fafc;padding:10px 12px;font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.05em;">Postulante</td>
                                    <td style="background:#f8fafc;padding:10px 12px;font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.05em;">Estado</td>
                                    <td style="background:#f8fafc;padding:10px 12px;font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.05em;">Documentos</td>
                                    <td style="background:#f8fafc;padding:10px 12px;font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.05em;text-align:center;">Accesos</td>
                                </tr>
                                @foreach($filas as $fila)
                                    <tr>
                                        <td style="padding:12px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;vertical-align:middle;white-space:nowrap;">{{ $fila['hora'] }}</td>
                                        <td style="padding:12px;border-top:1px solid #e2e8f0;vertical-align:middle;">
                                            <div style="font-size:13px;font-weight:800;color:#111827;line-height:1.35;">{{ $fila['nombre'] }}</div>
                                            <div style="font-size:11px;color:#64748b;margin-top:3px;">{{ $fila['folio'] }} · {{ $fila['rut'] }}</div>
                                            <div style="font-size:11px;color:#64748b;margin-top:2px;">{{ $fila['email'] }}</div>
                                        </td>
                                        <td style="padding:12px;border-top:1px solid #e2e8f0;vertical-align:middle;">
                                            <span style="display:inline-block;background:{{ $fila['estado_color'] }}22;color:{{ $fila['estado_color'] }};border:1px solid {{ $fila['estado_color'] }}44;font-size:11px;font-weight:800;padding:5px 8px;border-radius:4px;">{{ $fila['estado'] }}</span>
                                        </td>
                                        <td style="padding:12px;border-top:1px solid #e2e8f0;vertical-align:middle;">
                                            @if($fila['documentos_completos'])
                                                <div style="font-size:12px;font-weight:800;color:#15803d;">Completos</div>
                                                <div style="font-size:11px;color:#64748b;margin-top:3px;">{{ $fila['documentos_recibidos'] }} documento(s) recibidos</div>
                                            @else
                                                <div style="font-size:12px;font-weight:800;color:#ea580c;">Pendientes</div>
                                                <div style="font-size:11px;color:#64748b;margin-top:3px;">Falta: {{ $fila['faltantes_labels'] }}</div>
                                            @endif
                                        </td>
                                        <td style="padding:12px;border-top:1px solid #e2e8f0;vertical-align:middle;text-align:center;white-space:nowrap;">
                                            @if($fila['sharepoint_url'])
                                                <a href="{{ $fila['sharepoint_url'] }}" style="display:inline-block;background:#0f1b4c;color:#ffffff;text-decoration:none;font-size:11px;font-weight:800;padding:8px 10px;border-radius:5px;margin-bottom:6px;">SharePoint</a>
                                            @else
                                                <span style="display:inline-block;background:#f1f5f9;color:#64748b;font-size:11px;font-weight:700;padding:8px 10px;border-radius:5px;margin-bottom:6px;">Sin enlace SP</span>
                                            @endif
                                            <br>
                                            <a href="{{ $fila['panel_url'] }}" style="display:inline-block;color:#0f1b4c;text-decoration:none;font-size:11px;font-weight:800;">Panel RRHH</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-left:4px solid #ff6b35;border-radius:6px;">
                            <tr>
                                <td style="padding:14px 16px;">
                                    <p style="font-size:12px;line-height:1.55;color:#475569;margin:0;">
                                        Este cierre es automatico. Los enlaces de SharePoint respetan los permisos del sitio:
                                        solo podran abrirlos quienes tengan acceso al repositorio correspondiente.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="background:#0f1b4c;padding:16px 30px;text-align:center;">
                        <p style="font-size:11px;line-height:1.5;color:rgba(255,255,255,.58);margin:0;">
                            &copy; {{ date('Y') }} SAEP Platform · Cierre automatico RRHH
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
