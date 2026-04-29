<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Laravel mail transport via Microsoft Graph API (sendMail).
 *
 * Requisitos en Azure AD:
 *   - Permiso de aplicación: Mail.Send (Microsoft Graph)
 *   - Consentimiento de administrador concedido
 *   - MAIL_FROM_ADDRESS debe ser un buzón válido de Exchange Online en el tenant
 */
class GraphTransport extends AbstractTransport
{
    public function __construct(
        private string $tenantId,
        private string $clientId,
        private string $clientSecret,
        private string $fromEmail,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        $token = $this->getAccessToken();

        // Serializar el mensaje MIME completo (headers + body + adjuntos)
        // Graph API acepta MIME raw con Content-Type: text/plain
        $rawMime = $message->getOriginalMessage()->toString();

        $response = Http::withToken($token)
            ->withBody($rawMime, 'text/plain')
            ->post("https://graph.microsoft.com/v1.0/users/{$this->fromEmail}/sendMail");

        if (!$response->successful()) {
            throw new TransportException(
                'Microsoft Graph sendMail failed (' . $response->status() . '): ' . $response->body()
            );
        }
    }

    private function getAccessToken(): string
    {
        // Token cacheado ~58 min (expira a los 60)
        return Cache::remember('msgraph_mail_token', 3500, function () {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                ]
            );

            if (!$response->successful()) {
                throw new TransportException(
                    'Failed to acquire Microsoft Graph token: ' . $response->body()
                );
            }

            return $response->json('access_token');
        });
    }

    public function __toString(): string
    {
        return 'graph';
    }
}
