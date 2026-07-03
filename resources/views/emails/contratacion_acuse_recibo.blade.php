<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Recibimos tu Postulación — SAEP</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    @include('emails.partials.saep_header', [
        'module' => 'Portal de contratacion',
        'badge' => 'Postulacion recibida',
        'badgeColor' => '#10b981',
        'accentColor' => '#10b981',
    ])
    <!-- Body -->
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 20px;">
                Estimado/a <strong>{{ $postulante->nombre }}</strong>,
            </p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Hemos recibido tu postulación y la documentación asociada. Este correo confirma la recepción
                de los antecedentes; no constituye una oferta o aceptación laboral. Nuestro equipo de RRHH
                revisará tu información y te contactará si corresponde continuar el proceso.
            </p>
            @php
                $formatMailDate = function ($value): ?string {
                    if (empty($value)) {
                        return null;
                    }

                    if ($value instanceof \Carbon\CarbonInterface) {
                        return $value->format('d/m/Y H:i');
                    }

                    try {
                        return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i');
                    } catch (\Throwable $e) {
                        return is_scalar($value) ? (string) $value : null;
                    }
                };

                $postulacionFecha = $formatMailDate($postulante->created_at);
            @endphp

            <!-- Folio -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f9ff;border-radius:10px;margin-bottom:24px;">
                <tr>
                    <td style="padding:20px;text-align:center;">
                        <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin:0 0 8px;">Número de Folio</p>
                        <p style="font-size:28px;font-weight:800;color:#0369a1;margin:0;letter-spacing:0.1em;">{{ $postulante->folio }}</p>
                    </td>
                </tr>
            </table>

            <!-- Datos -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:40%;">RUT</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1e1e2e;">{{ $postulante->rut }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Correo</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $postulante->email }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $postulacionFecha ?? 'No registrada' }}</td>
                </tr>
            </table>

            <!-- Docs status -->
            <p style="font-size:13px;font-weight:700;color:#374151;margin:0 0 12px;">Estado de documentos:</p>
            @php
            $docsLabels = [
                'carnet_frontal'            => 'Carnet (Frontal)',
                'carnet_reverso'            => 'Carnet (Reverso)',
                'certificado_afp'           => 'Certificado AFP',
                'certificado_fonasa'        => 'Certificado FONASA',
                'licencia_conducir_frontal' => 'Lic. Conducir (Frontal)',
                'licencia_conducir_reverso' => 'Lic. Conducir (Reverso)',
            ];
            @endphp
            @foreach($docsLabels as $campo => $label)
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:6px;">
                <tr>
                    <td style="font-size:12px;color:#4b5563;">{{ $label }}</td>
                    <td style="text-align:right;">
                        @if($postulante->$campo)
                        <span style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-weight:700;">✓ Subido</span>
                        @elseif($campo !== 'licencia_conducir_frontal' && $campo !== 'licencia_conducir_reverso')
                        <span style="font-size:11px;background:#fefce8;color:#854d0e;padding:2px 8px;border-radius:4px;font-weight:700;">Pendiente</span>
                        @else
                        <span style="font-size:11px;background:#f3f4f6;color:#9ca3af;padding:2px 8px;border-radius:4px;">No aplica</span>
                        @endif
                    </td>
                </tr>
            </table>
            @endforeach

            @if(!$postulante->documentosCompletos())
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fefce8;border-radius:10px;margin-top:20px;margin-bottom:24px;">
                <tr>
                    <td style="padding:16px;">
                        <p style="font-size:13px;color:#854d0e;margin:0;">
                            <strong>⚠️ Documentos pendientes</strong><br>
                            Puedes ingresar nuevamente al portal con tu cuenta de Google para completar tu postulación.
                        </p>
                    </td>
                </tr>
            </table>
            @endif

            @php
                $politicaUrl = route('proteccion-datos.politica-privacidad');
                $consentimientoFecha = $formatMailDate($postulante->consentimiento_at);
            @endphp

            <!-- Privacy notice -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#eef2ff;border-radius:10px;margin-top:20px;margin-bottom:24px;">
                <tr>
                    <td style="padding:16px;">
                        <p style="font-size:13px;font-weight:700;color:#1e1e2e;margin:0 0 10px;">
                            Tratamiento de datos personales
                        </p>
                        <p style="font-size:12px;color:#4b5563;line-height:1.6;margin:0 0 10px;">
                            Los datos personales y documentos que entregaste serán tratados por SAEP y el área de RRHH
                            para gestionar tu postulación, verificar antecedentes documentales, comunicarnos contigo,
                            evaluar tu incorporación y, si corresponde, preparar la contratación.
                        </p>
                        <p style="font-size:12px;color:#4b5563;line-height:1.6;margin:0 0 10px;">
                            La información se conservará en la plataforma SAEP y en repositorios corporativos autorizados,
                            incluyendo SharePoint, con acceso restringido al personal habilitado para el proceso. No se
                            adjuntan documentos en este correo para proteger tu información.
                        </p>
                        <p style="font-size:12px;color:#4b5563;line-height:1.6;margin:0 0 10px;">
                            Puedes ejercer tus derechos de acceso, rectificación, supresión/cancelación, oposición,
                            bloqueo y portabilidad cuando resulten aplicables, conforme a la normativa chilena de
                            protección de datos personales.
                        </p>
                        @if($consentimientoFecha)
                        <p style="font-size:12px;color:#4b5563;line-height:1.6;margin:0 0 10px;">
                            Consentimiento registrado el {{ $consentimientoFecha }}.
                            @if($postulante->consentimiento_version)
                                Versión: {{ $postulante->consentimiento_version }}.
                            @endif
                        </p>
                        @endif
                        <p style="font-size:12px;color:#4b5563;line-height:1.6;margin:0;">
                            Revisa la política completa aquí:
                            <a href="{{ $politicaUrl }}" style="color:#1d4ed8;text-decoration:none;font-weight:700;">Política de privacidad</a>.
                        </p>
                    </td>
                </tr>
            </table>

            <p style="font-size:13px;color:#6b7280;line-height:1.6;margin:0;">
                Si tienes dudas, responde este correo o contacta directamente con RRHH.
            </p>
        </td>
    </tr>
    @include('emails.partials.saep_footer', [
        'note' => 'Correo automatico del portal de contratacion.',
        'context' => 'La documentacion no se adjunta por seguridad de la informacion.',
    ])
</table>
</td></tr>
</table>
</body>
</html>
