<?php

namespace App\Services;

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

    public function searchDocuments(string $term, int $page): Response
    {
        return $this->client()->get('documentos/', ['search' => $term, 'page' => $page, 'page_size' => 50]);
    }

    public function summarizeDocuments(array $entries, string $order = 'recent'): array
    {
        $documents = [];
        foreach ($entries as $entry) {
            $name = $entry['nombre'] ?? '';
            if (!is_string($name) || $name === '' || mb_strlen($name) > 255) {
                continue;
            }
            $date = null;
            try {
                if (filled($entry['fechaCreacion'] ?? null)) {
                    $date = \Carbon\CarbonImmutable::parse($entry['fechaCreacion'])->setTimezone('America/Santiago');
                }
            } catch (\Throwable) {
                // Unavailable dates remain explicitly empty.
            }
            if (isset($documents[$name]) && ($documents[$name]['timestamp'] ?? 0) >= ($date?->timestamp ?? 0)) {
                continue;
            }
            preg_match('/(?:rv\.?|v)\s*(\d+(?:\.\d+)*)/iu', $name, $version);
            $documents[$name] = ['name'=>$name, 'timestamp'=>$date?->timestamp,
                'date'=>$date?->format('d/m/Y H:i'), 'version'=>$version[1] ?? null];
        }
        uasort($documents, function ($a, $b) use ($order) {
            if ($order === 'name') {
                return strnatcasecmp($a['name'], $b['name']);
            }
            if ($a['timestamp'] === null || $b['timestamp'] === null) {
                return ($a['timestamp'] === null) <=> ($b['timestamp'] === null);
            }
            $comparison = $a['timestamp'] <=> $b['timestamp'];
            return ($order === 'oldest' ? $comparison : -$comparison) ?: strnatcasecmp($a['name'],$b['name']);
        });
        return $documents;
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
