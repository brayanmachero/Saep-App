<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Interpreta con IA una programación de contenedores pegada en cualquier formato
 * (tabla con columnas distintas, texto de correo) y la devuelve como filas con los
 * campos de SAEP. El resultado siempre pasa por la vista previa: la IA solo propone.
 */
class ContenedoresIaInterpreter
{
    public const CAMPOS = [
        'operacion' => 'cliente u operación (ej. WALMART, MAERSK, DHL)',
        'bodega' => 'bodega, CD o centro donde se descarga',
        'supervisor_nombre' => 'nombre del supervisor o encargado',
        'facturacion_mes' => 'mes de facturación, en palabras (ej. NOVIEMBRE)',
        'fecha' => 'fecha de la descarga en formato dd/mm/aaaa',
        'contenedor' => 'código del contenedor: 4 letras + 7 dígitos (ej. MSBU5332438)',
        'equipo_descarga' => 'equipo o cuadrilla que descarga (ej. SAEP 2)',
        'hora_cita' => 'hora de cita o llegada, hh:mm',
        'hora_inicio_descarga' => 'hora de inicio de la descarga, hh:mm',
        'hora_termino_descarga' => 'hora de término de la descarga, hh:mm',
        'item' => 'cantidad de ítems o SKU (número)',
        'cajas' => 'cantidad de cajas o bultos (número)',
        'pallets' => 'cantidad de pallets (número)',
        'producto' => 'descripción del producto o mercadería',
        'observacion' => 'observación o estado (ej. OK, DESCARGADO)',
        'anio' => 'año (número)',
        'mes' => 'mes de la operación, en palabras',
        'semana' => 'número de semana',
        'dia' => 'día de la semana, en palabras',
        'dato' => 'columna DATO de la planilla, si existe',
        'fact_codigo' => 'código FACT o de tarifa (ej. CNT001)',
    ];

    public function enabled(): bool
    {
        return (bool) $this->apiKey();
    }

    public function provider(): string
    {
        return strtolower((string) config('descarga_contenedores.ia.provider', 'openai'));
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function interpret(string $texto): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('La interpretación con IA no está configurada.');
        }

        $json = $this->provider() === 'gemini'
            ? $this->callGemini($texto)
            : $this->callOpenAi($texto);

        return $this->sanitize($json);
    }

    private function apiKey(): ?string
    {
        $key = $this->provider() === 'gemini'
            ? config('services.gemini.key')
            : config('services.openai.key');

        return $key ?: null;
    }

    private function instrucciones(): string
    {
        $campos = collect(self::CAMPOS)
            ->map(fn ($descripcion, $campo) => "- {$campo}: {$descripcion}")
            ->implode("\n");

        return <<<TXT
Eres un asistente que extrae programaciones de descarga de contenedores para una empresa chilena.
Recibirás texto pegado desde Excel o un correo, en cualquier formato y orden de columnas.
Devuelve SOLO un objeto JSON con la forma {"filas": [ {...}, ... ]}, una fila por contenedor.
Campos permitidos (usa exactamente estos nombres):
{$campos}
Reglas:
- No inventes datos: si un campo no aparece, déjalo como cadena vacía "".
- Copia los valores tal como vienen (sin traducir nombres ni corregir códigos), salvo fecha (dd/mm/aaaa) y horas (hh:mm).
- Las fechas vienen en formato chileno (día/mes/año).
- Ignora filas de totales, encabezados repetidos y saludos o firmas de correo.
- Todos los valores deben ser cadenas de texto.
TXT;
    }

    private function callOpenAi(string $texto): array
    {
        $response = Http::withToken($this->apiKey())
            ->timeout((int) config('descarga_contenedores.ia.timeout', 45))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('descarga_contenedores.ia.model') ?: 'gpt-4o-mini',
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->instrucciones()],
                    ['role' => 'user', 'content' => $texto],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Contenedores IA (OpenAI) respondió con error', ['status' => $response->status()]);
            throw new RuntimeException('El servicio de IA no respondió correctamente.');
        }

        return $this->decode((string) $response->json('choices.0.message.content'));
    }

    private function callGemini(string $texto): array
    {
        $model = config('descarga_contenedores.ia.model') ?: 'gemini-2.5-flash';

        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey()])
            ->timeout((int) config('descarga_contenedores.ia.timeout', 45))
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => $this->instrucciones()]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $texto]]]],
                'generationConfig' => [
                    'temperature' => 0,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Contenedores IA (Gemini) respondió con error', ['status' => $response->status()]);
            throw new RuntimeException('El servicio de IA no respondió correctamente.');
        }

        return $this->decode((string) $response->json('candidates.0.content.parts.0.text'));
    }

    private function decode(string $content): array
    {
        $content = trim(preg_replace('/^```(?:json)?|```$/m', '', $content));
        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw new RuntimeException('La IA no devolvió un resultado legible.');
        }

        return $data;
    }

    /**
     * Solo campos conocidos, como texto, recortados y con un máximo de 200 filas.
     *
     * @return array<int, array<string, string>>
     */
    public function sanitize(array $data): array
    {
        $filas = $data['filas'] ?? (array_is_list($data) ? $data : []);

        return collect($filas)
            ->filter(fn ($fila) => is_array($fila))
            ->map(function (array $fila) {
                $limpia = [];
                foreach (array_keys(self::CAMPOS) as $campo) {
                    $valor = $fila[$campo] ?? '';
                    $valor = is_scalar($valor) ? trim((string) $valor) : '';
                    $limpia[$campo] = mb_substr($valor, 0, 260);
                }

                return $limpia;
            })
            ->filter(fn (array $fila) => collect($fila)->contains(fn ($v) => $v !== ''))
            ->take(200)
            ->values()
            ->all();
    }
}
