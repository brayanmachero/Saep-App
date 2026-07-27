<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Respuesta Formulario</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    @include('emails.partials.saep_header', [
        'module' => 'Formularios digitales',
        'badge' => 'Respuesta recibida',
        'badgeColor' => '#10b981',
    ])
    {{-- Body --}}
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 8px;">
                <strong>{{ $respuesta->formulario->nombre }}</strong>
            </p>
            <p style="font-size:13px;color:#6b7280;margin:0 0 24px;">
                Respondido por <strong>{{ $respuesta->usuario->name ?? '—' }}</strong>
                · {{ $respuesta->created_at->format('d/m/Y H:i') }}
                @if($respuesta->usuario->departamento)
                · {{ $respuesta->usuario->departamento->nombre }}
                @endif
            </p>

            {{-- Datos respondidos --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                @foreach($schema as $field)
                    @if($field['type'] === 'divider')
                        <tr>
                            <td colspan="2" style="background:#f0f1f5;padding:10px 16px;font-size:12px;font-weight:700;color:#0f1b4c;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1px solid #e5e7eb;">
                                {{ $field['label'] ?? 'Sección' }}
                            </td>
                        </tr>
                    @else
                        @php
                            $valor = $datos[$field['id']] ?? null;
                            $display = '—';

                            if ($field['type'] === 'file' && is_array($valor)) {
                                if (isset($valor['name'])) {
                                    $display = $valor['name'];
                                } elseif (isset($valor[0]['name'])) {
                                    $display = implode(', ', array_column($valor, 'name'));
                                } else {
                                    $display = 'Archivo(s) adjunto(s)';
                                }
                            } elseif ($field['type'] === 'checkbox' && is_array($valor)) {
                                $display = implode(', ', $valor);
                            } elseif ($field['type'] === 'signature') {
                                $display = $valor ? '✓ Firmado' : '—';
                            } elseif (is_string($valor) || is_numeric($valor)) {
                                $display = $valor ?: '—';
                            }
                        @endphp
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:35%;vertical-align:top;background:#fafbfc;">
                                {{ $field['label'] ?? $field['id'] }}
                                @if(!empty($field['required']))
                                    <span style="color:#ef4444;">*</span>
                                @endif
                            </td>
                            <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">
                                {{ $display }}
                            </td>
                        </tr>
                    @endif
                @endforeach
            </table>

            @if($adjuntosOmitidos !== [])
                <table width="100%" cellpadding="0" cellspacing="0" style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;margin:0 0 20px;">
                    <tr>
                        <td style="padding:14px 16px;font-size:13px;color:#9a3412;line-height:1.55;">
                            <strong>Documentos disponibles en SAEP</strong><br>
                            {{ count($adjuntosOmitidos) }} archivo(s) no se adjuntaron para mantener este correo dentro del límite seguro de envío. Revísalos desde la respuesta completa.
                        </td>
                    </tr>
                </table>
            @endif

            @include('emails.partials.saep_button', [
                'url' => route('respuestas.show', $respuesta),
                'label' => 'Ver respuesta completa',
            ])

            <p style="font-size:13px;color:#9ca3af;line-height:1.6;margin:0;">
                Este correo fue generado automáticamente por SAEP al recibir una nueva respuesta.
            </p>
        </td>
    </tr>
    @include('emails.partials.saep_footer', [
        'note' => 'Correo generado automaticamente al recibir una nueva respuesta.',
        'siteUrl' => config('app.url'),
        'siteLabel' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'SAEP',
    ])
</table>
</td></tr>
</table>
</body>
</html>
