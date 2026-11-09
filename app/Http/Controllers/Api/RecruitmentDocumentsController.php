<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PostulanteContratacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class RecruitmentDocumentsController extends Controller
{
    private const FIELDS = [
        'carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa',
        'licencia_conducir_frontal', 'licencia_conducir_reverso',
    ];

    public function index(Request $request)
    {
        $this->authorizeService($request);
        $rut = $this->rut($request->header('X-SAEP-RUT'));
        $expedientes = PostulanteContratacion::query()
            ->where('rut', $rut)
            ->orderByDesc('es_vigente')
            ->latest('id')
            ->get();

        return response()->json([
            'expedientes' => $expedientes->map(fn (PostulanteContratacion $expediente) => [
                'id' => $expediente->id,
                'folio' => $expediente->folio,
                'estado' => $expediente->estado_label,
                'vigente' => $expediente->es_vigente,
                'fecha' => $expediente->created_at?->toIso8601String(),
                'documentos' => collect($expediente->documentosSubidos())
                    ->map(fn (array $doc) => ['campo' => $doc['campo'], 'nombre' => $doc['label']])
                    ->values(),
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, PostulanteContratacion $postulante, string $campo)
    {
        $this->authorizeService($request);
        $rut = $this->rut($request->header('X-SAEP-RUT'));
        abort_unless($postulante->rut === $rut && in_array($campo, self::FIELDS, true), 404);
        $path = $postulante->$campo;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $stream = Storage::disk('local')->readStream($path);
        abort_unless($stream !== false, 404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = $postulante->folio . '_' . $campo . '.' . $extension;

        return response()->streamDownload(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeService(Request $request): void
    {
        $expected = trim((string) config('services.recruitment_documents.token'));
        abort_if($expected === '', Response::HTTP_SERVICE_UNAVAILABLE);
        $provided = $request->bearerToken();
        abort_unless(is_string($provided) && hash_equals($expected, $provided), Response::HTTP_UNAUTHORIZED);
    }

    private function rut(mixed $value): string
    {
        abort_unless(is_string($value) && PostulanteContratacion::validarRut($value), Response::HTTP_UNPROCESSABLE_ENTITY);

        return PostulanteContratacion::formatearRut($value);
    }
}
