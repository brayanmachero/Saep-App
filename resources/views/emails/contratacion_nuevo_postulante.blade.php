<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Nuevo Postulante — SAEP</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    @include('emails.partials.saep_header', [
        'module' => 'Portal de contratacion',
        'badge' => 'Nuevo postulante',
        'badgeColor' => '#0ea5e9',
        'accentColor' => '#0ea5e9',
    ])
    <!-- Body -->
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 20px;">
                Se ha registrado un nuevo postulante en el Portal de Contratación. La documentación debe revisarse
                desde el panel interno; no se adjuntan documentos en este correo.
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
                        <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin:0 0 8px;">Folio</p>
                        <p style="font-size:28px;font-weight:800;color:#0369a1;margin:0;letter-spacing:0.1em;">{{ $postulante->folio }}</p>
                    </td>
                </tr>
            </table>

            <!-- Datos personales -->
            <p style="font-size:13px;font-weight:700;color:#374151;margin:0 0 8px;">Datos personales</p>
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:40%;">Nombre</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1e1e2e;">{{ $postulante->nombre }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">RUT</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;font-family:monospace;">{{ $postulante->rut }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Correo</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $postulante->email }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha postulación</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $postulacionFecha ?? 'No registrada' }}</td>
                </tr>
            </table>

            <!-- Documentos -->
            <p style="font-size:13px;font-weight:700;color:#374151;margin:0 0 12px;">
                Documentos subidos
                @if($postulante->documentosCompletos())
                <span style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-weight:700;margin-left:8px;">Completos</span>
                @else
                <span style="font-size:11px;background:#fefce8;color:#854d0e;padding:2px 8px;border-radius:4px;font-weight:700;margin-left:8px;">Incompletos</span>
                @endif
            </p>

            @php
            $docsLabels = [
                'carnet_frontal'            => ['label' => 'Carnet (Frontal)',          'required' => true],
                'carnet_reverso'            => ['label' => 'Carnet (Reverso)',          'required' => true],
                'certificado_afp'           => ['label' => 'AFP',                       'required' => true],
                'certificado_fonasa'        => ['label' => 'FONASA',                    'required' => true],
                'licencia_conducir_frontal' => ['label' => 'Lic. Conducir (Frontal)',   'required' => false],
                'licencia_conducir_reverso' => ['label' => 'Lic. Conducir (Reverso)',   'required' => false],
            ];
            @endphp
            @foreach($docsLabels as $campo => $info)
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:6px;">
                <tr>
                    <td style="font-size:12px;color:#4b5563;">{{ $info['label'] }}</td>
                    <td style="text-align:right;">
                        @if($postulante->$campo)
                        <span style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-weight:700;">✓ Subido</span>
                        @elseif($info['required'])
                        <span style="font-size:11px;background:#fefce8;color:#854d0e;padding:2px 8px;border-radius:4px;">Pendiente</span>
                        @else
                        <span style="font-size:11px;background:#f3f4f6;color:#9ca3af;padding:2px 8px;border-radius:4px;">—</span>
                        @endif
                    </td>
                </tr>
            </table>
            @endforeach

            @php
                $politicaUrl = route('proteccion-datos.politica-privacidad');
                $consentimientoFecha = $formatMailDate($postulante->consentimiento_at);
            @endphp

            <!-- Privacy and confidentiality -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fff7ed;border-radius:10px;margin-top:22px;margin-bottom:24px;">
                <tr>
                    <td style="padding:16px;">
                        <p style="font-size:13px;font-weight:700;color:#7c2d12;margin:0 0 10px;">
                            Tratamiento confidencial obligatorio
                        </p>
                        <p style="font-size:12px;color:#7c2d12;line-height:1.6;margin:0 0 10px;">
                            Esta postulación contiene datos personales y documentos de identidad, previsión, salud u otros
                            antecedentes laborales. Su revisión debe limitarse a fines de reclutamiento, selección,
                            verificación documental y eventual contratación.
                        </p>
                        <p style="font-size:12px;color:#7c2d12;line-height:1.6;margin:0 0 10px;">
                            No reenvíes este correo, no descargues ni compartas documentos fuera de SAEP, SharePoint o los
                            canales corporativos autorizados. El acceso corresponde solo a personal habilitado de RRHH o
                            responsables del proceso.
                        </p>
                        <p style="font-size:12px;color:#7c2d12;line-height:1.6;margin:0 0 10px;">
                            El tratamiento debe respetar la Ley N° 19.628 y su reforma por Ley N° 21.719, incluyendo
                            licitud, finalidad, proporcionalidad, seguridad, transparencia y confidencialidad.
                        </p>
                        <p style="font-size:12px;color:#7c2d12;line-height:1.6;margin:0;">
                            @if($postulante->consentimiento_datos)
                                Consentimiento registrado
                                @if($consentimientoFecha)
                                    el {{ $consentimientoFecha }}
                                @endif
                                @if($postulante->consentimiento_version)
                                    · versión {{ $postulante->consentimiento_version }}
                                @endif
                                .
                            @else
                                Revisar consentimiento antes de continuar el tratamiento.
                            @endif
                            Política vigente:
                            <a href="{{ $politicaUrl }}" style="color:#9a3412;text-decoration:none;font-weight:700;">ver política</a>.
                        </p>
                    </td>
                </tr>
            </table>

            <!-- CTA -->
            @php $panelUrl = config('app.url') . '/contratacion/' . $postulante->id; @endphp
            @include('emails.partials.saep_button', [
                'url' => $panelUrl,
                'label' => 'Ver en panel RRHH',
                'color' => '#0ea5e9',
            ])
        </td>
    </tr>
    @include('emails.partials.saep_footer', [
        'note' => 'Notificacion automatica del portal de contratacion.',
        'context' => 'Acceso restringido a personal autorizado de RRHH.',
    ])
</table>
</td></tr>
</table>
</body>
</html>
