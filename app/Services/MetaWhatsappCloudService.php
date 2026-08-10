<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaWhatsappCloudService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.whatsapp.enabled')
            && filled(config('services.whatsapp.phone_number_id'))
            && filled(config('services.whatsapp.access_token'))
            && filled(config('services.whatsapp.app_secret'))
            && filled(config('services.whatsapp.verify_token'));
    }

    /**
     * Envía exclusivamente plantillas aprobadas. Los mensajes libres y los
     * destinatarios sin consentimiento quedan fuera de esta integración.
     */
    public function sendTemplate(string $to, string $template, string $language = 'es', array $components = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('La integración oficial de WhatsApp no está habilitada.');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => ltrim($to, '+'),
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))
                ->acceptJson()
                ->timeout(20)
                ->post($this->messagesUrl(), $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No fue posible conectar con la API oficial de WhatsApp.', previous: $exception);
        }

        if ($response->failed()) {
            report(new RuntimeException('Meta WhatsApp API rechazó la solicitud: ' . $response->status()));
            throw new RuntimeException('Meta rechazó el envío de la plantilla. Revisa su estado y la configuración de la cuenta.');
        }

        return $response->json();
    }

    /**
     * Mensaje libre para atención humana. El controlador que lo consume debe
     * verificar la ventana de 24 horas iniciada por el contacto; fuera de esa
     * ventana solo corresponde usar una plantilla aprobada.
     */
    public function sendText(string $to, string $body): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('La integración oficial de WhatsApp no está habilitada.');
        }

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))
                ->acceptJson()
                ->timeout(20)
                ->post($this->messagesUrl(), [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($to, '+'),
                    'type' => 'text',
                    'text' => ['preview_url' => false, 'body' => $body],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No fue posible conectar con la API oficial de WhatsApp.', previous: $exception);
        }

        if ($response->failed()) {
            report(new RuntimeException('Meta WhatsApp API rechazó el mensaje de bandeja: ' . $response->status()));
            throw new RuntimeException('Meta rechazó el mensaje. Verifica la ventana de atención y la configuración del canal.');
        }

        return $response->json();
    }

    /**
     * Obtiene el catálogo oficial de plantillas de la cuenta de WhatsApp.
     * Solo se sincronizan metadatos necesarios para validar los despachos.
     */
    public function listTemplates(): array
    {
        if (!$this->isConfigured() || blank(config('services.whatsapp.business_account_id'))) {
            throw new RuntimeException('La cuenta de WhatsApp Business no está configurada para sincronizar plantillas.');
        }

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))
                ->acceptJson()
                ->timeout(20)
                ->get($this->templatesUrl(), [
                    'fields' => 'name,language,status,category,components',
                    'limit' => 250,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No fue posible conectar con Meta para sincronizar plantillas.', previous: $exception);
        }

        if ($response->failed()) {
            report(new RuntimeException('Meta WhatsApp API rechazó la sincronización de plantillas: ' . $response->status()));
            throw new RuntimeException('Meta rechazó la sincronización de plantillas. Revisa el token y los permisos de la cuenta de WhatsApp Business.');
        }

        return (array) $response->json('data', []);
    }

    public function hasValidSignature(string $rawBody, ?string $signature): bool
    {
        $appSecret = (string) config('services.whatsapp.app_secret');
        if ($appSecret === '' || !$signature || !str_starts_with($signature, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $appSecret), $signature);
    }

    private function messagesUrl(): string
    {
        $version = trim((string) config('services.whatsapp.graph_version', 'v23.0'), '/');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        return "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";
    }

    private function templatesUrl(): string
    {
        $version = trim((string) config('services.whatsapp.graph_version', 'v23.0'), '/');
        $businessAccountId = config('services.whatsapp.business_account_id');

        return "https://graph.facebook.com/{$version}/{$businessAccountId}/message_templates";
    }
}
