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
        $rules = KizeoAutomationRule::withCount('runs')
            ->with('latestRun')
            ->orderBy('form_id')
            ->orderBy('priority')
            ->paginate(20);

        $stats = [
            'rules' => KizeoAutomationRule::count(),
            'active' => KizeoAutomationRule::where('enabled', true)->count(),
            'runs_today' => KizeoAutomationRun::whereDate('created_at', today())->count(),
            'errors_today' => KizeoAutomationRun::whereDate('created_at', today())->where('status', 'error')->count(),
        ];

        return view('kizeo_automations.index', compact('rules', 'stats'));
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
}
