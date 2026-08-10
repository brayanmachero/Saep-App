<?php

namespace App\Http\Controllers;

use App\Models\ReclutamientoWhatsappEvento;
use App\Models\ReclutamientoWhatsappContacto;
use App\Models\ReclutamientoWhatsappConversacion;
use App\Models\ReclutamientoWhatsappDestinatario;
use App\Models\ReclutamientoWhatsappMensaje;
use App\Models\RegistroTratamientoDatos;
use App\Services\MetaWhatsappCloudService;
use App\Services\ReclutamientoWhatsappCampaignService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReclutamientoWhatsappWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $verifyToken = (string) config('services.whatsapp.verify_token');

        if ($verifyToken === '' || $request->query('hub_mode') !== 'subscribe' || !hash_equals($verifyToken, (string) $request->query('hub_verify_token'))) {
            abort(403);
        }

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function handle(Request $request, MetaWhatsappCloudService $whatsapp)
    {
        if (!$whatsapp->hasValidSignature($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            abort(403);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                $value = (array) data_get($change, 'value', []);
                $this->registrarMensajesEntrantes($value);
                $this->registrarEstadosEntrega($value);

                ReclutamientoWhatsappEvento::create([
                    'meta_event_id' => (string) data_get($entry, 'id'),
                    'tipo' => (string) data_get($change, 'field', 'webhook'),
                    'datos' => $this->redactarCambio($value),
                    'ocurrido_at' => now(),
                ]);
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * Los mensajes entrantes se guardan solo en la conversación de RRHH y
     * mediante cast encrypted. Un mensaje recibido no habilita campañas;
     * una instrucción inequívoca de baja sí bloquea futuras campañas.
     */
    private function registrarMensajesEntrantes(array $value): void
    {
        $perfiles = collect($value['contacts'] ?? [])
            ->keyBy(fn (array $contacto) => (string) ($contacto['wa_id'] ?? ''));

        foreach ((array) ($value['messages'] ?? []) as $message) {
            $from = preg_replace('/\D+/', '', (string) ($message['from'] ?? ''));
            $messageId = (string) ($message['id'] ?? '');
            if ($from === '' || $messageId === '' || ReclutamientoWhatsappMensaje::where('meta_message_id', $messageId)->exists()) {
                continue;
            }

            $perfil = (array) ($perfiles->get($from) ?? []);
            $nombre = trim((string) data_get($perfil, 'profile.name')) ?: 'Contacto sin identificar';
            $contacto = ReclutamientoWhatsappContacto::firstOrCreate(
                ['telefono' => '+' . $from],
                [
                    'nombre' => $nombre,
                    'origen' => 'whatsapp_entrante',
                    'origen_detalle' => 'Webhook oficial Meta',
                    'consentimiento_whatsapp' => false,
                ]
            );

            $ocurridoAt = $this->timestampMeta($message['timestamp'] ?? null);
            $conversacion = ReclutamientoWhatsappConversacion::firstOrCreate(
                ['contacto_id' => $contacto->id],
                ['estado' => 'nueva', 'iniciada_at' => $ocurridoAt]
            );

            $tipo = (string) ($message['type'] ?? 'desconocido');
            $contenido = $tipo === 'text'
                ? trim((string) data_get($message, 'text.body'))
                : "[{$tipo} recibido; revisar en WhatsApp]";

            if ($tipo === 'text' && $this->esSolicitudDeBaja($contenido)) {
                $this->registrarBajaPorMensaje($contacto, $ocurridoAt);
            }

            ReclutamientoWhatsappMensaje::create([
                'conversacion_id' => $conversacion->id,
                'direccion' => 'entrante',
                'tipo' => $tipo,
                'meta_message_id' => $messageId,
                'contenido' => $contenido !== '' ? $contenido : '[Mensaje sin texto]',
                'estado' => 'recibido',
                'ocurrido_at' => $ocurridoAt,
            ]);

            $conversacion->update([
                'estado' => in_array($conversacion->estado, ['resuelta', 'cerrada'], true) ? 'nueva' : $conversacion->estado,
                'ultimo_mensaje_preview' => $contenido !== '' ? $contenido : '[Mensaje sin texto]',
                'ultimo_mensaje_at' => $ocurridoAt,
                'ultimo_mensaje_entrante_at' => $ocurridoAt,
                'cerrada_at' => null,
            ]);
        }
    }

    private function registrarEstadosEntrega(array $value): void
    {
        foreach ((array) ($value['statuses'] ?? []) as $status) {
            $messageId = (string) ($status['id'] ?? '');
            if ($messageId === '') {
                continue;
            }

            $destinatario = ReclutamientoWhatsappDestinatario::where('meta_message_id', $messageId)->first();
            if (!$destinatario) {
                continue;
            }

            $estado = (string) ($status['status'] ?? 'pendiente');
            $ocurridoAt = $this->timestampMeta($status['timestamp'] ?? null);
            $estadoLocal = match ($estado) {
                'sent' => 'enviado',
                'delivered' => 'entregado',
                'read' => 'leido',
                'failed' => 'fallido',
                default => $destinatario->estado,
            };
            $campos = ['estado' => $estadoLocal];
            if ($estado === 'sent') $campos['enviado_at'] = $ocurridoAt;
            if ($estado === 'delivered') $campos['entregado_at'] = $ocurridoAt;
            if ($estado === 'read') $campos['leido_at'] = $ocurridoAt;
            if ($estado === 'failed') $campos['codigo_error'] = (string) data_get($status, 'errors.0.code', 'meta_failed');
            $destinatario->update($campos);

            $mensajeCampos = ['estado' => $estadoLocal];
            if ($estado === 'delivered') $mensajeCampos['entregado_at'] = $ocurridoAt;
            if ($estado === 'read') $mensajeCampos['leido_at'] = $ocurridoAt;
            ReclutamientoWhatsappMensaje::where('meta_message_id', $messageId)->update($mensajeCampos);

            ReclutamientoWhatsappEvento::create([
                'campania_id' => $destinatario->campania_id,
                'contacto_id' => $destinatario->contacto_id,
                'destinatario_id' => $destinatario->id,
                'meta_event_id' => $messageId,
                'tipo' => 'estado_entrega',
                'datos' => ['estado' => $estadoLocal, 'codigo_error' => $campos['codigo_error'] ?? null],
                'ocurrido_at' => $ocurridoAt,
            ]);

            app(ReclutamientoWhatsappCampaignService::class)->actualizarMetricas($destinatario->campania);
        }
    }

    private function timestampMeta(mixed $timestamp): Carbon
    {
        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : now();
    }

    private function esSolicitudDeBaja(string $contenido): bool
    {
        $texto = mb_strtoupper(trim($contenido));
        $texto = strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? '';

        return in_array($texto, ['BAJA', 'STOP', 'SALIR', 'CANCELAR', 'NO CONTACTAR'], true);
    }

    private function registrarBajaPorMensaje(ReclutamientoWhatsappContacto $contacto, Carbon $ocurridoAt): void
    {
        if ($contacto->consentimiento_revocado_at !== null) {
            return;
        }

        $contacto->update([
            'consentimiento_whatsapp' => false,
            'consentimiento_revocado_at' => $ocurridoAt,
            'motivo_revocacion' => 'Solicitud de baja recibida por WhatsApp',
        ]);

        RegistroTratamientoDatos::registrar(
            'whatsapp_baja_recibida',
            'reclutamiento_whatsapp_contactos',
            $contacto->id,
            'personal',
            'Se recibió una instrucción de baja por el canal de WhatsApp.',
            ['consentimiento' => true],
            ['consentimiento' => false, 'origen' => 'whatsapp_entrante']
        );
    }

    /** Store delivery metadata only; the message body is never replicated in event/audit JSON. */
    private function redactarCambio(array $value): array
    {
        return [
            'messaging_product' => $value['messaging_product'] ?? null,
            'metadata' => [
                'phone_number_id' => data_get($value, 'metadata.phone_number_id'),
            ],
            'statuses' => collect($value['statuses'] ?? [])->map(fn (array $status) => [
                'id' => $status['id'] ?? null,
                'status' => $status['status'] ?? null,
                'timestamp' => $status['timestamp'] ?? null,
                'error_codes' => collect($status['errors'] ?? [])->pluck('code')->values()->all(),
            ])->values()->all(),
            'mensajes' => collect($value['messages'] ?? [])->map(fn (array $message) => [
                'id' => $message['id'] ?? null,
                'from' => isset($message['from']) ? 'hash:' . substr(hash('sha256', (string) $message['from']), 0, 12) : null,
                'type' => $message['type'] ?? null,
                'timestamp' => $message['timestamp'] ?? null,
            ])->values()->all(),
        ];
    }
}
