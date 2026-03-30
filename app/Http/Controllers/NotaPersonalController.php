<?php

namespace App\Http\Controllers;

use App\Models\NotaPersonal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotaPersonalController extends Controller
{
    /**
     * Listado de notas con filtros.
     */
    public function index(Request $request)
    {
        $userId = auth()->id();

        $query = NotaPersonal::delUsuario($userId)->latest();

        // Filtro por categoría
        if ($request->filled('categoria') && $request->categoria !== 'Todas') {
            $query->categoria($request->categoria);
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            if ($request->estado === 'pendiente') {
                $query->pendientes();
            } elseif ($request->estado === 'completada') {
                $query->completadas();
            }
        }

        // Filtro por mes/año
        if ($request->filled('mes')) {
            [$anio, $mes] = explode('-', $request->mes);
            $query->delMes((int)$mes, (int)$anio);
        }

        // Búsqueda por texto
        if ($request->filled('buscar')) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $request->buscar);
            $query->where('contenido', 'like', '%' . $escaped . '%');
        }

        $notas = $query->paginate(20)->withQueryString();

        // Stats rápidas
        $stats = [
            'total'     => NotaPersonal::delUsuario($userId)->count(),
            'pendientes'=> NotaPersonal::delUsuario($userId)->pendientes()->count(),
            'hoy'       => NotaPersonal::delUsuario($userId)
                            ->whereDate('fecha_recordatorio', today())->pendientes()->count(),
            'categorias'=> NotaPersonal::delUsuario($userId)
                            ->selectRaw('categoria, count(*) as total')
                            ->groupBy('categoria')
                            ->pluck('total', 'categoria'),
        ];

        return view('notas.index', compact('notas', 'stats'));
    }

    /**
     * Guardar nueva nota (AJAX o form).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contenido'          => 'required|string|max:5000',
            'categoria'          => 'nullable|string|max:50',
            'fecha_recordatorio' => 'nullable|date',
            'origen'             => 'nullable|in:texto,voz',
        ]);

        // Si no viene categoría, intentar clasificar con IA
        if (empty($validated['categoria']) || $validated['categoria'] === 'auto') {
            $validated['categoria'] = $this->clasificarConIA($validated['contenido']);
        }

        $nota = NotaPersonal::create([
            'user_id'            => auth()->id(),
            'contenido'          => $validated['contenido'],
            'categoria'          => $validated['categoria'] ?? 'General',
            'fecha_recordatorio' => $validated['fecha_recordatorio'] ?? $this->extraerFecha($validated['contenido']),
            'origen'             => $validated['origen'] ?? 'texto',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'nota'     => $nota->fresh(),
                'message'  => 'Nota guardada',
            ]);
        }

        return redirect()->route('notas.index')->with('success', 'Nota guardada correctamente');
    }

    /**
     * Actualizar nota existente.
     */
    public function update(Request $request, NotaPersonal $nota)
    {
        if ($nota->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'contenido'          => 'sometimes|string|max:5000',
            'categoria'          => 'nullable|string|max:50',
            'fecha_recordatorio' => 'nullable|date',
            'completada'         => 'nullable|boolean',
        ]);

        $nota->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'nota' => $nota->fresh()]);
        }

        return redirect()->route('notas.index')->with('success', 'Nota actualizada');
    }

    /**
     * Toggle completada (AJAX).
     */
    public function toggleCompletada(NotaPersonal $nota)
    {
        if ($nota->user_id !== auth()->id()) {
            abort(403);
        }

        $nota->update(['completada' => !$nota->completada]);

        return response()->json([
            'success'    => true,
            'completada' => $nota->completada,
        ]);
    }

    /**
     * Eliminar nota.
     */
    public function destroy(NotaPersonal $nota)
    {
        if ($nota->user_id !== auth()->id()) {
            abort(403);
        }

        $nota->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('notas.index')->with('success', 'Nota eliminada');
    }

    /**
     * Clasificar texto con OpenAI (GPT-4o-mini) — muy barato.
     */
    private function clasificarConIA(string $texto): string
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            return $this->clasificarPorKeywords($texto);
        }

        try {
            $categorias = implode(', ', NotaPersonal::CATEGORIAS);

            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => "Clasifica la siguiente nota en exactamente UNA de estas categorías: {$categorias}. Responde SOLO con el nombre de la categoría, sin explicación."
                        ],
                        ['role' => 'user', 'content' => $texto],
                    ],
                    'max_tokens'  => 20,
                    'temperature' => 0,
                ]);

            if ($response->successful()) {
                $cat = trim($response->json('choices.0.message.content'));
                // Validar que sea una categoría válida
                if (in_array($cat, NotaPersonal::CATEGORIAS)) {
                    return $cat;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error clasificando nota con IA: ' . $e->getMessage());
        }

        return $this->clasificarPorKeywords($texto);
    }

    /**
     * Clasificación por keywords (fallback sin IA).
     */
    private function clasificarPorKeywords(string $texto): string
    {
        $texto = mb_strtolower($texto);

        $rules = [
            'Urgente'      => ['urgente', 'importante', 'prioridad', 'inmediato', 'asap', 'ya'],
            'Reunión'      => ['reunión', 'reunion', 'junta', 'meeting', 'cita'],
            'Horas Extra'  => ['hora extra', 'horas extra', 'sobretiempo', 'overtime', 'turno'],
            'Tarea'        => ['hacer', 'completar', 'terminar', 'revisar', 'enviar', 'preparar', 'actualizar'],
            'Recordatorio' => ['recordar', 'no olvidar', 'acordar', 'acordarse', 'pendiente', 'recordatorio'],
            'Idea'         => ['idea', 'propuesta', 'sugerencia', 'podría', 'quizás'],
            'Personal'     => ['personal', 'doctor', 'médico', 'familia', 'casa'],
        ];

        foreach ($rules as $cat => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($texto, $kw)) {
                    return $cat;
                }
            }
        }

        return 'General';
    }

    /**
     * Intentar extraer fecha del texto dictado.
     */
    private function extraerFecha(string $texto): ?string
    {
        $texto = mb_strtolower($texto);

        if (str_contains($texto, 'hoy')) {
            return today()->toDateString();
        }
        if (str_contains($texto, 'mañana')) {
            return today()->addDay()->toDateString();
        }
        if (preg_match('/pasado\s*mañana/', $texto)) {
            return today()->addDays(2)->toDateString();
        }
        if (str_contains($texto, 'lunes')) {
            return today()->next(\Carbon\Carbon::MONDAY)->toDateString();
        }
        if (str_contains($texto, 'martes')) {
            return today()->next(\Carbon\Carbon::TUESDAY)->toDateString();
        }
        if (str_contains($texto, 'miércoles') || str_contains($texto, 'miercoles')) {
            return today()->next(\Carbon\Carbon::WEDNESDAY)->toDateString();
        }
        if (str_contains($texto, 'jueves')) {
            return today()->next(\Carbon\Carbon::THURSDAY)->toDateString();
        }
        if (str_contains($texto, 'viernes')) {
            return today()->next(\Carbon\Carbon::FRIDAY)->toDateString();
        }
        if (str_contains($texto, 'fin de mes') || str_contains($texto, 'a fin de mes')) {
            return today()->endOfMonth()->toDateString();
        }
        if (str_contains($texto, 'próxima semana') || str_contains($texto, 'proxima semana')) {
            return today()->addWeek()->startOfWeek()->toDateString();
        }

        return null;
    }
}
