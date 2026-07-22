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
    private ?array $lastError = null;

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

    public function getLastError(): ?array
    {
        return $this->lastError;
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
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->recordUploadError('SharePoint: Servicio no configurado, se omite subida');
            return false;
        }

        $token = $this->getAccessToken();
        if (!$token) {
            $this->recordUploadError('SharePoint: No se pudo obtener token de acceso');
            return false;
        }

        $siteId = $this->getSiteId();
        if (!$siteId) {
            $this->recordUploadError('SharePoint: No se pudo resolver Site ID');
            return false;
        }

        // Construir ruta completa: rootFolder/remotePath (o absoluta si se indica)
        $fullPath = $absolute ? ltrim($remotePath, '/') : $this->rootFolder . '/' . ltrim($remotePath, '/');
        $fullPath = $this->sanitizePath($fullPath);

        return $this->uploadToSiteId($token, $siteId, $fullPath, $content, $contentType, 'SharePoint');
    }

    /**
     * Upload session para archivos > 4MB.
     */
    private function uploadLargeFile(string $token, string $siteId, string $fullPath, string $content, string $contentType): bool
    {
        $url = $this->driveRootUrl($siteId, $fullPath, ':/createUploadSession');

        $session = Http::withToken($token)->post($url, [
            'item' => ['@microsoft.graph.conflictBehavior' => 'replace'],
        ]);

        if ($session->failed()) {
            $this->recordUploadError('SharePoint: Error creando upload session', [
                'path'   => $fullPath,
                'status' => $session->status(),
                'body'   => $session->body(),
            ]);
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
                $this->recordUploadError('SharePoint: Error en chunk upload', [
                    'path'   => $fullPath,
                    'offset' => $offset,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }
        }

        Log::info('SharePoint: Archivo grande subido exitosamente', ['path' => $fullPath, 'size' => $fileSize]);
        return true;
    }

    /**
     * Obtener el Site ID de un sitio SharePoint específico (cacheado por nombre de sitio).
     */
    private function getSiteIdForSite(string $siteName): ?string
    {
        $cacheKey = 'msgraph_sharepoint_site_id_' . $siteName;

        return Cache::rememberForever($cacheKey, function () use ($siteName) {
            $token = $this->getAccessToken();
            if (!$token) {
                return null;
            }

            $url = "https://graph.microsoft.com/v1.0/sites/{$this->sharepointHost}:/sites/{$siteName}";
            $response = Http::withToken($token)->get($url);

            if ($response->failed()) {
                Log::error('SharePoint: Error obteniendo Site ID para ' . $siteName, [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $siteId = $response->json('id');
            Log::info('SharePoint: Site ID obtenido para ' . $siteName, ['siteId' => $siteId]);
            return $siteId;
        });
    }

    /**
     * Subir archivo a un sitio SharePoint específico (distinto al configurado por defecto).
     * La ruta es relativa a la raíz del drive del sitio.
     */
    public function uploadFileToSite(string $site, string $content, string $remotePath, string $contentType = 'application/pdf'): bool
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->recordUploadError('SharePoint: Servicio no configurado, se omite subida');
            return false;
        }

        $token = $this->getAccessToken();
        if (!$token) {
            $this->recordUploadError('SharePoint: No se pudo obtener token de acceso');
            return false;
        }

        $siteId = $this->getSiteIdForSite($site);
        if (!$siteId) {
            $this->recordUploadError('SharePoint: No se pudo resolver Site ID para ' . $site);
            return false;
        }

        $fullPath = $this->sanitizePath($remotePath);
        return $this->uploadToSiteId($token, $siteId, $fullPath, $content, $contentType, 'SharePoint ' . $site);
    }

    /**
     * Obtener URL web de un archivo o carpeta en un sitio SharePoint especifico.
     */
    public function getItemWebUrlForSite(string $site, string $remotePath): ?string
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            return null;
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $siteId = $this->getSiteIdForSite($site);
        if (!$siteId) {
            return null;
        }

        $fullPath = $this->sanitizePath($remotePath);
        $response = Http::withToken($token)->get($this->driveRootUrl($siteId, $fullPath));

        if ($response->successful()) {
            return $response->json('webUrl');
        }

        if ($response->status() !== 404) {
            $this->recordUploadError('SharePoint: Error obteniendo URL web', [
                'path' => $fullPath,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return null;
    }

    /**
     * Sanitizar ruta para SharePoint (remover caracteres inválidos).
     */
    private function sanitizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $path) ?? $path;
        $path = str_replace(['*', ':', '<', '>', '?', '|', '#', '%', '"', '{', '}', '~', '&'], '_', $path);
        $path = preg_replace('#/+#', '/', $path);

        $segments = array_map(function (string $segment) {
            $segment = preg_replace('/\s+/u', ' ', $segment) ?? $segment;
            $segment = trim($segment, " \t\n\r\0\x0B.");
            return $segment !== '' ? $segment : 'Sin especificar';
        }, explode('/', $path));

        return trim(implode('/', $segments), '/');
    }

    private function uploadToSiteId(string $token, string $siteId, string $fullPath, string $content, string $contentType, string $context): bool
    {
        $fileSize = strlen($content);

        if ($fileSize > 4 * 1024 * 1024) {
            return $this->uploadLargeFile($token, $siteId, $fullPath, $content, $contentType);
        }

        $url = $this->driveRootUrl($siteId, $fullPath, ':/content?@microsoft.graph.conflictBehavior=replace');
        $response = $this->putSmallFile($token, $url, $content, $contentType);

        if ($response->successful()) {
            Log::info($context . ': Archivo subido exitosamente', [
                'path'   => $fullPath,
                'size'   => $fileSize,
                'itemId' => $response->json('id'),
            ]);
            return true;
        }

        if ($response->status() === 401) {
            Cache::forget('msgraph_access_token');
            $token = $this->getAccessToken();
            if ($token) {
                $response = $this->putSmallFile($token, $url, $content, $contentType);
                if ($response->successful()) {
                    Log::info($context . ': Archivo subido en reintento', ['path' => $fullPath]);
                    return true;
                }
            }
        }

        if ($response->status() === 409 && $this->isNameAlreadyExists($response)) {
            usleep(500000);
            $retry = $this->putSmallFile($token, $url, $content, $contentType);
            if ($retry->successful()) {
                Log::info($context . ': Archivo subido tras conflicto temporal', ['path' => $fullPath]);
                return true;
            }

            if ($this->fileExists($token, $siteId, $fullPath)) {
                Log::warning($context . ': Archivo ya existía, se trata como subida idempotente', ['path' => $fullPath]);
                return true;
            }

            $response = $retry;
        }

        $this->recordUploadError($context . ': Error subiendo archivo', [
            'path'   => $fullPath,
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return false;
    }

    private function putSmallFile(string $token, string $url, string $content, string $contentType)
    {
        return Http::withToken($token)
            ->withHeaders(['Content-Type' => $contentType])
            ->withBody($content, $contentType)
            ->put($url);
    }

    private function fileExists(string $token, string $siteId, string $fullPath): bool
    {
        return Http::withToken($token)
            ->get($this->driveRootUrl($siteId, $fullPath))
            ->successful();
    }

    private function isNameAlreadyExists($response): bool
    {
        return ($response->json('error.code') === 'nameAlreadyExists')
            || str_contains($response->body(), 'nameAlreadyExists');
    }

    private function driveRootUrl(string $siteId, string $fullPath, string $suffix = ''): string
    {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $fullPath)));
        return "https://graph.microsoft.com/v1.0/sites/{$siteId}/drive/root:/{$encodedPath}{$suffix}";
    }

    private function recordUploadError(string $message, array $context = []): void
    {
        $this->lastError = ['message' => $message] + $context;
        Log::error($message, $context);
    }
}
