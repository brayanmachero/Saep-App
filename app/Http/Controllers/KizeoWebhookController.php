<?php

namespace App\Http\Controllers;

use App\Mail\VehiculoDevolucionMail;
use App\Mail\VehiculoEntregaMail;
use App\Models\Configuracion;
use App\Services\KizeoService;
use App\Services\OneDriveService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class KizeoWebhookController extends Controller
{
    // Diccionario de Patentes (UUID Kizeo → Etiqueta legible)
    private const PATENTES_MAP = [
        'a1667ca8-694c-47eb-b808-59e7134c93a5' => 'CGVC-41',
        'a1667ca8-6956-41d6-a3b0-8be607b45c50' => 'PSHD-40',
        'a1667ca8-695d-4cd4-81f9-108b7c67ae27' => 'PSHD-38',
        'a1667ca8-6965-4ba5-bc4a-84bc00847d7f' => 'PSHD-34',
        'a1667ca8-696b-4f0d-a3c1-66cfab3a6d11' => 'SFKF-54',
        'a1667ca8-6970-49c8-80bd-fd78533aebfe' => 'SFKF-56',
        'a1667ca8-6976-4ddf-9ec5-47565b02de12' => 'SFKF-58',
        'a1667ca8-697c-4400-8b79-3672f6de3827' => 'SFKF-69',
        'a1667ca8-6982-4e72-bd9e-f2f11966dae0' => 'SFKF-72',
        'a1667ca8-6989-4e5c-bb01-4bb546fe133b' => 'SFKF-80',
        'a1667ca8-698f-45f2-aefe-315c777dbb75' => 'SRDW-33',
        'a1667ca8-6996-4b26-a35b-767450e0e987' => 'SYGT-51',
        'a1667ca8-699d-4c05-9acf-7181f599d41c' => 'SYGT-67',
        'a1667ca8-69a3-4838-9840-ab6b1ef67244' => 'VBCK-18',
        'a1667ca8-69a9-4694-9712-1ca78ec2e727' => 'TWRD-11',
        'a1667ca8-67a0-4018-8839-4ba07b8fd0f0' => 'FYKJ-81',
    ];

    private KizeoService $kizeo;

    public function __construct(KizeoService $kizeo)
    {
        $this->kizeo = $kizeo;
    }

    /**
     * Recibir webhook de Kizeo Forms (Standard Webhook).
     */
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();

            // Log completo del payload para debugging
            Log::info('Kizeo Webhook recibido (payload completo)', $payload);

            // Extraer form_id y data_id del webhook notification
            // Kizeo Standard Webhook puede enviar: {format, eventType, data: {id, form_id, ...}}
            // O directamente: {id, form_id, eventType, ...}
            $formId = $payload['data']['form_id'] ?? $payload['form_id'] ?? null;
            $dataId = $payload['data']['id'] ?? $payload['id'] ?? $payload['data_id'] ?? null;
            $eventType = $payload['eventType'] ?? $payload['event'] ?? 'unknown';

            Log::info("Webhook parseado", ['formId' => $formId, 'dataId' => $dataId, 'event' => $eventType]);

            // Si el payload tiene los campos directamente, usarlos
            $fields = $payload['data']['fields'] ?? null;
            $recordMeta = $payload['data'] ?? [];

            // Si NO trae campos, consultar la API de Kizeo para obtener el registro completo
            if (!$fields && $formId && $dataId) {
                Log::info("Campos no encontrados en payload, consultando API de Kizeo...", ['formId' => $formId, 'dataId' => $dataId]);

                $record = $this->kizeo->getRecord($formId, $dataId);

                if (!$record || !isset($record['fields'])) {
                    Log::warning('No se pudieron obtener campos de la API de Kizeo', ['response' => $record]);
                    return response()->json(['status' => 'error', 'message' => 'No se pudieron obtener datos del formulario'], 200);
                }

                $fields = $record['fields'];
                $recordMeta = $record;
                Log::info('Campos obtenidos de la API de Kizeo', ['fields_count' => count($fields)]);
            }

            if (!$fields) {
                Log::warning('Sin campos de datos disponibles', ['payload_keys' => array_keys($payload)]);
                return response()->json(['status' => 'ignored', 'message' => 'Sin campos de datos'], 200);
            }

            // Helper para extraer campos del payload de Kizeo
            $getVal = function (string $key) use ($fields, $formId, $dataId) {
                if (!isset($fields[$key])) return '-';

                $field = $fields[$key];
                $res = $field['result'] ?? $field['value'] ?? $field;

                if ($res === null) return '-';

                // Si es un string simple, devolverlo
                if (is_string($res)) return $res;

                // Lista de selección múltiple (kit de seguridad, patentes)
                if (is_array($res) && !isset($res['value'])) {
                    if (!empty($res) && isset($res[0]['value']['code'])) {
                        return collect($res)->map(function ($r) use ($key) {
                            $code = $r['value']['code'];
                            if ($key === 'lista' && isset(self::PATENTES_MAP[$code])) {
                                return self::PATENTES_MAP[$code];
                            }
                            return $code;
                        })->implode(', ');
                    }
                    // Array de strings simples
                    if (!empty($res) && is_string($res[0] ?? null)) {
                        return implode(', ', $res);
                    }
                    return json_encode($res);
                }

                // Objeto complejo (fecha, geo, firma, dibujo, lista simple)
                if (isset($res['value'])) {
                    $val = $res['value'];
                    if (is_array($val)) {
                        if (isset($val['code'])) {
                            if ($key === 'lista' && isset(self::PATENTES_MAP[$val['code']])) {
                                return self::PATENTES_MAP[$val['code']];
                            }
                            return $val['code'];
                        }
                        if (isset($val['date'], $val['hour'])) return $val['date'] . ' ' . $val['hour'];
                        if (isset($val['lat'], $val['long'])) return $val['lat'] . ', ' . $val['long'];
                        if (isset($val['file'])) {
                            return $this->fetchMediaBase64($formId, $dataId, $val['file']);
                        }
                        return json_encode($val);
                    }
                    if (is_bool($val)) return $val ? 'Sí, conforme' : 'No';
                    return (string) $val;
                }

                return is_string($res) ? $res : json_encode($res);
            };

            // Extraer todos los campos
            $data = [
                'gestion'                   => $getVal('gestion'),
                'fecha_hora'                => $getVal('fecha_y_hora'),
                'marca_modelo'              => $getVal('marca_modelo'),
                'patente'                   => $getVal('lista'),
                'kilometraje_entrega'       => $getVal('kilometraje_de_entrega'),
                'kit_seguridad'             => $getVal('kit_de_seguridad_y_emergencia'),
                'declaracion_recepcion'     => $getVal('declaracion_de_recepcion_cust'),
                'he_leido_acepto'           => $getVal('he_leido_comprendo_y_acepto_l'),
                'firma_entrega'             => $getVal('firma'),
                'firma_encargado'           => $getVal('firma_encargado'),
                'geo_entrega'               => $getVal('geolocalizacion'),
                'dibujo'                    => $getVal('dibujo'),
                // Campos de Devolución
                'fecha_hora_devolucion'     => $getVal('fecha_y_hora1'),
                'kilometraje_devolucion'    => $getVal('kilometraje_de_devolucion1'),
                'danos_nuevos'              => $getVal('el_vehiculo_presenta_danos_nu'),
                'kit_completo'              => $getVal('se_devuelve_el_kit_de_herrami1'),
                'articulos_faltantes'       => $getVal('que_articulos_faltan_'),
                'observaciones_adicionales' => $getVal('observaciones_adicionales'),
                'firma_devolucion'          => $getVal('firma1'),
                'geo_devolucion'            => $getVal('geolocalizacion1'),
                // Datos del conductor (del formulario o metadatos del registro)
                'conductor_nombre'          => $getVal('conductor') !== '-'
                                                ? $getVal('conductor')
                                                : ($getVal('nombre_conductor') !== '-'
                                                    ? $getVal('nombre_conductor')
                                                    : (trim(($recordMeta['first_name'] ?? '') . ' ' . ($recordMeta['last_name'] ?? ''))
                                                       ?: ($recordMeta['user_name'] ?? '-'))),
                'conductor_rut'             => $getVal('rut_conductor') !== '-'
                                                ? $getVal('rut_conductor')
                                                : ($getVal('rut') !== '-' ? $getVal('rut') : '-'),
                // Metadatos del documento
                'folio'                     => strtoupper(substr(md5($dataId ?? uniqid()), 0, 8)),
                'data_id'                   => $dataId ?? '-',
                // Datos empresa (desde config/env)
                'empresa_razon_social'      => config('app.saep_razon_social', env('SAEP_RAZON_SOCIAL', 'SAEP')),
                'empresa_rut'               => env('SAEP_RUT', ''),
                'empresa_direccion'         => env('SAEP_DIRECCION', ''),
                'empresa_ciudad'            => env('SAEP_CIUDAD', 'Santiago'),
                'empresa_responsable'       => env('SAEP_RESPONSABLE_FIRMA', 'Encargado de Flota'),
            ];

            Log::info('Datos extraídos', ['gestion' => $data['gestion'], 'patente' => $data['patente']]);

            // Detectar tipo de acta
            $esDevolucion = str_contains($data['gestion'], 'Devoluci');
            $tipoActa = $esDevolucion ? 'Devolucion' : 'Entrega';
            $pdfView = $esDevolucion ? 'pdf.vehiculo_devolucion' : 'pdf.vehiculo_entrega';
            $fechaRef = $esDevolucion ? $data['fecha_hora_devolucion'] : $data['fecha_hora'];

            // Generar PDF con DomPDF
            $pdf = Pdf::loadView($pdfView, ['data' => $data, 'logoUrl' => 'https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg'])
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isRemoteEnabled'      => true,
                    'isHtml5ParserEnabled'  => true,
                    'defaultFont'           => 'DejaVu Sans',
                ]);

            $filename = "Acta_{$tipoActa}_Vehiculo_{$data['patente']}_" . preg_replace('/[^a-zA-Z0-9]/', '', $fechaRef) . '.pdf';
            $pdfContent = $pdf->output();

            // Subir PDF a OneDrive (carpeta por patente)
            try {
                $oneDrive = app(OneDriveService::class);
                if ($oneDrive->isConfigured()) {
                    $conductorSlug = preg_replace('/[^a-zA-Z0-9áéíóúñÁÉÍÓÚÑ ]/u', '', $data['conductor_nombre']);
                    $fechaSlug = date('Y-m-d');
                    $remotePath = "{$data['patente']}/{$tipoActa}_{$fechaSlug}_{$conductorSlug}.pdf";
                    $oneDrive->uploadFile($pdfContent, $remotePath);
                }
            } catch (\Throwable $e) {
                Log::warning('OneDrive upload falló (no crítico): ' . $e->getMessage());
            }

            // Enviar correo con PDF adjunto
            $envioActivo = Configuracion::get('kizeo_vehiculos_activo', '1');
            if ($envioActivo === '1' || $envioActivo === 'true') {
                $destinatariosRaw = Configuracion::get(
                    'kizeo_vehiculos_destinatarios',
                    config('services.kizeo.notify_email', 'brayan@bmachero.com')
                );
                $destinatarios = array_filter(array_map('trim', explode(',', $destinatariosRaw)));

                $mailable = $esDevolucion
                    ? new VehiculoDevolucionMail($data, $pdfContent, $filename)
                    : new VehiculoEntregaMail($data, $pdfContent, $filename);

                Mail::to($destinatarios)->send($mailable);

                Log::info("Acta de {$tipoActa} generada y enviada", [
                    'patente' => $data['patente'],
                    'destinatarios' => $destinatarios,
                ]);
            } else {
                Log::info("Acta de {$tipoActa} generada (envío email desactivado)", ['patente' => $data['patente']]);
            }

            return response()->json(['status' => 'success', 'message' => "Acta de {$tipoActa} procesada correctamente"]);

        } catch (\Throwable $e) {
            Log::error('Error en Kizeo Webhook: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Descargar media de Kizeo y devolver como data URI base64.
     */
    private function fetchMediaBase64(?string $formId, ?string $dataId, string $mediaId): string
    {
        if (!$formId || !$dataId) return '[Sin referencia de formulario]';

        try {
            $media = $this->kizeo->getMedia($formId, $dataId, $mediaId);
            if ($media && !empty($media['base64'])) {
                $type = $media['type'] ?? 'image/jpeg';
                return "data:{$type};base64,{$media['base64']}";
            }
            return '[Imagen no disponible]';
        } catch (\Exception $e) {
            Log::warning("Error descargando media Kizeo: {$e->getMessage()}");
            return '[Error descargando imagen]';
        }
    }
}
