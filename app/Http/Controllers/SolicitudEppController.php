<?php

namespace App\Http\Controllers;

use App\Models\InventarioCentroCosto;
use App\Models\InventarioVariante;
use App\Models\InventarioUbicacion;
use App\Services\InventarioStockService;
use Carbon\CarbonImmutable;
use App\Services\ReservaVehiculoMicrosoftAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SolicitudEppController extends Controller
{
    private function identity(Request $request): ?array
    {
        $user = $request->user();
        if ($user && $user->activo && Str::endsWith(strtolower($user->email), '@saep.cl')) {
            return ['name' => $user->name, 'email' => strtolower($user->email)];
        }
        return $request->session()->get('epp.microsoft_identity');
    }

    private function variants()
    {
        return InventarioVariante::with('producto')->where('activo', true)->whereHas('producto', fn ($q) => $q->where('activo', true));
    }

    public function login(Request $request, ReservaVehiculoMicrosoftAuthService $auth)
    {
        if (! $auth->estaConfigurado()) {
            return back()->withErrors(['login' => 'El acceso Microsoft no está configurado.']);
        }
        return redirect()->away($auth->urlAutorizacion($request, true));
    }

    public function portal(Request $request)
    {
        $identity = $this->identity($request);
        $centers = $identity ? InventarioCentroCosto::where('activo', true)->orderBy('nombre')->get(['id','nombre','codigo_origen']) : collect();
        $catalog = $identity ? $this->variants()->get()->map(fn ($v) => $this->item($v))->sortBy('nombre')->values() : collect();
        $history = $identity ? DB::table('solicitudes_epp')->where('email', $identity['email'])->latest('id')->limit(10)->get() : collect();
        return view('epp.portal', compact('identity','centers','catalog','history'));
    }

    private function item(InventarioVariante $v): array
    {
        return ['id' => $v->id, 'nombre' => $v->producto->nombre, 'codigo' => $v->codigo ?: $v->producto->codigo, 'talla' => $v->talla, 'categoria' => $v->producto->categoria ?: $v->producto->tipo, 'unidad' => $v->producto->unidad_medida];
    }

    public function store(Request $request)
    {
        abort_unless($identity = $this->identity($request), 403);
        $data = $request->validate(['folio'=>'required|uuid', 'centro_id'=>'required|integer','mes'=>'required|date_format:Y-m','cantidades'=>'nullable|array|max:200','cantidades.*'=>'required|integer|min:1|max:99999','insumos'=>'nullable|string|max:3000']);
        $quantities = $data['cantidades'] ?? [];
        if (! $quantities && blank($data['insumos'] ?? null)) {
            throw ValidationException::withMessages(['cantidades'=>'Agrega un artículo o describe el insumo y su cantidad.']);
        }
        DB::transaction(function () use ($identity, $data, $quantities) {
            $center = InventarioCentroCosto::whereKey($data['centro_id'])->where('activo', true)->lockForUpdate()->first();
            if (! $center) throw ValidationException::withMessages(['centro_id'=>'El centro ya no está activo. Selecciona otro.']);
            $existing = DB::table('solicitudes_epp')->where('folio',$data['folio'])->first();
            if ($existing) { abort_unless($existing->email === $identity['email'], 409); return; }
            $ids = array_keys($quantities);
            if (array_filter($ids, fn ($id) => !ctype_digit((string)$id) || (int)$id < 1)) throw ValidationException::withMessages(['cantidades'=>'Selección de artículo inválida.']);
            $variants = $this->variants()->whereKey($ids)->lockForUpdate()->get();
            if ($variants->count() !== count($ids)) throw ValidationException::withMessages(['cantidades'=>'Un artículo ya no está disponible en el catálogo. Revisa el pedido.']);
            $items = $variants->map(fn ($v) => $this->item($v) + ['cantidad'=>(int)$quantities[$v->id]])->values();
            DB::table('solicitudes_epp')->insert(['folio'=>$data['folio'],'nombre'=>$identity['name'],'email'=>$identity['email'],'centro_id'=>$center->id,'centro'=>trim($center->codigo_origen.' '.$center->nombre),'mes'=>$data['mes'],'articulos'=>$items->toJson(),'insumos'=>$data['insumos'] ?? null,'created_at'=>now(),'updated_at'=>now()]);
        });
        return redirect()->route('epp.portal')->with('pedido_ok', $data['folio']);
    }

    public function inbox(Request $request)
    {
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:150'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'estado' => ['nullable', 'in:pendientes,completadas,todas'],
        ]);
        $filters['mes'] ??= now()->format('Y-m');
        $filters['estado'] ??= 'pendientes';
        $start = CarbonImmutable::parse($filters['mes'].'-01')->startOfDay();
        $query = DB::table('solicitudes_epp')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->addMonth());
        if (filled($filters['buscar'] ?? null)) {
            $term = trim($filters['buscar']);
            $query->where(function ($query) use ($term) {
                foreach (['centro', 'nombre', 'email'] as $field) {
                    $query->orWhere($field, 'like', '%'.$term.'%');
                }
                if (ctype_digit(ltrim($term, '#'))) {
                    $query->orWhere('id', (int) ltrim($term, '#'));
                }
            });
        }
        $total = (clone $query)->count();
        $completed = (clone $query)->whereNotNull('completada_en')->count();
        $counts = ['pendientes' => $total - $completed, 'completadas' => $completed, 'todas' => $total];
        if ($filters['estado'] === 'pendientes') {
            $query->whereNull('completada_en');
        } elseif ($filters['estado'] === 'completadas') {
            $query->whereNotNull('completada_en');
        }

        $requests = $query->latest('id')->paginate(30)->withQueryString();
        $ids = $requests->getCollection()->flatMap(function ($order) {
            $order->items = json_decode($order->articulos, true);
            return array_column($order->items, 'id');
        })->unique();
        $central = InventarioUbicacion::where('activo', true)
            ->where('codigo', InventarioStockService::KIZEO_ORIGIN_LOCATION_CODE)->value('id');
        $stock = $central && $ids->isNotEmpty()
            ? DB::table('inventario_movimientos')->where('ubicacion_id', $central)
                ->whereIn('variante_id', $ids)->groupBy('variante_id')
                ->selectRaw('variante_id, SUM(cantidad) AS saldo')->pluck('saldo', 'variante_id')
            : collect();

        return view('epp.inbox', compact('requests', 'filters', 'counts', 'central', 'stock'));
    }

    public function complete(Request $request, int $pedido)
    {
        $order = DB::table('solicitudes_epp')->where('id', $pedido);
        abort_unless((clone $order)->exists(), 404);
        $order->whereNull('completada_en')->update([
            'completada_en' => now(),
            'completada_por' => $request->user()->id,
            'updated_at' => now(),
        ]);
        return back()->with('epp_status', 'Solicitud marcada como completada.');
    }
}
