<?php

namespace App\Services;

use App\Models\ReclutamientoWhatsappCampania;
use App\Models\ReclutamientoWhatsappContacto;
use App\Models\ReclutamientoWhatsappConversacion;
use App\Models\ReclutamientoWhatsappDestinatario;
use App\Models\ReclutamientoWhatsappEvento;
use App\Models\ReclutamientoWhatsappMensaje;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReclutamientoWhatsappCampaignService
{
    public function __construct(private readonly MetaWhatsappCloudService $whatsapp)
    {
    }

    public function isConfigured(): bool
    {
        return $this->whatsapp->isConfigured();
    }

    public function plantillaEsEstatica(?array $componentes): bool
    {
        return !$this->plantillaTieneVariables($componentes);
    }

    /**
     * Congela la audiencia elegible cuando la campaña queda programada. Cada
     * destinatario se valida nuevamente justo antes de despacharlo.
     */
    public function prepararDestinatarios(ReclutamientoWhatsappCampania $campania): int
    {
        $contactos = ReclutamientoWhatsappContacto::elegiblesParaCampanias($campania->finalidad)
            ->select('id')
            ->orderBy('id')
            ->pluck('id');

        foreach ($contactos->chunk(250) as $ids) {
            $ahora = now();
            $rows = $ids->map(fn (int $contactoId) => [
                'campania_id' => $campania->id,
                'contacto_id' => $contactoId,
                'estado' => 'pendiente',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])->all();

            DB::table('reclutamiento_whatsapp_destinatarios')->upsert(
                $rows,
                ['campania_id', 'contacto_id'],
                ['updated_at']
            );
        }

        $cantidad = $contactos->count();
        $campania->update(['destinatarios_estimados' => $cantidad]);

        return $cantidad;
    }

    /**
     * Despacha solo una cantidad acotada por ejecución. El comando agendado
     * retoma la campaña en el siguiente minuto si quedan destinatarios.
     */
    public function despacharCampania(ReclutamientoWhatsappCampania $campania, int $limit = 50): array
    {
        if (!$this->whatsapp->isConfigured()) {
            throw new RuntimeException('La integración oficial de WhatsApp no está habilitada.');
        }

        $campania = DB::transaction(function () use ($campania) {
            $bloqueada = ReclutamientoWhatsappCampania::query()->lockForUpdate()->findOrFail($campania->id);

            if ($bloqueada->estado !== 'programada' || !$bloqueada->programada_para || $bloqueada->programada_para->isFuture()) {
                return null;
            }

            $bloqueada->update(['estado' => 'enviando']);

            return $bloqueada->fresh(['plantilla']);
        });

        if (!$campania) {
            return ['procesados' => 0, 'enviados' => 0, 'fallidos' => 0, 'omitidos' => 0, 'estado' => 'omitida'];
        }

        $resultado = ['procesados' => 0, 'enviados' => 0, 'fallidos' => 0, 'omitidos' => 0];
        $destinatarios = ReclutamientoWhatsappDestinatario::query()
            ->where('campania_id', $campania->id)
            ->where('estado', 'pendiente')
            ->with('contacto')
            ->orderBy('id')
            ->limit(max(1, min($limit, 100)))
            ->get();

        foreach ($destinatarios as $destinatario) {
            ++$resultado['procesados'];
            $contacto = $destinatario->contacto;

            if (!$contacto || !$contacto->puedeRecibirCampanias($campania->finalidad)) {
                $this->omitirDestinatario($destinatario, 'El contacto ya no tiene consentimiento vigente para esta finalidad.');
                ++$resultado['omitidos'];
                continue;
            }

            if (!$campania->plantilla || $this->plantillaTieneVariables($campania->plantilla->componentes)) {
                $this->omitirDestinatario($destinatario, 'La plantilla no está sincronizada o requiere variables que aún no están configuradas.');
                ++$resultado['omitidos'];
                continue;
            }

            try {
                $respuesta = $this->whatsapp->sendTemplate(
                    $contacto->telefono,
                    $campania->plantilla_nombre,
                    $campania->plantilla_idioma
                );
                $metaMessageId = (string) data_get($respuesta, 'messages.0.id');

                if ($metaMessageId === '') {
                    throw new RuntimeException('Meta no devolvió el identificador del mensaje.');
                }

                $ocurridoAt = now();
                $destinatario->update([
                    'estado' => 'enviado',
                    'meta_message_id' => $metaMessageId,
                    'enviado_at' => $ocurridoAt,
                    'codigo_error' => null,
                ]);

                $conversacion = ReclutamientoWhatsappConversacion::firstOrCreate(
                    ['contacto_id' => $contacto->id],
                    [
                        'campania_origen_id' => $campania->id,
                        'estado' => 'esperando_respuesta',
                        'iniciada_at' => $ocurridoAt,
                    ]
                );
                $conversacion->update([
                    'campania_origen_id' => $conversacion->campania_origen_id ?? $campania->id,
                    'estado' => 'esperando_respuesta',
                    'ultimo_mensaje_preview' => "Plantilla enviada: {$campania->plantilla_nombre}",
                    'ultimo_mensaje_at' => $ocurridoAt,
                ]);
                ReclutamientoWhatsappMensaje::firstOrCreate(
                    ['meta_message_id' => $metaMessageId],
                    [
                        'conversacion_id' => $conversacion->id,
                        'direccion' => 'saliente',
                        'tipo' => 'plantilla',
                        'contenido' => "Plantilla enviada: {$campania->plantilla_nombre}",
                        'estado' => 'enviado',
                        'ocurrido_at' => $ocurridoAt,
                    ]
                );
                ReclutamientoWhatsappEvento::create([
                    'campania_id' => $campania->id,
                    'contacto_id' => $contacto->id,
                    'destinatario_id' => $destinatario->id,
                    'meta_event_id' => $metaMessageId,
                    'tipo' => 'campania_enviada',
                    'datos' => ['plantilla' => $campania->plantilla_nombre],
                    'ocurrido_at' => $ocurridoAt,
                ]);
                ++$resultado['enviados'];
            } catch (RuntimeException $exception) {
                $destinatario->update([
                    'estado' => 'fallido',
                    'codigo_error' => Str::limit($exception->getMessage(), 100, ''),
                ]);
                ReclutamientoWhatsappEvento::create([
                    'campania_id' => $campania->id,
                    'contacto_id' => $contacto->id,
                    'destinatario_id' => $destinatario->id,
                    'tipo' => 'campania_fallida',
                    'datos' => ['motivo' => Str::limit($exception->getMessage(), 180, '')],
                    'ocurrido_at' => now(),
                ]);
                ++$resultado['fallidos'];
            }
        }

        $this->actualizarMetricas($campania);
        $pendientes = ReclutamientoWhatsappDestinatario::query()
            ->where('campania_id', $campania->id)
            ->where('estado', 'pendiente')
            ->exists();
        $campania->update(['estado' => $pendientes ? 'programada' : 'completada']);

        return $resultado + ['estado' => $pendientes ? 'programada' : 'completada'];
    }

    public function actualizarMetricas(ReclutamientoWhatsappCampania $campania): void
    {
        $base = ReclutamientoWhatsappDestinatario::query()->where('campania_id', $campania->id);

        $campania->update([
            'enviados' => (clone $base)->whereIn('estado', ['enviado', 'entregado', 'leido'])->count(),
            'entregados' => (clone $base)->whereIn('estado', ['entregado', 'leido'])->count(),
            'leidos' => (clone $base)->where('estado', 'leido')->count(),
            'fallidos' => (clone $base)->where('estado', 'fallido')->count(),
        ]);
    }

    private function omitirDestinatario(ReclutamientoWhatsappDestinatario $destinatario, string $motivo): void
    {
        $destinatario->update([
            'estado' => 'omitido',
            'codigo_error' => Str::limit($motivo, 100, ''),
        ]);
    }

    private function plantillaTieneVariables(?array $componentes): bool
    {
        if (!$componentes) {
            return true;
        }

        foreach ($componentes as $componente) {
            if (preg_match('/\{\{\s*\d+\s*\}\}/', (string) ($componente['text'] ?? ''))) {
                return true;
            }
        }

        return false;
    }
}
