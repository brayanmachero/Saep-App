<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TalanaDocumentApi
{
    private function client(): PendingRequest
    {
        $token = config('services.talana_documents.token');
        if (! $token) {
            throw new RuntimeException('Falta la credencial documental.');
        }

        return Http::baseUrl('https://talana.com/es/api')->withToken($token, 'Token')
            ->withoutRedirecting()->acceptJson()->connectTimeout(8)->timeout(45);
    }

    public function searchDocuments(string $term, int $page, int $companyId): Response
    {
        return $this->client()->get('documentos/', ['empresa' => $companyId, 'search' => $term, 'page' => $page, 'page_size' => 50]);
    }

    public function summarizeDocuments(array $entries, string $order = 'recent'): array
    {
        $documents = [];
        foreach ($entries as $entry) {
            $name = $entry['nombre'] ?? '';
            if (! is_string($name) || $name === '' || mb_strlen($name) > 255) {
                continue;
            }
            $date = null;
            try {
                if (filled($entry['fechaCreacion'] ?? null)) {
                    $date = CarbonImmutable::parse($entry['fechaCreacion'])->setTimezone('America/Santiago');
                }
            } catch (\Throwable) {
                // Unavailable dates remain explicitly empty.
            }
            if (isset($documents[$name]) && ($documents[$name]['timestamp'] ?? 0) >= ($date?->timestamp ?? 0)) {
                continue;
            }
            preg_match('/(?:rv\.?|v)\s*(\d+(?:\.\d+)*)/iu', $name, $version);
            $documents[$name] = ['name' => $name, 'id' => isset($entry['id']) ? (int) $entry['id'] : null, 'timestamp' => $date?->timestamp,
                'date' => $date?->format('d/m/Y H:i'), 'version' => $version[1] ?? null];
        }
        uasort($documents, function ($a, $b) use ($order) {
            if ($order === 'name') {
                return strnatcasecmp($a['name'], $b['name']);
            }
            if ($a['timestamp'] === null || $b['timestamp'] === null) {
                return ($a['timestamp'] === null) <=> ($b['timestamp'] === null);
            }
            $comparison = $a['timestamp'] <=> $b['timestamp'];

            return ($order === 'oldest' ? $comparison : -$comparison) ?: strnatcasecmp($a['name'], $b['name']);
        });

        return $documents;
    }

    public function document(int $id, int $companyId): Response
    {
        return $this->client()->get('documentos/'.$id.'/', ['empresa' => $companyId]);
    }

    public function previewPdf(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! str_starts_with($url, 'https://') || ($host !== 'talana.com' && ! str_ends_with($host, '.talana.com'))) {
            throw new RuntimeException('El adjunto requiere revisión.');
        }
        $response = Http::withoutRedirecting()->connectTimeout(8)->timeout(45)
            ->withOptions(['stream' => true])->get($url)->throw();
        if (! $response->successful()) {
            throw new RuntimeException('El archivo no está disponible.');
        }
        $stream = $response->toPsrResponse()->getBody();
        $pdf = '';
        try {
            while (! $stream->eof()) {
                $pdf .= $stream->read(65536);
                if (strlen($pdf) > 25 * 1024 * 1024) {
                    throw new RuntimeException('El archivo supera el tamaño de la vista previa (25 MB).');
                }
            }
        } finally {
            $stream->close();
        }
        if (! str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('El adjunto no es un PDF disponible para previsualizar.');
        }

        return $pdf;
    }

    public function upload(string $rut, string $name, string $category, string $pdf): Response
    {
        return $this->client()->post('documentos/', [
            'rut' => $rut,
            'nombre' => $name,
            'categoria' => $category,
            'puedeVerloElTrabajador' => false,
            'filename' => 'documento.pdf',
            'filedata_b64' => base64_encode($pdf),
        ]);
    }

    public function requestSignature(int $documentId): Response
    {
        return $this->client()->post('document/requestSignature', [
            'document_id' => $documentId,
            'send_request_to_employee' => true,
        ]);
    }

    public function signatures(int $documentId): Response
    {
        return $this->client()->get('integration-signature-requests-paginated/', [
            'document_id' => $documentId,
            'page_size' => 20,
        ]);
    }
}
