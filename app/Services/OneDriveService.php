<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OneDriveService
{
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $driveUser;
    private string $rootFolder;

    public function __construct()
    {
        $config = config('services.microsoft_graph');
        $this->tenantId     = $config['tenant_id'] ?? '';
        $this->clientId     = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->driveUser    = $config['drive_user'] ?? '';
        $this->rootFolder   = $config['root_folder'] ?? 'Actas Vehiculos';
    }

    /**
     * Verificar que el servicio esté configurado.
     */
    public function isConfigured(): bool
    {
        return $this->tenantId && $this->clientId && $this->clientSecret && $this->driveUser;
    }

    /**
     * Obtener token de acceso via Client Credentials flow (OAuth2).
     */
    private function getAccessToken(): ?string
    {
        $cacheKey = 'msgraph_access_token';

        return Cache::remember($cacheKey, 3000, function () {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'grant_type'    => 'client_credentials',
                ]
            );

            if ($response->failed()) {
                Log::error('OneDrive: Error obteniendo token', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Subir un archivo al OneDrive del usuario configurado.
     *
     * @param string $content     Contenido binario del archivo (p.ej. PDF)
     * @param string $remotePath  Ruta relativa dentro del rootFolder (p.ej. "CGVC-41/Entrega_2026-03-30.pdf")
     * @param string $contentType Tipo MIME del archivo
     * @return bool
     */
    public function uploadFile(string $content, string $remotePath, string $contentType = 'application/pdf'): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('OneDrive: Servicio no configurado, se omite subida');
            return false;
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }

        // Construir ruta completa: rootFolder/remotePath
        $fullPath = $this->rootFolder . '/' . ltrim($remotePath, '/');

        // Sanitizar la ruta (eliminar caracteres no válidos para OneDrive)
        $fullPath = $this->sanitizePath($fullPath);

        $url = "https://graph.microsoft.com/v1.0/users/{$this->driveUser}/drive/root:/{$fullPath}:/content";

        // Para archivos < 4MB se usa PUT simple; para más grandes se necesita upload session
        $fileSize = strlen($content);

        if ($fileSize > 4 * 1024 * 1024) {
            return $this->uploadLargeFile($token, $fullPath, $content, $contentType);
        }

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => $contentType])
            ->withBody($content, $contentType)
            ->put($url);

        if ($response->successful()) {
            Log::info('OneDrive: Archivo subido exitosamente', [
                'path'   => $fullPath,
                'size'   => $fileSize,
                'itemId' => $response->json('id'),
            ]);
            return true;
        }

        Log::error('OneDrive: Error subiendo archivo', [
            'path'   => $fullPath,
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        // Si el token expiró, limpiar cache e intentar una vez más
        if ($response->status() === 401) {
            Cache::forget('msgraph_access_token');
            $newToken = $this->getAccessToken();
            if ($newToken) {
                $retry = Http::withToken($newToken)
                    ->withHeaders(['Content-Type' => $contentType])
                    ->withBody($content, $contentType)
                    ->put($url);
                if ($retry->successful()) {
                    Log::info('OneDrive: Archivo subido en reintento', ['path' => $fullPath]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Upload session para archivos > 4MB.
     */
    private function uploadLargeFile(string $token, string $fullPath, string $content, string $contentType): bool
    {
        $url = "https://graph.microsoft.com/v1.0/users/{$this->driveUser}/drive/root:/{$fullPath}:/createUploadSession";

        $session = Http::withToken($token)->post($url, [
            'item' => ['@microsoft.graph.conflictBehavior' => 'rename'],
        ]);

        if ($session->failed()) {
            Log::error('OneDrive: Error creando upload session', ['body' => $session->body()]);
            return false;
        }

        $uploadUrl = $session->json('uploadUrl');
        $fileSize = strlen($content);
        $chunkSize = 3276800; // 3.125 MB por chunk

        for ($offset = 0; $offset < $fileSize; $offset += $chunkSize) {
            $chunk = substr($content, $offset, $chunkSize);
            $end = min($offset + $chunkSize, $fileSize) - 1;

            $response = Http::withHeaders([
                'Content-Length' => strlen($chunk),
                'Content-Range'  => "bytes {$offset}-{$end}/{$fileSize}",
            ])->withBody($chunk, $contentType)->put($uploadUrl);

            if ($response->failed() && $response->status() !== 202) {
                Log::error('OneDrive: Error en chunk upload', [
                    'offset' => $offset,
                    'status' => $response->status(),
                ]);
                return false;
            }
        }

        Log::info('OneDrive: Archivo grande subido exitosamente', ['path' => $fullPath, 'size' => $fileSize]);
        return true;
    }

    /**
     * Sanitizar ruta para OneDrive (remover caracteres inválidos).
     */
    private function sanitizePath(string $path): string
    {
        // Caracteres no permitidos en OneDrive: " * : < > ? | #  %
        $path = str_replace(['*', ':', '<', '>', '?', '|', '#', '%', '"'], '_', $path);
        // Eliminar dobles slashes
        $path = preg_replace('#/+#', '/', $path);
        return trim($path, '/');
    }
}
