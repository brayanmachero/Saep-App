<?php

namespace App\Http\Controllers;

use App\Models\ReclutamientoWhatsappCampania;
use App\Models\ReclutamientoWhatsappAsignacion;
use App\Models\ReclutamientoWhatsappContacto;
use App\Models\ReclutamientoWhatsappConversacion;
use App\Models\ReclutamientoWhatsappPlantilla;
use App\Models\RegistroTratamientoDatos;
use App\Models\User;
use App\Services\MetaWhatsappCloudService;
use App\Services\ReclutamientoWhatsappCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReclutamientoWhatsappController extends Controller
{
    public function index(Request $request)
    {
        $contactos = ReclutamientoWhatsappContacto::query()
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $term = str_replace(['%', '_'], ['\\%', '\\_'], $request->string('buscar')->toString());
                $query->where(function ($contacts) use ($term) {
                    $contacts->where('nombre', 'like', "%{$term}%")
                        ->orWhere('telefono', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('consentimiento'), function ($query) use ($request) {
                if ($request->consentimiento === 'vigente') {
                    $query->elegiblesParaCampanias();
                }

                if ($request->consentimiento === 'sin_consentimiento') {
                    $query->where(function ($contacts) {
                        $contacts->where('consentimiento_whatsapp', false)
                            ->orWhereNull('consentimiento_aceptado_at')
                            ->orWhereNull('consentimiento_finalidad')
                            ->orWhereNull('consentimiento_version')
                            ->orWhereNull('consentimiento_evidencia_ref')
                            ->orWhereNull('consentimiento_verificado_at')
                            ->orWhereNull('consentimiento_verificado_por')
                            ->orWhereDate('retencion_hasta', '<', today());
                    })->whereNull('consentimiento_revocado_at');
                }

                if ($request->consentimiento === 'revocado') {
                    $query->whereNotNull('consentimiento_revocado_at');
                }
            })
            ->latest()
            ->paginate(12, ['*'], 'contactos_page')
            ->withQueryString();

        $campanias = ReclutamientoWhatsappCampania::query()
            ->with(['creador', 'aprobador', 'plantilla'])
            ->latest()
            ->paginate(10, ['*'], 'campanias_page')
            ->withQueryString();

        $stats = [
            'contactos' => ReclutamientoWhatsappContacto::count(),
            'consentimiento_vigente' => ReclutamientoWhatsappContacto::elegiblesParaCampanias()->count(),
            'pendientes_consentimiento' => ReclutamientoWhatsappContacto::query()
                ->whereNull('consentimiento_revocado_at')
                ->where(function ($contacts) {
                    $contacts->where('consentimiento_whatsapp', false)
                        ->orWhereNull('consentimiento_aceptado_at')
                        ->orWhereNull('consentimiento_finalidad')
                        ->orWhereNull('consentimiento_version')
                        ->orWhereNull('consentimiento_evidencia_ref')
                        ->orWhereNull('consentimiento_verificado_at')
                        ->orWhereNull('consentimiento_verificado_por')
                        ->orWhereDate('retencion_hasta', '<', today());
                })->count(),
            'bajas' => ReclutamientoWhatsappContacto::whereNotNull('consentimiento_revocado_at')->count(),
            'campanias_borrador' => ReclutamientoWhatsappCampania::whereIn('estado', ['borrador', 'pendiente_aprobacion'])->count(),
        ];

        $plantillas = ReclutamientoWhatsappPlantilla::query()
            ->where('estado', 'aprobada')
            ->orderBy('nombre_meta')
            ->get();
        $finalidades = ReclutamientoWhatsappContacto::FINALIDADES;

        $metaConfigurado = app(\App\Services\MetaWhatsappCloudService::class)->isConfigured();
        $puedeCrear = $request->user()->tieneAcceso('reclutamiento_whatsapp', 'puede_crear');
        $puedeEditar = $request->user()->tieneAcceso('reclutamiento_whatsapp', 'puede_editar');

        return view('reclutamiento_whatsapp.index', compact(
            'campanias',
            'contactos',
            'plantillas',
            'finalidades',
            'stats',
            'metaConfigurado',
            'puedeCrear',
            'puedeEditar'
        ));
    }

    public function storeContacto(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'telefono' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:200'],
            'consentimiento_aceptado_at' => ['required', 'date', 'before_or_equal:now'],
            'consentimiento_origen' => ['required', 'string', 'max:120'],
            'consentimiento_texto' => ['required', 'string', 'max:1500'],
            'consentimiento_finalidad' => ['required', Rule::in(array_keys(ReclutamientoWhatsappContacto::FINALIDADES))],
            'consentimiento_version' => ['required', 'string', 'max:50'],
            'consentimiento_evidencia_ref' => ['required', 'string', 'max:500'],
            'retencion_hasta' => ['required', 'date', 'after_or_equal:today'],
            'confirma_consentimiento' => ['accepted'],
        ], [
            'confirma_consentimiento.accepted' => 'Debes confirmar que existe autorización verificable para WhatsApp.',
        ]);

        $telefono = $this->normalizarTelefono($validated['telefono']);
        if (!$telefono) {
            return back()->withInput()->withErrors(['telefono' => 'Usa un teléfono internacional válido, por ejemplo +56912345678.']);
        }

        if (ReclutamientoWhatsappContacto::where('telefono', $telefono)->exists()) {
            return back()->withInput()->withErrors(['telefono' => 'Ese teléfono ya está registrado para Reclutamiento WhatsApp.']);
        }

        $contacto = ReclutamientoWhatsappContacto::create([
            'nombre' => $validated['nombre'],
            'telefono' => $telefono,
            'email' => $validated['email'] ?? null,
            'origen' => 'manual',
            'origen_detalle' => 'Registro individual por Reclutamiento',
            'consentimiento_whatsapp' => true,
            'consentimiento_aceptado_at' => $validated['consentimiento_aceptado_at'],
            'consentimiento_origen' => $validated['consentimiento_origen'],
            'consentimiento_texto' => $validated['consentimiento_texto'],
            'consentimiento_finalidad' => $validated['consentimiento_finalidad'],
            'consentimiento_version' => $validated['consentimiento_version'],
            'consentimiento_evidencia_ref' => $validated['consentimiento_evidencia_ref'],
            'consentimiento_verificado_at' => now(),
            'consentimiento_verificado_por' => $request->user()->id,
            'retencion_hasta' => $validated['retencion_hasta'],
            'consentimiento_ip' => $request->ip(),
            'consentimiento_user_agent' => $request->userAgent(),
        ]);

        RegistroTratamientoDatos::registrar(
            'whatsapp_contacto_registrado',
            'reclutamiento_whatsapp_contactos',
            $contacto->id,
            'personal',
            'Contacto de Reclutamiento WhatsApp incorporado con consentimiento verificable.',
            null,
            [
                'telefono_hash' => hash('sha256', $contacto->telefono),
                'consentimiento' => true,
                'finalidad' => $contacto->consentimiento_finalidad,
                'retencion_hasta' => $contacto->retencion_hasta?->toDateString(),
            ]
        );

        return redirect()->route('reclutamiento-whatsapp.index')->withFragment('contactos')
            ->with('success', 'Contacto incorporado. Puede recibir campañas de reclutamiento mientras su consentimiento siga vigente.');
    }

    /**
     * Importa bases descargadas desde portales de empleo. La importación no
     * convierte la postulación en autorización para WhatsApp: sin evidencia
     * expresa, los registros quedan excluidos de campañas.
     */
    public function importarContactos(Request $request)
    {
        $tieneConsentimiento = $request->boolean('confirma_consentimiento_importado');

        $validated = $request->validate([
            'archivo' => ['required', 'file', 'max:10240', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
            'origen_detalle' => ['required', 'string', 'max:160'],
            'confirma_consentimiento_importado' => ['nullable', 'boolean'],
            'consentimiento_aceptado_at' => [Rule::requiredIf($tieneConsentimiento), 'nullable', 'date', 'before_or_equal:now'],
            'consentimiento_origen' => [Rule::requiredIf($tieneConsentimiento), 'nullable', 'string', 'max:120'],
            'consentimiento_texto' => [Rule::requiredIf($tieneConsentimiento), 'nullable', 'string', 'max:1500'],
            'consentimiento_finalidad' => [Rule::requiredIf($tieneConsentimiento), 'nullable', Rule::in(array_keys(ReclutamientoWhatsappContacto::FINALIDADES))],
            'consentimiento_version' => [Rule::requiredIf($tieneConsentimiento), 'nullable', 'string', 'max:50'],
            'consentimiento_evidencia_ref' => [Rule::requiredIf($tieneConsentimiento), 'nullable', 'string', 'max:500'],
            'retencion_hasta' => [Rule::requiredIf($tieneConsentimiento), 'nullable', 'date', 'after_or_equal:today'],
        ], [
            'archivo.mimetypes' => 'Usa un archivo CSV exportado desde el portal de empleo.',
            'consentimiento_origen.required' => 'Indica de dónde se obtiene la autorización específica para WhatsApp.',
            'consentimiento_texto.required' => 'Registra el texto o evidencia verificable de la autorización.',
            'consentimiento_evidencia_ref.required' => 'Indica la referencia donde se puede verificar la autorización.',
        ]);

        $rows = $this->leerCsv($request->file('archivo')->getRealPath());
        if (count($rows) < 2) {
            return back()->withErrors(['archivo' => 'El CSV debe tener una cabecera y al menos un contacto.']);
        }

        $headers = array_map(fn ($header) => $this->normalizarCabecera((string) $header), array_shift($rows));
        $columnas = $this->mapearColumnasCsv($headers);
        if ($columnas['telefono'] === null) {
            return back()->withErrors(['archivo' => 'No se encontró una columna de teléfono. Usa uno de estos encabezados: teléfono, celular, whatsapp o móvil.']);
        }

        $resultado = ['insertados' => 0, 'duplicados' => 0, 'invalidos' => 0];
        DB::transaction(function () use ($rows, $columnas, $validated, $tieneConsentimiento, $request, &$resultado) {
            foreach ($rows as $row) {
                if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $telefono = $this->normalizarTelefono((string) ($row[$columnas['telefono']] ?? ''));
                if (!$telefono) {
                    $resultado['invalidos']++;
                    continue;
                }

                if (ReclutamientoWhatsappContacto::where('telefono', $telefono)->exists()) {
                    $resultado['duplicados']++;
                    continue;
                }

                $nombre = trim((string) ($columnas['nombre'] !== null ? ($row[$columnas['nombre']] ?? '') : ''));
                $email = trim((string) ($columnas['email'] !== null ? ($row[$columnas['email']] ?? '') : ''));

                ReclutamientoWhatsappContacto::create([
                    'nombre' => $nombre !== '' ? $nombre : 'Contacto sin nombre',
                    'telefono' => $telefono,
                    'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                    'origen' => 'portal_empleo',
                    'origen_detalle' => $validated['origen_detalle'],
                    'consentimiento_whatsapp' => $tieneConsentimiento,
                    'consentimiento_aceptado_at' => $tieneConsentimiento ? $validated['consentimiento_aceptado_at'] : null,
                    'consentimiento_origen' => $tieneConsentimiento ? $validated['consentimiento_origen'] : null,
                    'consentimiento_texto' => $tieneConsentimiento ? $validated['consentimiento_texto'] : null,
                    'consentimiento_finalidad' => $tieneConsentimiento ? $validated['consentimiento_finalidad'] : null,
                    'consentimiento_version' => $tieneConsentimiento ? $validated['consentimiento_version'] : null,
                    'consentimiento_evidencia_ref' => $tieneConsentimiento ? $validated['consentimiento_evidencia_ref'] : null,
                    'consentimiento_verificado_at' => $tieneConsentimiento ? now() : null,
                    'consentimiento_verificado_por' => $tieneConsentimiento ? $request->user()->id : null,
                    'retencion_hasta' => $tieneConsentimiento ? $validated['retencion_hasta'] : null,
                    'consentimiento_ip' => $tieneConsentimiento ? $request->ip() : null,
                    'consentimiento_user_agent' => $tieneConsentimiento ? $request->userAgent() : null,
                ]);
                $resultado['insertados']++;
            }
        });

        RegistroTratamientoDatos::registrar(
            'whatsapp_base_portal_importada',
            'reclutamiento_whatsapp_contactos',
            null,
            'personal',
            'Base de contactos de portal de empleo importada para Reclutamiento WhatsApp.',
            null,
            array_merge($resultado, ['origen' => $validated['origen_detalle'], 'consentimiento_confirmado' => $tieneConsentimiento])
        );

        $estado = $tieneConsentimiento
            ? 'con consentimiento verificable.'
            : 'sin consentimiento de WhatsApp; quedaron bloqueados para campañas.';

        return redirect()->route('reclutamiento-whatsapp.index')->withFragment('contactos')
            ->with('success', "Base procesada: {$resultado['insertados']} incorporados, {$resultado['duplicados']} duplicados y {$resultado['invalidos']} inválidos. Los contactos quedaron {$estado}");
    }

    public function revocarContacto(Request $request, ReclutamientoWhatsappContacto $contacto)
    {
        $validated = $request->validate([
            'motivo_revocacion' => ['nullable', 'string', 'max:200'],
        ]);

        if ($contacto->consentimiento_revocado_at) {
            return back()->with('info', 'El contacto ya tenía su consentimiento revocado.');
        }

        $contacto->update([
            'consentimiento_whatsapp' => false,
            'consentimiento_revocado_at' => now(),
            'motivo_revocacion' => $validated['motivo_revocacion'] ?: 'Baja registrada por Reclutamiento',
        ]);

        RegistroTratamientoDatos::registrar(
            'whatsapp_consentimiento_revocado',
            'reclutamiento_whatsapp_contactos',
            $contacto->id,
            'personal',
            'Se registró la baja de WhatsApp para Reclutamiento.',
            ['consentimiento' => true],
            ['consentimiento' => false]
        );

        return back()->with('success', 'Baja registrada. Este contacto queda excluido de cualquier campaña futura.');
    }

    public function storeCampania(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:1500'],
            'plantilla_id' => ['required', Rule::exists('reclutamiento_whatsapp_plantillas', 'id')->where('estado', 'aprobada')],
            'finalidad' => ['required', Rule::in(array_keys(ReclutamientoWhatsappContacto::FINALIDADES))],
            'accion' => ['required', Rule::in(['borrador', 'pendiente_aprobacion'])],
        ], [
            'plantilla_nombre.regex' => 'El nombre de plantilla Meta usa solo minúsculas, números y guiones bajos.',
        ]);

        $plantilla = ReclutamientoWhatsappPlantilla::findOrFail($validated['plantilla_id']);

        $cantidad = ReclutamientoWhatsappContacto::elegiblesParaCampanias($validated['finalidad'])->count();
        if ($validated['accion'] === 'pendiente_aprobacion' && $cantidad === 0) {
            return back()->withInput()->withErrors(['finalidad' => 'No existen contactos habilitados para la finalidad seleccionada. Guarda el borrador o completa la evidencia de consentimiento antes de enviarlo a aprobación.']);
        }

        $campania = ReclutamientoWhatsappCampania::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'plantilla_id' => $plantilla?->id,
            'plantilla_nombre' => $plantilla->nombre_meta,
            'plantilla_idioma' => $plantilla->idioma,
            'categoria' => $plantilla->categoria,
            'finalidad' => $validated['finalidad'],
            'estado' => $validated['accion'],
            'filtro_destinatarios' => [
                'consentimiento_verificable_vigente' => true,
                'finalidad' => $validated['finalidad'],
            ],
            'destinatarios_estimados' => $cantidad,
            'creada_por' => $request->user()->id,
        ]);

        RegistroTratamientoDatos::registrar(
            'whatsapp_campania_creada',
            'reclutamiento_whatsapp_campanias',
            $campania->id,
            'personal',
            "Campaña de Reclutamiento WhatsApp {$campania->nombre} creada como {$campania->estado}.",
            null,
            [
                'plantilla' => $campania->plantilla_nombre,
                'finalidad' => $campania->finalidad,
                'destinatarios_estimados' => $cantidad,
            ]
        );

        return redirect()->route('reclutamiento-whatsapp.index')->withFragment('campanias')
            ->with('success', 'Campaña preparada. No se envió ningún mensaje. Requiere aprobación y programación explícita.');
    }

    public function aprobarCampania(Request $request, ReclutamientoWhatsappCampania $campania)
    {
        if ($campania->estado !== 'pendiente_aprobacion') {
            return back()->with('error', 'Solo se pueden aprobar campañas enviadas a revisión.');
        }

        $campania->load('plantilla');
        if (!$campania->plantilla || $campania->plantilla->estado !== 'aprobada') {
            return back()->with('error', 'La campaña requiere una plantilla aprobada y sincronizada desde Meta.');
        }

        $cantidad = ReclutamientoWhatsappContacto::elegiblesParaCampanias($campania->finalidad)->count();
        if ($cantidad === 0) {
            return back()->with('error', 'No se puede aprobar sin contactos habilitados para la finalidad de esta campaña.');
        }

        $campania->update([
            'estado' => 'aprobada',
            'aprobada_por' => $request->user()->id,
            'aprobada_at' => now(),
            'destinatarios_estimados' => $cantidad,
        ]);

        RegistroTratamientoDatos::registrar(
            'whatsapp_campania_aprobada',
            'reclutamiento_whatsapp_campanias',
            $campania->id,
            'personal',
            "Campaña de Reclutamiento WhatsApp {$campania->nombre} aprobada para configuración posterior.",
            ['estado' => 'pendiente_aprobacion'],
            ['estado' => 'aprobada']
        );

        return back()->with('success', 'Campaña aprobada. Aún no se envía: programa fecha y hora para crear la audiencia y despacharla.');
    }

    public function programarCampania(
        Request $request,
        ReclutamientoWhatsappCampania $campania,
        ReclutamientoWhatsappCampaignService $campaigns
    ) {
        if ($campania->estado !== 'aprobada') {
            return back()->with('error', 'Solo se pueden programar campañas aprobadas.');
        }

        if (!$campaigns->isConfigured()) {
            return back()->with('error', 'Meta WhatsApp Cloud API no está configurada. No se puede programar un envío.');
        }

        $validated = $request->validate([
            'programada_para' => ['required', 'date', 'after_or_equal:now'],
        ]);
        $campania->load('plantilla');
        if (!$campania->plantilla || !$campaigns->plantillaEsEstatica($campania->plantilla->componentes)) {
            return back()->with('error', 'Esta versión solo programa plantillas sincronizadas sin variables. Configura los campos de la plantilla antes de usar variables como nombre o cargo.');
        }

        $cantidad = $campaigns->prepararDestinatarios($campania);
        if ($cantidad === 0) {
            return back()->with('error', 'No quedan contactos con consentimiento vigente para la finalidad de esta campaña.');
        }

        $campania->update([
            'estado' => 'programada',
            'programada_para' => $validated['programada_para'],
        ]);
        RegistroTratamientoDatos::registrar(
            'whatsapp_campania_programada',
            'reclutamiento_whatsapp_campanias',
            $campania->id,
            'personal',
            "Campaña de Reclutamiento WhatsApp {$campania->nombre} programada para despacho.",
            ['estado' => 'aprobada'],
            ['estado' => 'programada', 'destinatarios' => $cantidad, 'programada_para' => $campania->programada_para]
        );

        return back()->with('success', "Campaña programada para {$campania->programada_para->format('d/m/Y H:i')}. Audiencia congelada: {$cantidad} contacto(s).");
    }

    public function sincronizarPlantillas(MetaWhatsappCloudService $whatsapp)
    {
        try {
            $plantillas = $whatsapp->listTemplates();
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        foreach ($plantillas as $plantilla) {
            $nombre = trim((string) ($plantilla['name'] ?? ''));
            if ($nombre === '') {
                continue;
            }

            ReclutamientoWhatsappPlantilla::updateOrCreate(
                ['nombre_meta' => $nombre],
                [
                    'idioma' => (string) ($plantilla['language'] ?? 'es'),
                    'categoria' => strtolower((string) ($plantilla['category'] ?? 'utility')),
                    'estado' => strtolower((string) ($plantilla['status'] ?? 'pendiente')),
                    'componentes' => $plantilla['components'] ?? [],
                    'sincronizada_at' => now(),
                ]
            );
        }

        return back()->with('success', 'Plantillas sincronizadas desde Meta: ' . count($plantillas) . '.');
    }

    public function bandeja(Request $request)
    {
        $puedeCoordinar = $request->user()->tieneAcceso('reclutamiento_whatsapp', 'puede_editar');

        $conversaciones = ReclutamientoWhatsappConversacion::query()
            ->with(['contacto', 'asignado'])
            ->when(! $puedeCoordinar, fn ($query) => $query->where('asignada_a', $request->user()->id))
            ->when($request->filled('estado'), fn ($query) => $query->where('estado', $request->string('estado')->toString()))
            ->when($request->filled('asignada_a'), function ($query) use ($request) {
                $request->asignada_a === 'sin_asignar'
                    ? $query->whereNull('asignada_a')
                    : $query->where('asignada_a', $request->integer('asignada_a'));
            })
            ->orderByDesc('ultimo_mensaje_at')
            ->orderByDesc('id')
            ->get();

        $seleccionada = $request->filled('conversacion')
            ? $conversaciones->firstWhere('id', $request->integer('conversacion'))
            : $conversaciones->first();

        $seleccionada?->load([
            'mensajes' => fn ($query) => $query->with('enviadoPor')->orderBy('ocurrido_at')->orderBy('id'),
            'asignaciones' => fn ($query) => $query->with(['asignado', 'asignadoPor'])->latest('id'),
            'asignado',
            'contacto',
        ]);

        $agentes = User::query()
            ->with('rol')
            ->where('activo', true)
            ->whereHas('rol', fn ($query) => $query->whereIn('codigo', ['SUPER_ADMIN', 'RECLUTAMIENTO_COORDINADOR', 'RECLUTADOR']))
            ->orderBy('name')
            ->get();

        $metaConfigurado = app(\App\Services\MetaWhatsappCloudService::class)->isConfigured();

        return view('reclutamiento_whatsapp.bandeja', compact(
            'conversaciones',
            'seleccionada',
            'agentes',
            'puedeCoordinar',
            'metaConfigurado'
        ));
    }

    public function asignarConversacion(Request $request, ReclutamientoWhatsappConversacion $conversacion)
    {
        $validated = $request->validate([
            'asignada_a' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $agente = !empty($validated['asignada_a']) ? User::with('rol')->findOrFail($validated['asignada_a']) : null;
        if ($agente && !$agente->tieneAcceso('reclutamiento_whatsapp')) {
            return back()->with('error', 'Solo se puede asignar una conversación a un usuario de Reclutamiento WhatsApp.');
        }

        $anterior = $conversacion->asignada_a;
        $accion = $agente ? ($anterior ? 'reasignada' : 'asignada') : 'desasignada';
        $conversacion->update([
            'asignada_a' => $agente?->id,
            'estado' => $agente && $conversacion->estado === 'nueva' ? 'asignada' : $conversacion->estado,
        ]);
        ReclutamientoWhatsappAsignacion::create([
            'conversacion_id' => $conversacion->id,
            'asignada_a' => $agente?->id,
            'asignada_por' => $request->user()->id,
            'accion' => $accion,
        ]);
        RegistroTratamientoDatos::registrar(
            'whatsapp_conversacion_' . $accion,
            'reclutamiento_whatsapp_conversaciones',
            $conversacion->id,
            'personal',
            'Asignación de conversación de Reclutamiento actualizada.',
            ['asignada_a' => $anterior],
            ['asignada_a' => $agente?->id]
        );

        return back()->with('success', 'Responsable de la conversación actualizado.');
    }

    public function actualizarEstadoConversacion(Request $request, ReclutamientoWhatsappConversacion $conversacion)
    {
        $puedeCoordinar = $request->user()->tieneAcceso('reclutamiento_whatsapp', 'puede_editar');
        if (!$puedeCoordinar && $conversacion->asignada_a !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'estado' => ['required', Rule::in(ReclutamientoWhatsappConversacion::ESTADOS)],
        ]);
        $anterior = $conversacion->estado;
        $nuevo = $validated['estado'];
        $conversacion->update([
            'estado' => $nuevo,
            'cerrada_at' => in_array($nuevo, ['resuelta', 'cerrada'], true) ? now() : null,
        ]);
        RegistroTratamientoDatos::registrar(
            'whatsapp_conversacion_estado_actualizado',
            'reclutamiento_whatsapp_conversaciones',
            $conversacion->id,
            'personal',
            'Estado de conversación de Reclutamiento actualizado.',
            ['estado' => $anterior],
            ['estado' => $nuevo]
        );

        return back()->with('success', 'Estado de conversación actualizado.');
    }

    public function responderConversacion(
        Request $request,
        ReclutamientoWhatsappConversacion $conversacion,
        MetaWhatsappCloudService $whatsapp
    ) {
        $puedeCoordinar = $request->user()->tieneAcceso('reclutamiento_whatsapp', 'puede_editar');
        if (!$puedeCoordinar && $conversacion->asignada_a !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'contenido' => ['required', 'string', 'max:4096'],
        ]);

        if (!$conversacion->ultimo_mensaje_entrante_at || $conversacion->ultimo_mensaje_entrante_at->lt(now()->subHours(24))) {
            return back()->with('error', 'La ventana de respuesta directa expiró. Para reiniciar contacto corresponde usar una plantilla aprobada de Meta.');
        }

        try {
            $respuesta = $whatsapp->sendText($conversacion->contacto->telefono, $validated['contenido']);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $ocurridoAt = now();
        $metaMessageId = data_get($respuesta, 'messages.0.id');
        $mensaje = $conversacion->mensajes()->create([
            'direccion' => 'saliente',
            'tipo' => 'texto',
            'meta_message_id' => $metaMessageId,
            'contenido' => $validated['contenido'],
            'enviado_por' => $request->user()->id,
            'estado' => 'enviado',
            'ocurrido_at' => $ocurridoAt,
        ]);
        $conversacion->update([
            'estado' => 'esperando_respuesta',
            'ultimo_mensaje_preview' => $validated['contenido'],
            'ultimo_mensaje_at' => $ocurridoAt,
        ]);
        RegistroTratamientoDatos::registrar(
            'whatsapp_respuesta_enviada',
            'reclutamiento_whatsapp_mensajes',
            $mensaje->id,
            'personal',
            'Respuesta enviada desde la bandeja de Reclutamiento.',
            null,
            ['conversacion_id' => $conversacion->id, 'longitud' => mb_strlen($validated['contenido'])]
        );

        return back()->with('success', 'Respuesta enviada y registrada en la conversación.');
    }

    private function normalizarTelefono(string $telefono): ?string
    {
        $digits = preg_replace('/\D+/', '', $telefono);

        if (str_starts_with(trim($telefono), '+') && strlen($digits) >= 8 && strlen($digits) <= 15) {
            return '+' . $digits;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '+56' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '569')) {
            return '+' . $digits;
        }

        return null;
    }

    private function leerCsv(string $path): array
    {
        $contenido = (string) file_get_contents($path);
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? '';
        $lineas = preg_split('/\r\n|\r|\n/', $contenido) ?: [];
        $primera = $lineas[0] ?? '';
        $delimitadores = [',', ';', "\t"];
        $delimitador = collect($delimitadores)->sortByDesc(fn ($item) => substr_count($primera, $item))->first();

        return collect($lineas)
            ->filter(fn ($linea) => trim($linea) !== '')
            ->map(fn ($linea) => str_getcsv($linea, $delimitador))
            ->values()
            ->all();
    }

    private function normalizarCabecera(string $cabecera): string
    {
        $cabecera = mb_strtolower(trim($cabecera));
        $cabecera = strtr($cabecera, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return preg_replace('/[^a-z0-9]+/', '', $cabecera) ?: '';
    }

    private function mapearColumnasCsv(array $headers): array
    {
        $buscar = fn (array $aliases) => collect($headers)->search(fn ($header) => in_array($header, $aliases, true));

        $telefono = $buscar(['telefono', 'telefonocelular', 'celular', 'movil', 'whatsapp', 'numero', 'numerotelefono']);
        $nombre = $buscar(['nombre', 'nombrecompleto', 'candidato', 'postulante', 'contacto']);
        $email = $buscar(['email', 'correo', 'correoelectronico']);

        return [
            'telefono' => $telefono === false ? null : $telefono,
            'nombre' => $nombre === false ? null : $nombre,
            'email' => $email === false ? null : $email,
        ];
    }
}
