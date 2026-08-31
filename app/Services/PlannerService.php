<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PlannerService
{
    private const GRAPH_BASE_URL = 'https://graph.microsoft.com/v1.0';

    private string $tenantId;

    private string $clientId;

    private string $clientSecret;

    private string $planId;

    public function __construct()
    {
        $graph = config('services.microsoft_graph', []);

        $this->tenantId = (string) ($graph['tenant_id'] ?? '');
        $this->clientId = (string) ($graph['client_id'] ?? '');
        $this->clientSecret = (string) ($graph['client_secret'] ?? '');
        $this->planId = trim((string) config('planner.plan_id', ''));
    }

    public function isConfigured(): bool
    {
        return $this->tenantId !== ''
            && $this->clientId !== ''
            && $this->clientSecret !== ''
            && $this->planId !== '';
    }

    public function planId(): string
    {
        if ($this->planId === '') {
            throw new RuntimeException('Microsoft Planner no está configurado. Falta MSGRAPH_PLANNER_PLAN_ID.');
        }

        return $this->planId;
    }

    /** @return array<string, mixed> */
    public function plan(): array
    {
        return $this->get('/planner/plans/'.rawurlencode($this->planId()));
    }

    /** @return array<int, array<string, mixed>> */
    public function buckets(): array
    {
        return $this->getCollection('/planner/plans/'.rawurlencode($this->planId()).'/buckets');
    }

    /** @return array<int, array<string, mixed>> */
    public function tasks(): array
    {
        return $this->getCollection('/planner/plans/'.rawurlencode($this->planId()).'/tasks');
    }

    /** @return array<string, mixed> */
    public function createBucket(string $name): array
    {
        return $this->post('/planner/buckets', [
            'name' => $name,
            'planId' => $this->planId(),
        ]);
    }

    /** @param array<string, mixed> $bucket */
    public function renameBucket(array $bucket, string $name): void
    {
        $bucketId = (string) ($bucket['id'] ?? '');
        $etag = $this->etag($bucket);

        if ($bucketId === '' || $etag === '') {
            throw new RuntimeException('No fue posible renombrar la columna inicial de Planner.');
        }

        $this->patch('/planner/buckets/'.rawurlencode($bucketId), ['name' => $name], $etag);
    }

    /**
     * @return array<string, mixed>
     */
    public function createTask(
        string $bucketId,
        string $title,
        ?string $description = null,
        bool $completed = false,
        ?string $startDateTime = null,
        ?string $dueDateTime = null,
    ): array {
        $payload = [
            'planId' => $this->planId(),
            'bucketId' => $bucketId,
            'title' => $title,
        ];

        if ($startDateTime !== null) {
            $payload['startDateTime'] = $startDateTime;
        }

        if ($dueDateTime !== null) {
            $payload['dueDateTime'] = $dueDateTime;
        }

        $task = $this->post('/planner/tasks', $payload);

        $taskId = (string) ($task['id'] ?? '');
        if ($taskId === '') {
            throw new RuntimeException('Planner no devolvió un identificador al crear una tarea.');
        }

        if ($description !== null && trim($description) !== '') {
            $this->updateTaskDetails($taskId, $description);
        }

        if ($completed) {
            $etag = $this->etag($task);
            if ($etag === '') {
                $etag = $this->etag($this->get('/planner/tasks/'.rawurlencode($taskId)));
            }

            if ($etag === '') {
                throw new RuntimeException("No fue posible marcar como completada la tarea {$taskId} en Planner.");
            }

            $this->patch('/planner/tasks/'.rawurlencode($taskId), ['percentComplete' => 100], $etag);
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>  $task
     * @param  array<string, mixed>  $changes
     */
    public function updateTask(array $task, array $changes): void
    {
        $taskId = (string) ($task['id'] ?? '');
        $etag = $this->etag($task);

        if ($taskId === '' || $etag === '') {
            throw new RuntimeException('No fue posible actualizar una tarea de Planner.');
        }

        $this->patch('/planner/tasks/'.rawurlencode($taskId), $changes, $etag);
    }

    private function updateTaskDetails(string $taskId, string $description): void
    {
        $details = $this->get('/planner/tasks/'.rawurlencode($taskId).'/details');
        $etag = $this->etag($details);

        if ($etag === '') {
            throw new RuntimeException("No fue posible actualizar el detalle de la tarea {$taskId} en Planner.");
        }

        $this->patch('/planner/tasks/'.rawurlencode($taskId).'/details', ['description' => $description], $etag);
    }

    private function accessToken(): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Microsoft Planner no está configurado.');
        }

        return Cache::remember('msgraph_planner_access_token', 3000, function (): string {
            $response = Http::asForm()
                ->timeout(20)
                ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->failed() || ! $response->json('access_token')) {
                Log::error('Planner: no fue posible obtener un token de Microsoft Graph.', [
                    'status' => $response->status(),
                ]);

                throw new RuntimeException('No fue posible autenticar la integración con Microsoft Planner.');
            }

            return (string) $response->json('access_token');
        });
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        $response = $this->request()->get(self::GRAPH_BASE_URL.$path);

        return $this->jsonOrFail($response, 'consultar Planner');
    }

    /** @return array<int, array<string, mixed>> */
    private function getCollection(string $path): array
    {
        $data = $this->get($path);

        return array_values($data['value'] ?? []);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = $this->request()->post(self::GRAPH_BASE_URL.$path, $payload);

        return $this->jsonOrFail($response, 'crear en Planner');
    }

    /** @param array<string, mixed> $payload */
    private function patch(string $path, array $payload, string $etag): void
    {
        $response = $this->request()
            ->withHeaders(['If-Match' => $etag])
            ->patch(self::GRAPH_BASE_URL.$path, $payload);

        if ($response->failed()) {
            $this->throwGraphError($response, 'actualizar Planner');
        }
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(30);
    }

    /** @return array<string, mixed> */
    private function jsonOrFail(Response $response, string $operation): array
    {
        if ($response->failed()) {
            $this->throwGraphError($response, $operation);
        }

        return $response->json() ?: [];
    }

    private function throwGraphError(Response $response, string $operation): never
    {
        $message = (string) ($response->json('error.message') ?? 'Respuesta no disponible');

        Log::error("Planner: error al {$operation}.", [
            'status' => $response->status(),
            'message' => $message,
        ]);

        throw new RuntimeException("No fue posible {$operation} en Microsoft Planner (HTTP {$response->status()}).");
    }

    /** @param array<string, mixed> $resource */
    private function etag(array $resource): string
    {
        return (string) ($resource['@odata.etag'] ?? $resource['etag'] ?? '');
    }
}
