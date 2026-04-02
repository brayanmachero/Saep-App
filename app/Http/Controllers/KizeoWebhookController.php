<?php

namespace App\Http\Controllers;

use App\Mail\VehiculoDevolucionMail;
use App\Mail\VehiculoEntregaMail;
use App\Models\Configuracion;
use App\Models\WebhookLog;
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
     * Despacha según el formId al handler correspondiente.
     */
    public function handle(Request $request)
    {
        // Verificar secreto del webhook si está configurado
        $secret = config('services.kizeo.webhook_secret');
        if ($secret && !hash_equals($secret, (string) $request->header('X-Webhook-Secret', ''))) {
            Log::warning('Kizeo Webhook rechazado: secreto inválido', ['ip' => $request->ip()]);
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        try {
            $payload = $request->all();

            // Log completo del payload para debugging
            Log::info('Kizeo Webhook recibido (payload completo)', $payload);

            // Extraer form_id y data_id del webhook notification
            $formId = $payload['data']['form_id'] ?? $payload['form_id'] ?? null;
            $dataId = $payload['data']['id'] ?? $payload['id'] ?? $payload['data_id'] ?? null;
            $eventType = $payload['eventType'] ?? $payload['event'] ?? 'unknown';

            Log::info("Webhook parseado", ['formId' => $formId, 'dataId' => $dataId, 'event' => $eventType]);

            if (!$formId || !$dataId) {
                Log::warning('Webhook sin formId o dataId', ['payload_keys' => array_keys($payload)]);
                WebhookLog::logIgnored(['origen' => 'kizeo', 'form_id' => $formId, 'data_id' => $dataId, 'tipo' => 'sin_identificar', 'resumen' => 'Payload sin form_id o data_id', 'ip' => $request->ip()]);
                return response()->json(['status' => 'ignored', 'message' => 'Sin form_id o data_id'], 200);
            }

            // Despachar según el formulario
            $vehicleFormId     = config('services.kizeo.vehicle_form_id');
            $charlaFormId      = config('services.kizeo.charla_form_id');
            $observacionFormId = config('services.kizeo.observacion_form_id');
            $inspeccionFormId  = config('services.kizeo.inspeccion_form_id');
            $visitaFormId      = config('services.kizeo.visita_form_id');
            $accidenteFormId   = config('services.kizeo.accidente_form_id');

            if ($vehicleFormId && $formId == $vehicleFormId) {
                return $this->handleVehiculo($formId, $dataId, $payload, $request->ip());
            }

            if ($charlaFormId && $formId == $charlaFormId) {
                return $this->handleCharlaSst($formId, $dataId, $payload, $request->ip());
            }

            if ($observacionFormId && $formId == $observacionFormId) {
                return $this->handleObservacionConducta($formId, $dataId, $payload, $request->ip());
            }

            if ($inspeccionFormId && $formId == $inspeccionFormId) {
                return $this->handleInspeccionSst($formId, $dataId, $payload, $request->ip());
            }

            if ($visitaFormId && $formId == $visitaFormId) {
                return $this->handleVisitaTerreno($formId, $dataId, $payload, $request->ip());
            }

            if ($accidenteFormId && $formId == $accidenteFormId) {
                return $this->handleAccidenteSst($formId, $dataId, $payload, $request->ip());
            }

            Log::info("Webhook recibido para formulario no registrado", ['formId' => $formId]);
            WebhookLog::logIgnored(['origen' => 'kizeo', 'form_id' => $formId, 'data_id' => $dataId, 'tipo' => 'no_registrado', 'resumen' => "FormId {$formId} sin handler configurado", 'ip' => $request->ip()]);
            return response()->json(['status' => 'ignored', 'message' => "FormId {$formId} no tiene handler configurado"], 200);

        } catch (\Throwable $e) {
            Log::error('Error en Kizeo Webhook: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'Internal processing error'], 500);
        }
    }

    /**
     * Handler para formularios de Vehículos (Entrega/Devolución).
     */
    private function handleVehiculo(string $formId, string $dataId, array $payload, ?string $ip = null)
    {
        try {
            // Obtener campos del registro
            $fields = $payload['data']['fields'] ?? null;
            $recordMeta = $payload['data'] ?? [];

            if (!$fields) {
                Log::info("Campos no encontrados en payload, consultando API de Kizeo...", ['formId' => $formId, 'dataId' => $dataId]);
                $record = $this->kizeo->getRecord($formId, $dataId);
                if (!$record || !isset($record['fields'])) {
                    Log::warning('No se pudieron obtener campos de la API de Kizeo', ['response' => $record]);
                    return response()->json(['status' => 'error', 'message' => 'No se pudieron obtener datos del formulario'], 200);
                }
                $fields = $record['fields'];
                $recordMeta = $record;
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
                'conductor_nombre'          => '-',
                'conductor_rut'             => '-',
                // Metadatos del documento
                'folio'                     => strtoupper(substr(md5($dataId ?? uniqid()), 0, 8)),
                'data_id'                   => $dataId ?? '-',
                // Datos empresa (desde config/env)
                'empresa_razon_social'      => env('SAEP_RAZON_SOCIAL', 'SAEP'),
                'empresa_rut'               => env('SAEP_RUT', ''),
                'empresa_direccion'         => env('SAEP_DIRECCION', ''),
                'empresa_ciudad'            => env('SAEP_CIUDAD', 'Santiago'),
                'empresa_responsable'       => env('SAEP_RESPONSABLE_FIRMA', 'Encargado de Flota'),
            ];

            // ── Resolver conductor_nombre y conductor_rut con detección inteligente ──
            // Patrón RUT: 1-99 millones + guión + dígito verificador (0-9 o K)
            $esRut = fn($v) => $v && $v !== '-' && preg_match('/^\d{1,2}\.?\d{3}\.?\d{3}-[\dkK]$/i', trim($v));

            // Recopilar candidatos de nombre y RUT desde múltiples campos
            $candidatos = [
                $getVal('conductor'),
                $getVal('nombre_conductor'),
                trim(($recordMeta['first_name'] ?? '') . ' ' . ($recordMeta['last_name'] ?? '')),
                $recordMeta['user_name'] ?? null,
            ];
            $candidatosRut = [
                $getVal('rut_conductor'),
                $getVal('rut'),
            ];

            foreach ($candidatos as $c) {
                if (!$c || $c === '-' || trim($c) === '') continue;
                if ($esRut($c)) {
                    // Si parece RUT, usarlo como RUT si aún no tenemos uno
                    if ($data['conductor_rut'] === '-') {
                        $data['conductor_rut'] = trim($c);
                    }
                } else {
                    // Es un nombre real
                    if ($data['conductor_nombre'] === '-') {
                        $data['conductor_nombre'] = trim($c);
                    }
                }
            }
            foreach ($candidatosRut as $r) {
                if ($r && $r !== '-' && $data['conductor_rut'] === '-') {
                    $data['conductor_rut'] = trim($r);
                }
            }

            // Limpiar campos que Kizeo devuelve como JSON oculto ({"result":null,"hidden":true})
            $cleanKizeo = function ($val) {
                if (!is_string($val)) return $val;
                $trimmed = trim($val);
                if ($trimmed === '' || $trimmed === '-') return '-';
                // Detectar JSON con hidden:true o result:null
                if (preg_match('/^\s*\{.*"hidden"\s*:\s*true/i', $trimmed) ||
                    preg_match('/^\s*\{.*"result"\s*:\s*null/i', $trimmed)) {
                    return '-';
                }
                return $val;
            };
            foreach (['articulos_faltantes', 'geo_entrega', 'geo_devolucion', 'observaciones_adicionales', 'dibujo'] as $campo) {
                $data[$campo] = $cleanKizeo($data[$campo]);
            }

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
            $sharepointPath = null;
            try {
                $oneDrive = app(OneDriveService::class);
                if ($oneDrive->isConfigured()) {
                    $conductorSlug = preg_replace('/[^a-zA-Z0-9áéíóúñÁÉÍÓÚÑ ]/u', '', $data['conductor_nombre']);
                    $fechaSlug = date('Y-m-d');
                    $remotePath = "{$data['patente']}/{$tipoActa}_{$fechaSlug}_{$conductorSlug}.pdf";
                    $oneDrive->uploadFile($pdfContent, $remotePath);
                    $sharepointPath = $remotePath;
                }
            } catch (\Throwable $e) {
                Log::warning('OneDrive upload falló (no crítico): ' . $e->getMessage());
            }

            // Enviar correo con PDF adjunto
            $emailEnviado = false;
            $destinatarios = [];
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
                $emailEnviado = true;

                Log::info("Acta de {$tipoActa} generada y enviada", [
                    'patente' => $data['patente'],
                    'destinatarios' => $destinatarios,
                ]);
            } else {
                Log::info("Acta de {$tipoActa} generada (envío email desactivado)", ['patente' => $data['patente']]);
            }

            // Registrar en webhook_logs
            WebhookLog::logSuccess([
                'origen'          => 'kizeo',
                'form_id'         => $formId,
                'data_id'         => $dataId,
                'tipo'            => 'vehiculo_' . strtolower($tipoActa),
                'resumen'         => "Acta de {$tipoActa} - {$data['patente']} ({$data['conductor_nombre']})",
                'archivo'         => $filename,
                'sharepoint_path' => $sharepointPath,
                'email_enviado'   => $emailEnviado,
                'destinatarios'   => $destinatarios,
                'metadata'        => [
                    'patente'   => $data['patente'],
                    'conductor' => $data['conductor_nombre'],
                    'fecha'     => $fechaRef,
                ],
                'ip' => $ip,
            ]);

            return response()->json(['status' => 'success', 'message' => "Acta de {$tipoActa} procesada correctamente"]);

        } catch (\Throwable $e) {
            Log::error('Error en Kizeo Webhook (Vehículo): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            WebhookLog::logError([
                'origen'        => 'kizeo',
                'form_id'       => $formId,
                'data_id'       => $dataId,
                'tipo'          => 'vehiculo',
                'resumen'       => 'Error al procesar acta de vehículo',
                'error_message' => $e->getMessage(),
                'ip'            => $ip,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Internal processing error'], 500);
        }
    }

    /**
     * Handler para formularios de Charla de Seguridad SST (Form 973784).
     * Descarga el PDF generado por Kizeo y lo sube a SharePoint.
     *
     * Campos del formulario:
     * supervisor, fecha_de_actividad_, hora_inicial_, hora_termino_,
     * duracion_actividad, lugar_de_la_capacitacion, nombre_relator_,
     * firma_relator_, nombre_relator_cphs_, firma_relator_cphs_, notas,
     * antecedentes, actividad_de_, otro_especificar_, titulo_actividad,
     * descripcion_, foto1, asistentes/asistentes2 (tabla: rut, nombre_y_apellidos1, cargo, firma1)
     */
    private function handleCharlaSst(string $formId, string $dataId, array $payload, ?string $ip = null)
    {
        try {
            // Obtener registro completo desde la API de Kizeo
            $record = $this->kizeo->getRecord($formId, $dataId);
            $fields = $record['fields'] ?? [];
            $recordMeta = $record ?? [];

            // Helper para extraer valor simple de un campo
            $getVal = function (string $key) use ($fields) {
                if (!isset($fields[$key])) return '-';
                $field = $fields[$key];
                $res = $field['result'] ?? $field['value'] ?? $field;
                if ($res === null) return '-';
                if (is_string($res)) return $res;
                if (isset($res['value'])) {
                    $val = $res['value'];
                    if (is_array($val) && isset($val['date'], $val['hour'])) return $val['date'] . ' ' . $val['hour'];
                    if (is_array($val) && isset($val['date'])) return $val['date'];
                    if (is_string($val)) return $val;
                    if (is_bool($val)) return $val ? 'Sí' : 'No';
                }
                return is_string($res) ? $res : '-';
            };

            // Extraer datos del formulario de Charla SST
            $fecha      = $getVal('fecha_de_actividad_');
            $titulo     = $getVal('titulo_actividad');
            $actividad  = $getVal('actividad_de_');
            $relator    = $getVal('nombre_relator_');
            $supervisor = $getVal('supervisor');
            $lugar      = $getVal('lugar_de_la_capacitacion');

            // Fallbacks
            if ($fecha === '-') $fecha = date('Y-m-d');
            if ($titulo === '-') $titulo = $actividad !== '-' ? $actividad : 'Charla SST';
            if ($relator === '-') {
                $relator = trim(($recordMeta['first_name'] ?? '') . ' ' . ($recordMeta['last_name'] ?? ''));
                if (!$relator || trim($relator) === '') {
                    $relator = $recordMeta['user_name'] ?? 'Desconocido';
                }
            }

            // Limpiar fecha para slug (solo YYYY-MM-DD)
            $fechaSlug = preg_replace('/[^0-9-]/', '', substr($fecha, 0, 10));
            if (!$fechaSlug) $fechaSlug = date('Y-m-d');

            // Extraer componentes de fecha para carpetas
            $ts = strtotime($fechaSlug) ?: time();
            $anio = date('Y', $ts);
            $mesNum = date('m', $ts);
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
                       '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            $mesNombre = "{$mesNum} - " . ($meses[$mesNum] ?? $mesNum);

            // Sanitizar nombres de carpeta (quitar caracteres no válidos para paths)
            $sanitize = fn($v) => trim(preg_replace('/[\\\\\/\:*?"<>|]/u', '', $v)) ?: 'Sin especificar';

            $lugarFolder    = $sanitize($lugar !== '-' ? $lugar : 'Sin CD');
            $actividadFolder = $sanitize($actividad !== '-' ? $actividad : 'General');

            Log::info("Procesando Charla SST", [
                'formId' => $formId, 'dataId' => $dataId,
                'fecha' => $fecha, 'titulo' => $titulo, 'relator' => $relator,
                'lugar' => $lugar, 'actividad' => $actividad,
            ]);

            // Descargar el PDF generado por Kizeo
            $pdfContent = $this->kizeo->downloadPdf($formId, $dataId);

            if (!$pdfContent || strlen($pdfContent) < 100) {
                Log::warning('PDF de Charla SST vacío o inválido', ['size' => strlen($pdfContent ?? '')]);
                return response()->json(['status' => 'error', 'message' => 'PDF descargado está vacío'], 200);
            }

            // Nombre: 2026-03-31 - Titulo Actividad (Juan Pérez).pdf
            $tituloClean = preg_replace('/[\\\\\/\:*?"<>|]/u', '', $titulo);
            $relatorClean = preg_replace('/[\\\\\/\:*?"<>|]/u', '', $relator);
            $filename = "{$fechaSlug} - " . substr(trim($tituloClean), 0, 60) . " ({$relatorClean}).pdf";

            // Estructura: Charlas SST / 2026 / 03 - Marzo / CD Santiago / Capacitación / archivo.pdf
            $sharepointPath = null;
            try {
                $oneDrive = app(OneDriveService::class);
                if ($oneDrive->isConfigured()) {
                    $rootFolder = config('services.kizeo.charla_sharepoint_folder', 'Charlas SST');
                    $remotePath = "{$rootFolder}/{$anio}/{$mesNombre}/{$lugarFolder}/{$actividadFolder}/{$filename}";
                    $oneDrive->uploadFile($pdfContent, $remotePath, 'application/pdf', true);
                    $sharepointPath = $remotePath;
                    Log::info("Charla SST subida a SharePoint", ['path' => $remotePath]);
                } else {
                    Log::warning('SharePoint no configurado, PDF de Charla SST no se pudo subir');
                }
            } catch (\Throwable $e) {
                Log::warning('SharePoint upload de Charla SST falló (no crítico): ' . $e->getMessage());
            }

            // Enviar email con PDF adjunto (desactivado por defecto, activar desde Configuración)
            $emailEnviado = false;
            $destinatarios = [];
            $envioActivo = Configuracion::get('kizeo_charla_sst_email_activo', '0');
            if ($envioActivo === '1' || $envioActivo === 'true') {
                $destinatariosRaw = Configuracion::get(
                    'kizeo_charla_sst_destinatarios',
                    config('services.kizeo.notify_email', 'brayan@bmachero.com')
                );
                $destinatarios = array_filter(array_map('trim', explode(',', $destinatariosRaw)));

                if (!empty($destinatarios)) {
                    $cuerpo = "Se adjunta el registro de Charla de Seguridad SST.\n\n"
                        . "Título: {$titulo}\n"
                        . "Fecha: {$fecha}\n"
                        . "Relator: {$relator}\n"
                        . ($lugar !== '-' ? "Lugar: {$lugar}\n" : '')
                        . ($supervisor !== '-' ? "Supervisor: {$supervisor}\n" : '')
                        . "\nDocumento generado automáticamente desde Kizeo Forms.";

                    Mail::raw($cuerpo, function ($message) use ($destinatarios, $pdfContent, $filename, $fecha, $titulo) {
                        $message->to($destinatarios)
                            ->subject("Charla SST - {$titulo} ({$fecha})")
                            ->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);
                    });
                    $emailEnviado = true;
                    Log::info("Email de Charla SST enviado", ['destinatarios' => $destinatarios]);
                }
            }

            // Registrar en webhook_logs
            WebhookLog::logSuccess([
                'origen'          => 'kizeo',
                'form_id'         => $formId,
                'data_id'         => $dataId,
                'tipo'            => 'charla_sst',
                'resumen'         => "Charla SST - {$titulo} ({$relator})",
                'archivo'         => $filename,
                'sharepoint_path' => $sharepointPath,
                'email_enviado'   => $emailEnviado,
                'destinatarios'   => $destinatarios,
                'metadata'        => [
                    'titulo'    => $titulo,
                    'actividad' => $actividad,
                    'relator'   => $relator,
                    'lugar'     => $lugar,
                    'fecha'     => $fecha,
                ],
                'ip' => $ip,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Charla SST procesada correctamente']);

        } catch (\Throwable $e) {
            Log::error('Error en Kizeo Webhook (Charla SST): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            WebhookLog::logError([
                'origen'        => 'kizeo',
                'form_id'       => $formId,
                'data_id'       => $dataId,
                'tipo'          => 'charla_sst',
                'resumen'       => 'Error al procesar Charla SST',
                'error_message' => $e->getMessage(),
                'ip'            => $ip,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Internal processing error'], 500);
        }
    }

    /**
     * Handler para formulario Observación de Conducta.
     * Descarga el PDF generado por Kizeo y lo sube a SharePoint.
     *
     * Campos: nombre_del_observador, cargo, centro_de_distribucion, fecha,
     * tipo_de_observacion, negativa_1, conducta_observada, medida_de_control,
     * registro_de_retroalimentacion, nombre_del_trabajador (RUT),
     * nombre_trabajador_observado, cargo1, firma_observador, firma_colaborador
     */
    private function handleObservacionConducta(string $formId, string $dataId, array $payload, ?string $ip = null)
    {
        try {
            $record = $this->kizeo->getRecord($formId, $dataId);
            $fields = $record['fields'] ?? [];
            $recordMeta = $record ?? [];

            $getVal = function (string $key) use ($fields) {
                if (!isset($fields[$key])) return '-';
                $field = $fields[$key];
                $res = $field['result'] ?? $field['value'] ?? $field;
                if ($res === null) return '-';
                if (is_string($res)) return $res;
                if (isset($res['value'])) {
                    $val = $res['value'];
                    if (is_array($val) && isset($val['date'], $val['hour'])) return $val['date'] . ' ' . $val['hour'];
                    if (is_array($val) && isset($val['date'])) return $val['date'];
                    if (is_string($val)) return $val;
                    if (is_bool($val)) return $val ? 'Sí' : 'No';
                }
                return is_string($res) ? $res : '-';
            };

            // Extraer datos
            $fecha        = $getVal('fecha');
            $observador   = $getVal('nombre_del_observador');
            $cargoObs     = $getVal('cargo');
            $cd           = $getVal('centro_de_distribucion');
            $tipoObs      = $getVal('tipo_de_observacion');
            $trabajador   = $getVal('nombre_trabajador_observado');
            $rutTrabajador = $getVal('nombre_del_trabajador');
            $conducta     = $getVal('conducta_observada');

            // Fallbacks
            if ($fecha === '-') $fecha = date('Y-m-d');
            if ($observador === '-') {
                $observador = trim(($recordMeta['first_name'] ?? '') . ' ' . ($recordMeta['last_name'] ?? ''));
                if (!$observador || trim($observador) === '') {
                    $observador = $recordMeta['user_name'] ?? 'Desconocido';
                }
            }
            if ($trabajador === '-') $trabajador = 'Trabajador';
            if ($tipoObs === '-') $tipoObs = 'Observación';

            // Slug de fecha
            $fechaSlug = preg_replace('/[^0-9-]/', '', substr($fecha, 0, 10));
            if (!$fechaSlug) $fechaSlug = date('Y-m-d');

            // Componentes de fecha para carpetas
            $ts = strtotime($fechaSlug) ?: time();
            $anio = date('Y', $ts);
            $mesNum = date('m', $ts);
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
                       '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            $mesNombre = "{$mesNum} - " . ($meses[$mesNum] ?? $mesNum);

            $sanitize = fn($v) => trim(preg_replace('/[\\\\\/\:*?"<>|]/u', '', $v)) ?: 'Sin especificar';

            $cdFolder = $sanitize($cd !== '-' ? $cd : 'Sin CD');

            Log::info("Procesando Observación de Conducta", [
                'formId' => $formId, 'dataId' => $dataId,
                'fecha' => $fecha, 'observador' => $observador,
                'trabajador' => $trabajador, 'tipo' => $tipoObs, 'cd' => $cd,
            ]);

            // Descargar PDF de Kizeo
            $pdfContent = $this->kizeo->downloadPdf($formId, $dataId);

            if (!$pdfContent || strlen($pdfContent) < 100) {
                Log::warning('PDF de Observación Conducta vacío o inválido', ['size' => strlen($pdfContent ?? '')]);
                return response()->json(['status' => 'error', 'message' => 'PDF descargado está vacío'], 200);
            }

            // Nombre: 2026-04-01 - Juan Pérez (Positiva).pdf
            $trabajadorClean = preg_replace('/[\\\\\/\:*?"<>|]/u', '', $trabajador);
            $tipoClean = preg_replace('/[\\\\\/\:*?"<>|]/u', '', $tipoObs);
            $filename = "{$fechaSlug} - " . substr(trim($trabajadorClean), 0, 60) . " ({$tipoClean}).pdf";

            // Estructura: Observaciones Conducta / 2026 / 04 - Abril / CD Santiago / archivo.pdf
            $sharepointPath = null;
            try {
                $oneDrive = app(OneDriveService::class);
                if ($oneDrive->isConfigured()) {
                    $rootFolder = config('services.kizeo.observacion_sharepoint_folder', 'Observaciones Conducta');
                    $remotePath = "{$rootFolder}/{$anio}/{$mesNombre}/{$cdFolder}/{$filename}";
                    $oneDrive->uploadFile($pdfContent, $remotePath, 'application/pdf', true);
                    $sharepointPath = $remotePath;
                    Log::info("Observación de Conducta subida a SharePoint", ['path' => $remotePath]);
                } else {
                    Log::warning('SharePoint no configurado, PDF de Observación no se pudo subir');
                }
            } catch (\Throwable $e) {
                Log::warning('SharePoint upload de Observación falló (no crítico): ' . $e->getMessage());
            }

            // Registrar en webhook_logs
            WebhookLog::logSuccess([
                'origen'          => 'kizeo',
                'form_id'         => $formId,
                'data_id'         => $dataId,
                'tipo'            => 'observacion_conducta',
                'resumen'         => "Obs. Conducta - {$trabajador} ({$tipoObs})",
                'archivo'         => $filename,
                'sharepoint_path' => $sharepointPath,
                'email_enviado'   => false,
                'destinatarios'   => [],
                'metadata'        => [
                    'observador'  => $observador,
                    'trabajador'  => $trabajador,
                    'rut'         => $rutTrabajador,
                    'tipo'        => $tipoObs,
                    'conducta'    => substr($conducta, 0, 200),
                    'cd'          => $cd,
                    'fecha'       => $fecha,
                ],
                'ip' => $ip,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Observación de Conducta procesada correctamente']);

        } catch (\Throwable $e) {
            Log::error('Error en Kizeo Webhook (Observación Conducta): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            WebhookLog::logError([
                'origen'        => 'kizeo',
                'form_id'       => $formId,
                'data_id'       => $dataId,
                'tipo'          => 'observacion_conducta',
                'resumen'       => 'Error al procesar Observación de Conducta',
                'error_message' => $e->getMessage(),
                'ip'            => $ip,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Internal processing error'], 500);
        }
    }

    /**
     * Handler para formulario Inspección SST.
     * Descarga el PDF generado por Kizeo y lo sube a SharePoint.
     *
     * Campos: centro_de_distribucion, fecha_, hora_, responsable_area_,
     * inspeccion_efectuada_por_, cargo_, areas_inspeccionadas_, objetivo_1,
     * descripcion_de_la_accion_o_co, medidas_correctivas_preventiv, frecuencia,
     * responsable_de_ejecucion, verificacion
     */
    private function handleInspeccionSst(string $formId, string $dataId, array $payload, ?string $ip = null)
    {
        try {
            $record = $this->kizeo->getRecord($formId, $dataId);
            $fields = $record['fields'] ?? [];
            $recordMeta = $record ?? [];

            $getVal = function (string $key) use ($fields) {
                if (!isset($fields[$key])) return '-';
                $field = $fields[$key];
                $res = $field['result'] ?? $field['value'] ?? $field;
                if ($res === null) return '-';
                if (is_string($res)) return $res;
                if (isset($res['value'])) {
                    $val = $res['value'];
                    if (is_array($val) && isset($val['date'], $val['hour'])) return $val['date'] . ' ' . $val['hour'];
                    if (is_array($val) && isset($val['date'])) return $val['date'];
                    if (is_string($val)) return $val;
                    if (is_bool($val)) return $val ? 'Sí' : 'No';
                }
                return is_string($res) ? $res : '-';
            };

            // Extraer datos
            $fecha       = $getVal('fecha_');
            $cd          = $getVal('centro_de_distribucion');
            $inspector   = $getVal('inspeccion_efectuada_por_');
            $cargoInsp   = $getVal('cargo_');
            $responsable = $getVal('responsable_area_');
            $areas       = $getVal('areas_inspeccionadas_');
            $objetivo    = $getVal('objetivo_1');

            // Fallbacks
            if ($fecha === '-') $fecha = date('Y-m-d');
            if ($inspector === '-') {
                $inspector = trim(($recordMeta['first_name'] ?? '') . ' ' . ($recordMeta['last_name'] ?? ''));
                if (!$inspector || trim($inspector) === '') {
                    $inspector = $recordMeta['user_name'] ?? 'Desconocido';
                }
            }
            if ($areas === '-') $areas = 'Inspección General';

            // Slug de fecha
            $fechaSlug = preg_replace('/[^0-9-]/', '', substr($fecha, 0, 10));
            if (!$fechaSlug) $fechaSlug = date('Y-m-d');

            // Componentes de fecha para carpetas
            $ts = strtotime($fechaSlug) ?: time();
            $anio = date('Y', $ts);
            $mesNum = date('m', $ts);
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
                       '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            $mesNombre = "{$mesNum} - " . ($meses[$mesNum] ?? $mesNum);

            $sanitize = fn($v) => trim(preg_replace('/[\\\\\/\:*?"<>|]/u', '', $v)) ?: 'Sin especificar';

            $cdFolder = $sanitize($cd !== '-' ? $cd : 'Sin CD');

            Log::info("Procesando Inspección SST", [
                'formId' => $formId, 'dataId' => $dataId,
                'fecha' => $fecha, 'inspector' => $inspector,
                'areas' => $areas, 'cd' => $cd,
            ]);

            // Descargar PDF de Kizeo
            $pdfContent = $this->kizeo->downloadPdf($formId, $dataId);

            if (!$pdfContent || strlen($pdfContent) < 100) {
                Log::warning('PDF de Inspección SST vacío o inválido', ['size' => strlen($pdfContent ?? '')]);
                return response()->json(['status' => 'error', 'message' => 'PDF descargado está vacío'], 200);
            }

            // Nombre: 2026-04-01 - Áreas inspeccionadas (Inspector).pdf
            $areasClean = preg_replace('/[\\\\\/\:*?"<>|]/u', '', $areas);
            $inspectorClean = preg_replace('/[\\\\\/\:*?"<>|]/u', '', $inspector);
            $filename = "{$fechaSlug} - " . substr(trim($areasClean), 0, 60) . " ({$inspectorClean}).pdf";

            // Estructura: Inspecciones SST / 2026 / 04 - Abril / CD Santiago / archivo.pdf
            $sharepointPath = null;
            try {
                $oneDrive = app(OneDriveService::class);
                if ($oneDrive->isConfigured()) {
                    $rootFolder = config('services.kizeo.inspeccion_sharepoint_folder', 'Inspecciones SST');
                    $remotePath = "{$rootFolder}/{$anio}/{$mesNombre}/{$cdFolder}/{$filename}";
                    $oneDrive->uploadFile($pdfContent, $remotePath, 'application/pdf', true);
                    $sharepointPath = $remotePath;
                    Log::info("Inspección SST subida a SharePoint", ['path' => $remotePath]);
                } else {
                    Log::warning('SharePoint no configurado, PDF de Inspección no se pudo subir');
                }
            } catch (\Throwable $e) {
                Log::warning('SharePoint upload de Inspección falló (no crítico): ' . $e->getMessage());
            }

            // Registrar en webhook_logs
            WebhookLog::logSuccess([
                'origen'          => 'kizeo',
                'form_id'         => $formId,
                'data_id'         => $dataId,
                'tipo'            => 'inspeccion_sst',
                'resumen'         => "Inspección SST - {$areas} ({$inspector})",
                'archivo'         => $filename,
                'sharepoint_path' => $sharepointPath,
                'email_enviado'   => false,
                'destinatarios'   => [],
                'metadata'        => [
                    'inspector'   => $inspector,
                    'cargo'       => $cargoInsp,
                    'responsable' => $responsable,
                    'areas'       => $areas,
                    'objetivo'    => substr($objetivo, 0, 200),
                    'cd'          => $cd,
                    'fecha'       => $fecha,
                ],
                'ip' => $ip,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Inspección SST procesada correctamente']);

        } catch (\Throwable $e) {
            Log::error('Error en Kizeo Webhook (Inspección SST): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            WebhookLog::logError([
                'origen'        => 'kizeo',
                'form_id'       => $formId,
                'data_id'       => $dataId,
                'tipo'          => 'inspeccion_sst',
                'resumen'       => 'Error al procesar Inspección SST',
                'error_message' => $e->getMessage(),
                'ip'            => $ip,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Internal processing error'], 500);
        }
    }

    /**
     * Handler para formulario Visita a Terreno.
     * Descarga el PDF generado por Kizeo y lo sube a SharePoint.
     *
     * Campos: centro_de_costo, fecha, seccion_area_sector, participantes,
     * listado_de_actividades, temas_tratados_acuerdos_o,
     * nombre_de_quien_realiza_el_re, recibido_por
     */
    private function handleVisitaTerreno(string $formId, string $dataId, array $payload, ?string $ip = null)
    {
        try {
            $record = $this->kizeo->getRecord($formId, $dataId);
            $fields = $record['fields'] ?? [];
            $recordMeta = $record ?? [];

            $getVal = function (string $key) use ($fields) {
                if (!isset($fields[$key])) return '-';
                $field = $fields[$key];
                $res = $field['result'] ?? $field['value'] ?? $field;
                if ($res === null) return '-';
                if (is_string($res)) return $res;
                if (isset($res['value'])) {
                    $val = $res['value'];
                    if (is_array($val) && isset($val['date'], $val['hour'])) return $val['date'] . ' ' . $val['hour'];
                    if (is_array($val) && isset($val['date'])) return $val['date'];
                    if (is_string($val)) return $val;
                    if (is_bool($val)) return $val ? 'Sí' : 'No';
                }
                return is_string($res) ? $res : '-';
            };

            // Extraer datos
            $fecha      = $getVal('fecha');
            $cd         = $getVal('centro_de_costo');
            $seccion    = $getVal('seccion_area_sector');
            $realizador = $getVal('nombre_de_quien_realiza_el_re');
            $recibido   = $getVal('recibido_por');

            // Fallbacks
            if ($fecha === '-') $fecha = date('Y-m-d');
            if ($realizador === '-') {
                $realizador = trim(($recordMeta['first_name'] ?? '') . ' ' . ($recordMeta['last_name'] ?? ''));
                if (!$realizador || trim($realizador) === '') {
                    $realizador = $recordMeta['user_name'] ?? 'Desconocido';
                }
            }
            if ($seccion === '-') $seccion = 'General';

            // Slug de fecha
            $fechaSlug = preg_replace('/[^0-9-]/', '', substr($fecha, 0, 10));
            if (!$fechaSlug) $fechaSlug = date('Y-m-d');

            // Componentes de fecha para carpetas
            $ts = strtotime($fechaSlug) ?: time();
            $anio = date('Y', $ts);
            $mesNum = date('m', $ts);
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
                       '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            $mesNombre = "{$mesNum} - " . ($meses[$mesNum] ?? $mesNum);

            $sanitize = fn($v) => trim(preg_replace('/[\\\\\/\:*?"<>|]/u', '', $v)) ?: 'Sin especificar';

            $cdFolder = $sanitize($cd !== '-' ? $cd : 'Sin CD');

            Log::info("Procesando Visita a Terreno", [
                'formId' => $formId, 'dataId' => $dataId,
                'fecha' => $fecha, 'realizador' => $realizador,
                'seccion' => $seccion, 'cd' => $cd,
            ]);

            // Descargar PDF de Kizeo
            $pdfContent = $this->kizeo->downloadPdf($formId, $dataId);

            if (!$pdfContent || strlen($pdfContent) < 100) {
                Log::warning('PDF de Visita Terreno vacío o inválido', ['size' => strlen($pdfContent ?? '')]);
                return response()->json(['status' => 'error', 'message' => 'PDF descargado está vacío'], 200);
            }

            // Nombre: 2026-04-01 - Sección (Realizador).pdf
            $seccionClean = preg_replace('/[\\\\\/\:*?"<>|]/u', '', $seccion);
            $realizadorClean = preg_replace('/[\\\\\/\:*?"<>|]/u', '', $realizador);
            $filename = "{$fechaSlug} - " . substr(trim($seccionClean), 0, 60) . " ({$realizadorClean}).pdf";

            // Estructura: Visitas Terreno / 2026 / 04 - Abril / CD Santiago / archivo.pdf
            $sharepointPath = null;
            try {
                $oneDrive = app(OneDriveService::class);
                if ($oneDrive->isConfigured()) {
                    $rootFolder = config('services.kizeo.visita_sharepoint_folder', 'Visitas Terreno');
                    $remotePath = "{$rootFolder}/{$anio}/{$mesNombre}/{$cdFolder}/{$filename}";
                    $oneDrive->uploadFile($pdfContent, $remotePath, 'application/pdf', true);
                    $sharepointPath = $remotePath;
                    Log::info("Visita Terreno subida a SharePoint", ['path' => $remotePath]);
                } else {
                    Log::warning('SharePoint no configurado, PDF de Visita no se pudo subir');
                }
            } catch (\Throwable $e) {
                Log::warning('SharePoint upload de Visita falló (no crítico): ' . $e->getMessage());
            }

            // Registrar en webhook_logs
            WebhookLog::logSuccess([
                'origen'          => 'kizeo',
                'form_id'         => $formId,
                'data_id'         => $dataId,
                'tipo'            => 'visita_terreno',
                'resumen'         => "Visita Terreno - {$cd} / {$seccion} ({$realizador})",
                'archivo'         => $filename,
                'sharepoint_path' => $sharepointPath,
                'email_enviado'   => false,
                'destinatarios'   => [],
                'metadata'        => [
                    'realizador' => $realizador,
                    'recibido'   => $recibido,
                    'seccion'    => $seccion,
                    'cd'         => $cd,
                    'fecha'      => $fecha,
                ],
                'ip' => $ip,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Visita Terreno procesada correctamente']);

        } catch (\Throwable $e) {
            Log::error('Error en Kizeo Webhook (Visita Terreno): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            WebhookLog::logError([
                'origen'        => 'kizeo',
                'form_id'       => $formId,
                'data_id'       => $dataId,
                'tipo'          => 'visita_terreno',
                'resumen'       => 'Error al procesar Visita Terreno',
                'error_message' => $e->getMessage(),
                'ip'            => $ip,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Internal processing error'], 500);
        }
    }

    /**
     * Handler para formulario Informe Preliminar de Accidente (<24 Hrs).
     * Descarga el PDF generado por Kizeo y lo sube a SharePoint.
     *
     * Campos: rut_del_lesionado, nombre_del_lesionado, cargo_del_lesionado,
     * centro_de_distribucion, tipo_de_incidente, area_o_linea_donde_ocurrio,
     * fecha_del_accidente, hora_del_accidente, descripcion_del_evento,
     * parte_de_cuerpo_afectada, responsable_de_informe, fecha_del_informe
     */
    private function handleAccidenteSst(string $formId, string $dataId, array $payload, ?string $ip = null)
    {
        try {
            $record = $this->kizeo->getRecord($formId, $dataId);
            $fields = $record['fields'] ?? [];
            $recordMeta = $record ?? [];

            $getVal = function (string $key) use ($fields) {
                if (!isset($fields[$key])) return '-';
                $field = $fields[$key];
                $res = $field['result'] ?? $field['value'] ?? $field;
                if ($res === null) return '-';
                if (is_string($res)) return $res;
                if (isset($res['value'])) {
                    $val = $res['value'];
                    if (is_array($val) && isset($val['date'], $val['hour'])) return $val['date'] . ' ' . $val['hour'];
                    if (is_array($val) && isset($val['date'])) return $val['date'];
                    if (is_string($val)) return $val;
                    if (is_bool($val)) return $val ? 'Sí' : 'No';
                }
                return is_string($res) ? $res : '-';
            };

            // Extraer datos
            $fechaAccidente = $getVal('fecha_del_accidente');
            $cd             = $getVal('centro_de_distribucion');
            $lesionado      = $getVal('nombre_del_lesionado');
            $rutLesionado   = $getVal('rut_del_lesionado');
            $cargoLesionado = $getVal('cargo_del_lesionado');
            $tipoIncidente  = $getVal('tipo_de_incidente');
            $area           = $getVal('area_o_linea_donde_ocurrio');
            $responsable    = $getVal('responsable_de_informe');
            $parteCuerpo    = $getVal('parte_de_cuerpo_afectada');

            // Fallbacks
            if ($fechaAccidente === '-') $fechaAccidente = date('Y-m-d');
            if ($lesionado === '-') $lesionado = 'Trabajador';
            if ($tipoIncidente === '-') $tipoIncidente = 'Accidente';

            // Slug de fecha
            $fechaSlug = preg_replace('/[^0-9-]/', '', substr($fechaAccidente, 0, 10));
            if (!$fechaSlug) $fechaSlug = date('Y-m-d');

            // Componentes de fecha para carpetas
            $ts = strtotime($fechaSlug) ?: time();
            $anio = date('Y', $ts);
            $mesNum = date('m', $ts);
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
                       '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            $mesNombre = "{$mesNum} - " . ($meses[$mesNum] ?? $mesNum);

            $sanitize = fn($v) => trim(preg_replace('/[\\\\\/\:*?"<>|]/u', '', $v)) ?: 'Sin especificar';

            $cdFolder = $sanitize($cd !== '-' ? $cd : 'Sin CD');

            Log::info("Procesando Accidente SST", [
                'formId' => $formId, 'dataId' => $dataId,
                'fecha' => $fechaAccidente, 'lesionado' => $lesionado,
                'tipo' => $tipoIncidente, 'cd' => $cd,
            ]);

            // Descargar PDF de Kizeo
            $pdfContent = $this->kizeo->downloadPdf($formId, $dataId);

            if (!$pdfContent || strlen($pdfContent) < 100) {
                Log::warning('PDF de Accidente SST vacío o inválido', ['size' => strlen($pdfContent ?? '')]);
                return response()->json(['status' => 'error', 'message' => 'PDF descargado está vacío'], 200);
            }

            // Nombre: 2026-04-01 - Juan Pérez (Accidente Trabajo).pdf
            $lesionadoClean = preg_replace('/[\\\\\/\:*?"<>|]/u', '', $lesionado);
            $tipoClean = preg_replace('/[\\\\\/\:*?"<>|]/u', '', $tipoIncidente);
            $filename = "{$fechaSlug} - " . substr(trim($lesionadoClean), 0, 60) . " ({$tipoClean}).pdf";

            // Estructura: Accidentes SST / 2026 / 04 - Abril / CD Santiago / archivo.pdf
            $sharepointPath = null;
            try {
                $oneDrive = app(OneDriveService::class);
                if ($oneDrive->isConfigured()) {
                    $rootFolder = config('services.kizeo.accidente_sharepoint_folder', 'Accidentes SST');
                    $remotePath = "{$rootFolder}/{$anio}/{$mesNombre}/{$cdFolder}/{$filename}";
                    $oneDrive->uploadFile($pdfContent, $remotePath, 'application/pdf', true);
                    $sharepointPath = $remotePath;
                    Log::info("Accidente SST subido a SharePoint", ['path' => $remotePath]);
                } else {
                    Log::warning('SharePoint no configurado, PDF de Accidente no se pudo subir');
                }
            } catch (\Throwable $e) {
                Log::warning('SharePoint upload de Accidente falló (no crítico): ' . $e->getMessage());
            }

            // Registrar en webhook_logs
            WebhookLog::logSuccess([
                'origen'          => 'kizeo',
                'form_id'         => $formId,
                'data_id'         => $dataId,
                'tipo'            => 'accidente_sst',
                'resumen'         => "Accidente - {$lesionado} ({$tipoIncidente})",
                'archivo'         => $filename,
                'sharepoint_path' => $sharepointPath,
                'email_enviado'   => false,
                'destinatarios'   => [],
                'metadata'        => [
                    'lesionado'    => $lesionado,
                    'rut'          => $rutLesionado,
                    'cargo'        => $cargoLesionado,
                    'tipo'         => $tipoIncidente,
                    'area'         => $area,
                    'parte_cuerpo' => $parteCuerpo,
                    'responsable'  => $responsable,
                    'cd'           => $cd,
                    'fecha'        => $fechaAccidente,
                ],
                'ip' => $ip,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Accidente SST procesado correctamente']);

        } catch (\Throwable $e) {
            Log::error('Error en Kizeo Webhook (Accidente SST): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            WebhookLog::logError([
                'origen'        => 'kizeo',
                'form_id'       => $formId,
                'data_id'       => $dataId,
                'tipo'          => 'accidente_sst',
                'resumen'       => 'Error al procesar Accidente SST',
                'error_message' => $e->getMessage(),
                'ip'            => $ip,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Internal processing error'], 500);
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
