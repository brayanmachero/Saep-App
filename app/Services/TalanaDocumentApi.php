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
