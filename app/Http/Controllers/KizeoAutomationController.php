<?php

namespace App\Http\Controllers;

use App\Models\KizeoAutomationRule;
use App\Models\KizeoAutomationRun;
use App\Services\KizeoService;
use Illuminate\Http\Request;

class KizeoAutomationController extends Controller
{
    private const OPERATORS = [
        'equals' => 'Igual a',
        'not_equals' => 'Distinto de',
        'contains' => 'Contiene',
        'not_contains' => 'No contiene',
        'starts_with' => 'Empieza con',
        'ends_with' => 'Termina con',
        'empty' => 'Está vacío',
        'not_empty' => 'No está vacío',
        'in' => 'Está en lista',
    ];

    public function index()
    {
        $legacyAutomations = $this->legacyAutomations();

        $rules = KizeoAutomationRule::withCount('runs')
            ->with('latestRun')
            ->orderBy('form_id')
            ->orderBy('priority')
            ->paginate(20);

        $stats = [
            'rules' => KizeoAutomationRule::count(),
            'active' => KizeoAutomationRule::where('enabled', true)->count(),
            'legacy_active' => collect($legacyAutomations)->where('active', true)->count(),
            'runs_today' => KizeoAutomationRun::whereDate('created_at', today())->count(),
            'errors_today' => KizeoAutomationRun::whereDate('created_at', today())->where('status', 'error')->count(),
        ];

        return view('kizeo_automations.index', compact('rules', 'stats', 'legacyAutomations'));
    }

    public function create(KizeoService $kizeo)
    {
        $rule = new KizeoAutomationRule([
            'enabled' => true,
            'priority' => 100,
            'folder_template' => '{anio}/{mes} - {mes_nombre}',
            'filename_template' => '{fecha} - {form_name} - {data_id}.pdf',
            'continue_legacy' => false,
        ]);

        return view('kizeo_automations.form', [
            'rule' => $rule,
            'forms' => $this->forms($kizeo),
            'operators' => self::OPERATORS,
            'conditions' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['conditions'] = $this->conditionsFromRequest($request);

        KizeoAutomationRule::create($data);

        return redirect()
            ->route('kizeo-automations.index')
            ->with('success', 'Automatización Kizeo creada.');
    }

    public function edit(KizeoAutomationRule $kizeoAutomation, KizeoService $kizeo)
    {
        return view('kizeo_automations.form', [
            'rule' => $kizeoAutomation,
            'forms' => $this->forms($kizeo),
            'operators' => self::OPERATORS,
            'conditions' => $kizeoAutomation->conditions ?? [],
        ]);
    }

    public function update(Request $request, KizeoAutomationRule $kizeoAutomation)
    {
        $data = $this->validated($request);
        $data['conditions'] = $this->conditionsFromRequest($request);

        $kizeoAutomation->update($data);

        return redirect()
            ->route('kizeo-automations.index')
            ->with('success', 'Automatización Kizeo actualizada.');
    }

    public function destroy(KizeoAutomationRule $kizeoAutomation)
    {
        $kizeoAutomation->delete();

        return redirect()
            ->route('kizeo-automations.index')
            ->with('success', 'Automatización Kizeo eliminada.');
    }

    public function toggle(KizeoAutomationRule $kizeoAutomation)
    {
        $kizeoAutomation->update(['enabled' => !$kizeoAutomation->enabled]);

        return back()->with('success', 'Estado de automatización actualizado.');
    }

    public function lookupForm(Request $request, KizeoService $kizeo)
    {
        $data = $request->validate([
            'form_id' => ['required', 'string', 'max:80'],
        ]);

        try {
            $response = $kizeo->rawGet('forms/' . $data['form_id'], 20);
            $form = $response['form'] ?? [];

            if (!$form) {
                return response()->json(['message' => 'Formulario no encontrado en Kizeo.'], 404);
            }

            return response()->json([
                'id' => (string) ($form['id'] ?? $data['form_id']),
                'name' => (string) ($form['name'] ?? ''),
                'fields' => $this->fieldsFromForm($form),
                'exports' => $this->exportsFromForm($form),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo consultar el formulario en Kizeo.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'form_id' => ['required', 'string', 'max:80'],
            'form_name' => ['nullable', 'string', 'max:180'],
            'enabled' => ['nullable', 'boolean'],
            'priority' => ['required', 'integer', 'min:0', 'max:999'],
            'sharepoint_site' => ['nullable', 'string', 'max:120'],
            'sharepoint_folder' => ['nullable', 'string', 'max:500'],
            'folder_template' => ['required', 'string', 'max:500'],
            'filename_template' => ['required', 'string', 'max:300'],
            'export_id' => ['nullable', 'string', 'max:80'],
            'continue_legacy' => ['nullable', 'boolean'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['continue_legacy'] = $request->boolean('continue_legacy');

        return $data;
    }

    private function conditionsFromRequest(Request $request): array
    {
        $fields = $request->input('conditions.field', []);
        $operators = $request->input('conditions.operator', []);
        $values = $request->input('conditions.value', []);
        $conditions = [];

        foreach ($fields as $index => $field) {
            $field = trim((string) $field);

            if ($field === '') {
                continue;
            }

            $conditions[] = [
                'field' => $field,
                'operator' => $operators[$index] ?? 'equals',
                'value' => trim((string) ($values[$index] ?? '')),
            ];
        }

        return $conditions;
    }

    private function forms(KizeoService $kizeo): array
    {
        try {
            return collect($kizeo->getForms())
                ->map(fn ($form) => [
                    'id' => (string) ($form['id'] ?? ''),
                    'name' => (string) ($form['name'] ?? 'Sin nombre'),
                ])
                ->filter(fn ($form) => $form['id'] !== '')
                ->sortBy('name')
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function fieldsFromForm(array $form): array
    {
        return collect($form['fields'] ?? [])
            ->map(function ($field, $key) {
                return [
                    'key' => (string) $key,
                    'caption' => (string) ($field['caption'] ?? $key),
                    'type' => (string) ($field['type'] ?? ''),
                    'required' => (bool) ($field['required'] ?? false),
                    'token' => '{' . $key . '}',
                    'kizeo_tag' => '##' . $key . '##',
                ];
            })
            ->reject(fn ($field) => in_array($field['type'], ['section', 'separator', 'fixed_text'], true))
            ->values()
            ->all();
    }

    private function exportsFromForm(array $form): array
    {
        return collect($form['exports'] ?? [])
            ->map(fn ($export) => [
                'id' => (string) ($export['id'] ?? ''),
                'name' => (string) ($export['computedNames']['pdf'] ?? $export['name'] ?? 'Export'),
                'type' => (string) ($export['type'] ?? ''),
                'is_default' => (bool) ($export['is_default'] ?? false),
                'is_pdf_default' => (bool) ($export['json']['is_pdf_default'] ?? false),
                'deleted' => (bool) ($export['deleted'] ?? false),
            ])
            ->filter(fn ($export) => $export['id'] !== '' && !$export['deleted'])
            ->values()
            ->all();
    }

    private function legacyAutomations(): array
    {
        $site = config('services.microsoft_graph.sharepoint_site', 'PDR');
        $root = config('services.microsoft_graph.root_folder', 'Actas Vehiculos');

        return collect([
            [
                'name' => 'Vehículos Entrega / Devolución',
                'form_id' => config('services.kizeo.vehicle_form_id'),
                'destination' => "{$site} / {$root}",
                'source' => 'KIZEO_VEHICLE_FORM_ID',
            ],
            [
                'name' => 'Charla SST',
                'form_id' => config('services.kizeo.charla_form_id'),
                'destination' => $site . ' / ' . config('services.kizeo.charla_sharepoint_folder', 'Charlas SST'),
                'source' => 'KIZEO_CHARLA_FORM_ID',
            ],
            [
                'name' => 'Observación de Conducta',
                'form_id' => config('services.kizeo.observacion_form_id'),
                'destination' => $site . ' / ' . config('services.kizeo.observacion_sharepoint_folder', 'Observaciones Conducta'),
                'source' => 'KIZEO_OBSERVACION_FORM_ID',
            ],
            [
                'name' => 'Inspección SST',
                'form_id' => config('services.kizeo.inspeccion_form_id'),
                'destination' => $site . ' / ' . config('services.kizeo.inspeccion_sharepoint_folder', 'Inspecciones SST'),
                'source' => 'KIZEO_INSPECCION_FORM_ID',
            ],
            [
                'name' => 'Visita Terreno',
                'form_id' => config('services.kizeo.visita_form_id'),
                'destination' => $site . ' / ' . config('services.kizeo.visita_sharepoint_folder', 'Visitas Terreno'),
                'source' => 'KIZEO_VISITA_FORM_ID',
            ],
            [
                'name' => 'Accidente SST',
                'form_id' => config('services.kizeo.accidente_form_id'),
                'destination' => $site . ' / ' . config('services.kizeo.accidente_sharepoint_folder', 'Accidentes SST'),
                'source' => 'KIZEO_ACCIDENTE_FORM_ID',
            ],
            [
                'name' => 'Declaración de Incidente',
                'form_id' => config('services.kizeo.declaracion_form_id'),
                'destination' => $site . ' / ' . config('services.kizeo.declaracion_sharepoint_folder', 'Declaraciones SST'),
                'source' => 'KIZEO_DECLARACION_FORM_ID',
            ],
            [
                'name' => 'Reunión CPHS',
                'form_id' => config('services.kizeo.cphs_form_id'),
                'destination' => $site . ' / ' . config('services.kizeo.cphs_sharepoint_folder', 'Reuniones CPHS'),
                'source' => 'KIZEO_CPHS_FORM_ID',
            ],
        ])->map(function (array $automation) {
            $automation['active'] = filled($automation['form_id']);

            return $automation;
        })->all();
    }
}
