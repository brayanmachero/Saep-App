<?php

namespace App\Http\Controllers;

use App\Services\KizeoService;
use Illuminate\Http\Request;

class KizeoDashboardController extends Controller
{
    private KizeoService $kizeo;

    public function __construct(KizeoService $kizeo)
    {
        $this->kizeo = $kizeo;
    }

    /**
     * Dashboard principal PDR con indicadores.
     */
    public function index(Request $request)
    {
        return view('kizeo.dashboard');
    }

    /**
     * API: datos del dashboard (llamado via AJAX).
     */
    public function dashboardData(Request $request)
    {
        $startDate    = $request->input('start_date');
        $endDate      = $request->input('end_date');
        $forceRefresh = $request->boolean('force_refresh');

        try {
            $data = $this->kizeo->getDashboardData($startDate, $endDate, $forceRefresh);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: datos profundos de TODOS los formularios PDR (auto-carga).
     */
    public function allDeepData(Request $request)
    {
        set_time_limit(300); // Permitir hasta 5 min para cargar deep data

        $startDate    = $request->input('start_date');
        $endDate      = $request->input('end_date');
        $forceRefresh = $request->boolean('force_refresh');

        try {
            $data = $this->kizeo->getAllDeepData($startDate, $endDate, $forceRefresh, 15);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: lista de formularios PDR.
     */
    public function forms()
    {
        try {
            $forms = $this->kizeo->getPdrForms();
            return response()->json(['success' => true, 'forms' => array_values($forms)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: registros profundos de un formulario (Deep Analytics).
     */
    public function deepData(Request $request, string $formId)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $limit     = min((int) ($request->input('limit', 200)), 500);

        try {
            $records = $this->kizeo->getDeepFormData($formId, $startDate, $endDate, $limit);
            return response()->json(['success' => true, 'records' => $records]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: proxy de media (foto/firma).
     */
    public function media(string $formId, string $recordId, string $mediaId)
    {
        try {
            $media = $this->kizeo->getMedia($formId, $recordId, $mediaId);
            if (!$media) {
                return response()->json(['error' => 'Media no disponible'], 404);
            }
            return response()->json($media);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: detalle completo de un registro (para slide-out panel).
     */
    public function recordDetail(string $formId, string $recordId)
    {
        try {
            $record = $this->kizeo->getRecord($formId, $recordId);
            if (!$record) {
                return response()->json(['error' => 'Registro no encontrado'], 404);
            }
            $userDic = $this->kizeo->getUserDictionary();
            $userId = $record['user_id'] ?? null;
            $record['_user_display'] = $userDic[$userId] ?? ($record['user_name'] ?? "ID-{$userId}");
            return response()->json(['success' => true, 'record' => $record]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
