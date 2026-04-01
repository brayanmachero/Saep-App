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
    private string $sharepointHost;
    private string $sharepointSite;
    private string $rootFolder;

    public function __construct()
    {
        $config = config('services.microsoft_graph');
        $this->tenantId       = $config['tenant_id'] ?? '';
        $this->clientId       = $config['client_id'] ?? '';
        $this->clientSecret   = $config['client_secret'] ?? '';
        $this->sharepointHost = $config['sharepoint_host'] ?? '';
        $this->sharepointSite = $config['sharepoint_site'] ?? '';
        $this->rootFolder     = $config['root_folder'] ?? 'Actas Vehiculos';
    }

    /**
     * Verificar que el servicio esté configurado.
     */
    public function isConfigured(): bool
    {
        return $this->tenantId && $this->clientId && $this->clientSecret
            && $this->sharepointHost && $this->sharepointSite;
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
                Log::error('SharePoint: Error obteniendo token', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Obtener el Site ID de SharePoint (se cachea indefinidamente).
     */
    private function getSiteId(): ?string
    {
        $cacheKey = 'msgraph_sharepoint_site_id';

        return Cache::rememberForever($cacheKey, function () {
            $token = $this->getAccessToken();
            if (!$token) {
                return null;
            }

            // GET /sites/{hostname}:/sites/{sitePath}
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->sharepointHost}:/sites/{$this->sharepointSite}";

            $response = Http::withToken($token)->get($url);

            if ($response->failed()) {
                Log::error('SharePoint: Error obteniendo Site ID', [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $siteId = $response->json('id');
            Log::info('SharePoint: Site ID obtenido', ['siteId' => $siteId]);
            return $siteId;
        });
    }

    /**
     * Subir un archivo al SharePoint del sitio configurado.
     *
     * @param string $content     Contenido binario del archivo (p.ej. PDF)
     * @param string $remotePath  Ruta relativa dentro del rootFolder (p.ej. "CGVC-41/Entrega_2026-03-30.pdf")
     * @param string $contentType Tipo MIME del archivo
     * @param bool   $absolute    Si true, remotePath es ruta absoluta desde la raíz del drive (no antepone rootFolder)
     * @return bool
     */
    public function uploadFile(string $content, string $remotePath, string $contentType = 'application/pdf', bool $absolute = false): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('SharePoint: Servicio no configurado, se omite subida');
            return false;
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }

        $siteId = $this->getSiteId();
        if (!$siteId) {
            return false;
        }

        // Construir ruta completa: rootFolder/remotePath (o absoluta si se indica)
        $fullPath = $absolute ? ltrim($remotePath, '/') : $this->rootFolder . '/' . ltrim($remotePath, '/');
        $fullPath = $this->sanitizePath($fullPath);

        // Endpoint SharePoint: /sites/{siteId}/drive/root:/{path}:/content
        $url = "https://graph.microsoft.com/v1.0/sites/{$siteId}/drive/root:/{$fullPath}:/content";

        $fileSize = strlen($content);

        if ($fileSize > 4 * 1024 * 1024) {
            return $this->uploadLargeFile($token, $siteId, $fullPath, $content, $contentType);
        }

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => $contentType])
            ->withBody($content, $contentType)
            ->put($url);

        if ($response->successful()) {
            Log::info('SharePoint: Archivo subido exitosamente', [
                'path'   => $fullPath,
                'size'   => $fileSize,
                'itemId' => $response->json('id'),
            ]);
            return true;
        }

        Log::error('SharePoint: Error subiendo archivo', [
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
                    Log::info('SharePoint: Archivo subido en reintento', ['path' => $fullPath]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Upload session para archivos > 4MB.
     */
    private function uploadLargeFile(string $token, string $siteId, string $fullPath, string $content, string $contentType): bool
    {
        $url = "https://graph.microsoft.com/v1.0/sites/{$siteId}/drive/root:/{$fullPath}:/createUploadSession";

        $session = Http::withToken($token)->post($url, [
            'item' => ['@microsoft.graph.conflictBehavior' => 'rename'],
        ]);

        if ($session->failed()) {
            Log::error('SharePoint: Error creando upload session', ['body' => $session->body()]);
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
                Log::error('SharePoint: Error en chunk upload', [
                    'offset' => $offset,
                    'status' => $response->status(),
                ]);
                return false;
            }
        }

        Log::info('SharePoint: Archivo grande subido exitosamente', ['path' => $fullPath, 'size' => $fileSize]);
        return true;
    }

    /**
     * Sanitizar ruta para SharePoint (remover caracteres inválidos).
     */
    private function sanitizePath(string $path): string
    {
        $path = str_replace(['*', ':', '<', '>', '?', '|', '#', '%', '"'], '_', $path);
        $path = preg_replace('#/+#', '/', $path);
        return trim($path, '/');
    }
}
