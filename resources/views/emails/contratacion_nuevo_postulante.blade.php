<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo postulante - SAEP</title>
</head>
<body style="margin:0;padding:0;background:#eef1f5;font-family:Arial,Helvetica,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;margin:0;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="680" cellpadding="0" cellspacing="0" style="width:680px;max-width:100%;background:#ffffff;border:1px solid #d8dee9;border-radius:8px;overflow:hidden;">
                <tr>
                    <td style="background:#0f1b4c;padding:22px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <div style="font-size:30px;line-height:1;font-weight:800;color:#ff6b35;letter-spacing:.01em;">saep</div>
                                    <div style="font-size:12px;color:#cbd5e1;margin-top:6px;">Aviso interno RRHH</div>
                                </td>
                                <td align="right" style="vertical-align:top;">
                                    <span style="display:inline-block;border:1px solid rgba(255,255,255,.35);color:#ffffff;font-size:11px;font-weight:700;letter-spacing:.08em;padding:6px 10px;border-radius:4px;">NUEVO POSTULANTE</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px;">
                        <h1 style="font-size:22px;line-height:1.25;color:#0f172a;margin:0 0 10px;">Nuevo postulante registrado</h1>
                        <p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 18px;">
                            Se registro una postulacion en el Portal de Contratacion. Revisa el detalle y documentacion desde el panel RRHH.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef6ff;border:1px solid #cfe6ff;border-radius:8px;margin:0 0 20px;">
                            <tr>
                                <td style="padding:18px 20px;text-align:center;">
                                    <div style="font-size:11px;line-height:1;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:8px;">Folio</div>
                                    <div style="font-size:28px;line-height:1.1;font-weight:800;color:#1d4f91;letter-spacing:.08em;">{{ $postulante->folio }}</div>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:20px;">
                            <tr>
                                <td colspan="2" style="background:#f8fafc;padding:10px 14px;font-size:12px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.05em;">Datos del postulante</td>
                            </tr>
                            <tr>
                                <td style="width:34%;padding:12px 14px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">Nombre</td>
                                <td style="padding:12px 14px;border-top:1px solid #e2e8f0;font-size:13px;color:#111827;font-weight:700;">{{ $postulante->nombre }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 14px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">RUT</td>
                                <td style="padding:12px 14px;border-top:1px solid #e2e8f0;font-size:13px;color:#111827;">{{ $postulante->rut }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 14px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">Correo</td>
                                <td style="padding:12px 14px;border-top:1px solid #e2e8f0;font-size:13px;color:#111827;">{{ $postulante->email }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 14px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">Fecha postulacion</td>
                                <td style="padding:12px 14px;border-top:1px solid #e2e8f0;font-size:13px;color:#111827;">{{ $postulante->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:24px;">
                            <tr>
                                <td colspan="2" style="background:#f8fafc;padding:10px 14px;font-size:12px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.05em;">
                                    Documentos
                                    @if($postulante->documentosCompletos())
                                        <span style="display:inline-block;background:#dcfce7;color:#166534;font-size:10px;font-weight:800;padding:3px 7px;border-radius:4px;margin-left:6px;">COMPLETOS</span>
                                    @else
                                        <span style="display:inline-block;background:#fef3c7;color:#92400e;font-size:10px;font-weight:800;padding:3px 7px;border-radius:4px;margin-left:6px;">PENDIENTES</span>
                                    @endif
                                </td>
                            </tr>
                            @php
                                $docsLabels = [
                                    'carnet_frontal' => ['label' => 'Carnet frontal', 'required' => true],
                                    'carnet_reverso' => ['label' => 'Carnet reverso', 'required' => true],
                                    'certificado_afp' => ['label' => 'Certificado AFP', 'required' => true],
                                    'certificado_fonasa' => ['label' => 'Certificado FONASA', 'required' => true],
                                    'licencia_conducir_frontal' => ['label' => 'Licencia conducir frontal', 'required' => false],
                                    'licencia_conducir_reverso' => ['label' => 'Licencia conducir reverso', 'required' => false],
                                ];
                            @endphp
                            @foreach($docsLabels as $campo => $info)
                                <tr>
                                    <td style="padding:10px 14px;border-top:1px solid #e2e8f0;font-size:12px;color:#475569;">{{ $info['label'] }}</td>
                                    <td align="right" style="padding:10px 14px;border-top:1px solid #e2e8f0;">
                                        @if($postulante->$campo)
                                            <span style="display:inline-block;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:4px 8px;border-radius:4px;">Recibido</span>
                                        @elseif($info['required'])
                                            <span style="display:inline-block;background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;padding:4px 8px;border-radius:4px;">Pendiente</span>
                                        @else
                                            <span style="display:inline-block;background:#f1f5f9;color:#64748b;font-size:11px;font-weight:700;padding:4px 8px;border-radius:4px;">No aplica</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        @php $panelUrl = rtrim(config('app.url'), '/') . '/contratacion/' . $postulante->id; @endphp
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center">
                                    <a href="{{ $panelUrl }}" style="display:inline-block;background:#0f1b4c;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:12px 24px;border-radius:6px;">
                                        Ver en panel RRHH
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 28px;text-align:center;">
                        <p style="font-size:11px;line-height:1.5;color:#94a3b8;margin:0;">
                            &copy; {{ date('Y') }} SAEP - Notificacion automatica interna RRHH
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
