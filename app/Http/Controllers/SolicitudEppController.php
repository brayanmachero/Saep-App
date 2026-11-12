<?php

namespace App\Http\Controllers;

use App\Models\InventarioCentroCosto;
use App\Models\InventarioVariante;
use App\Models\InventarioUbicacion;
use App\Services\InventarioStockService;
use Carbon\CarbonImmutable;
use App\Services\SolicitudEppCorreo;
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
        $target = $request->integer('pedido');
        if ($target && !$identity) {
            $request->session()->put('epp.pedido', $target);
        }
        if ($identity && !$target && $request->session()->has('epp.pedido')) {
            $target = $request->session()->pull('epp.pedido');
            return redirect()->route('epp.portal', ['pedido'=>$target])->withFragment('pedido-'.$target);
        }
        $centers = $identity ? InventarioCentroCosto::where('activo', true)->orderBy('nombre')->get(['id','nombre','codigo_origen']) : collect();
        $catalog = $identity ? $this->variants()->get()->map(fn ($v) => $this->item($v))->sortBy('nombre')->values() : collect();
        $history = $identity ? $this->orders()->where('email', $identity['email'])->latest('id')->limit(10)->get() : collect();
        if ($identity && $target && !$history->contains('id', $target)) {
            $requested = $this->orders()->where('email',$identity['email'])->find($target);
            if ($requested) $history->prepend($requested);
        }
        $history->each(fn ($order) => $this->prepareOrder($order));
        return view('epp.portal', compact('identity','centers','catalog','history'));
    }

    private function item(InventarioVariante $v): array
    {
        return ['id' => $v->id, 'nombre' => $v->producto->nombre, 'codigo' => $v->codigo ?: $v->producto->codigo, 'talla' => $v->talla, 'categoria' => $v->producto->categoria ?: $v->producto->tipo, 'unidad' => $v->producto->unidad_medida];
    }

    public function store(Request $request)
    {
        abort_unless($identity = $this->identity($request), 403);
        $input = $request->all();
        if (is_array($input['cantidades'] ?? null)) {
            foreach ($input['cantidades'] as $id => $quantity) {
                if (is_string($quantity) && preg_match('/^0*([0-9]{1,5})(?:[.,]0+)?$/D', $quantity, $parts)) {
                    $input['cantidades'][$id] = (int) $parts[1];
                }
            }
        }
        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'folio' => 'required|uuid',
            'centro_id' => 'required|integer',
            'mes' => 'required|date_format:Y-m',
            'cantidades' => 'nullable|array|max:200',
            'cantidades.*' => 'bail|required|integer|min:1|max:99999',
            'insumos' => 'nullable|string|max:3000',
        ], [
            'folio.required' => 'Vuelve a abrir el formulario para generar el número de envío.',
            'folio.uuid' => 'Vuelve a abrir el formulario para generar el número de envío.',
            'centro_id.required' => 'Selecciona un centro de trabajo de la lista.',
            'centro_id.integer' => 'Selecciona un centro de trabajo de la lista.',
            'mes.required' => 'Selecciona el mes del pedido.',
            'mes.date_format' => 'Selecciona el mes del pedido.',
            'cantidades.array' => 'Vuelve a seleccionar los artículos del pedido.',
            'cantidades.max' => 'Puedes agregar hasta 200 artículos por solicitud.',
            'cantidades.*.required' => 'Ingresa la cantidad del artículo indicado.',
            'cantidades.*.integer' => 'Ingresa una cantidad entera entre 1 y 99.999.',
            'cantidades.*.min' => 'La cantidad mínima es 1.',
            'cantidades.*.max' => 'La cantidad máxima es 99.999.',
            'insumos.string' => 'Describe los insumos en un máximo de 3.000 caracteres.',
            'insumos.max' => 'Describe los insumos en un máximo de 3.000 caracteres.',
        ]);
        if ($validator->fails()) {
            return redirect()->route('epp.portal')->withErrors($validator)->withInput($request->except('_token'));
        }
        $data = $validator->validated();
        $quantities = $data['cantidades'] ?? [];
        if (! $quantities && blank($data['insumos'] ?? null)) {
            throw ValidationException::withMessages(['cantidades'=>'Agrega un artículo o describe el insumo y su cantidad.'])->redirectTo(route('epp.portal'));
        }
        $number = DB::transaction(function () use ($identity, $data, $quantities) {
            $center = InventarioCentroCosto::whereKey($data['centro_id'])->where('activo', true)->lockForUpdate()->first();
            if (! $center) throw ValidationException::withMessages(['centro_id'=>'El centro ya no está activo. Selecciona otro.'])->redirectTo(route('epp.portal'));
            $existing = DB::table('solicitudes_epp')->where('folio',$data['folio'])->first();
            if ($existing) { abort_unless($existing->email === $identity['email'], 409); return $existing->id; }
            $ids = array_keys($quantities);
            if (array_filter($ids, fn ($id) => !ctype_digit((string)$id) || (int)$id < 1)) throw ValidationException::withMessages(['cantidades'=>'Selección de artículo inválida.'])->redirectTo(route('epp.portal'));
            $variants = $this->variants()->whereKey($ids)->lockForUpdate()->get();
            if ($variants->count() !== count($ids)) throw ValidationException::withMessages(['cantidades'=>'Un artículo ya no está disponible en el catálogo. Revisa el pedido.'])->redirectTo(route('epp.portal'));
            $items = $variants->map(fn ($v) => $this->item($v) + ['cantidad'=>(int)$quantities[$v->id]])->values();
            $id = DB::table('solicitudes_epp')->insertGetId(['folio'=>$data['folio'],'nombre'=>$identity['name'],'email'=>$identity['email'],'centro_id'=>$center->id,'centro'=>trim($center->codigo_origen.' '.$center->nombre),'mes'=>$data['mes'],'articulos'=>$items->toJson(),'insumos'=>$data['insumos'] ?? null,'created_at'=>now(),'updated_at'=>now()]);
            SolicitudEppCorreo::registrar($id, 'Solicitud recibida');
            return $id;
        });
        return redirect()->route('epp.portal')->with('pedido_ok', $number);
    }

    public function inbox(Request $request)
    {
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:150'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'estado' => ['nullable', 'in:pendientes,gestion,completadas,todas'],
        ]);
        $filters['mes'] ??= now()->format('Y-m');
        $filters['estado'] ??= 'pendientes';
        $start = CarbonImmutable::parse($filters['mes'].'-01')->startOfDay();
        $query = $this->orders()
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
        $managing = (clone $query)->whereNull('completada_en')->whereNotNull('cambios')->count();
        $counts = ['gestion' => $managing, 'pendientes' => $total - $completed - $managing, 'completadas' => $completed, 'todas' => $total];
        if ($filters['estado'] === 'pendientes') {
            $query->whereNull('completada_en')->whereNull('cambios');
        } elseif ($filters['estado'] === 'gestion') {
            $query->whereNull('completada_en')->whereNotNull('cambios');
        } elseif ($filters['estado'] === 'completadas') {
            $query->whereNotNull('completada_en');
        }

        $requests = $query->latest('id')->paginate(30)->withQueryString();
        $central = $this->central();
        $balances = $central && $requests->isNotEmpty() ? app(InventarioStockService::class)->balances($central) : collect();
        $stock = $this->stockByVariant($central);
        foreach ($requests as $order) {
            $this->prepareOrder($order);
            $order->options = $this->alternatives($order, $balances, $stock);
        }
        return view('epp.inbox', compact('requests', 'filters', 'counts', 'central', 'stock'));
    }

    private function orders()
    {
        return DB::table('solicitudes_epp')->select('solicitudes_epp.*')
            ->selectSub(DB::table('users')->select('name')->whereColumn('users.id', 'solicitudes_epp.completada_por'), 'gestor_cierre');
    }

    private function central()
    {
        return InventarioUbicacion::where('activo', true)
            ->where('codigo', InventarioStockService::KIZEO_ORIGIN_LOCATION_CODE)->value('id');
    }

    private function stockByVariant($central)
    {
        return $central ? DB::table('inventario_movimientos')->where('ubicacion_id', $central)
            ->groupBy('variante_id')->selectRaw('variante_id, SUM(cantidad) AS saldo')->pluck('saldo', 'variante_id') : collect();
    }

    private function prepareOrder($order): void
    {
        $order->items = json_decode($order->articulos, true);
        $order->events = json_decode($order->cambios ?? '[]', true);
        $order->pending = [];
        foreach ($order->events as $event) {
            $answer = $event['respuesta'] ?? 'aceptada';
            if ($answer === 'pendiente') {
                $order->pending[$event['linea']] = $event;
            } elseif ($answer === 'aceptada') {
                $order->items[$event['linea']] = $event['nuevo'];
            }
        }
        $order->demand = collect($order->items)->groupBy('id')->map(fn ($lines) => $lines->sum('cantidad'));
        $order->estado = $order->completada_en ? 'Completada' : ($order->events ? 'En gestión' : 'Pendiente');
    }

    private function alternatives($order, $balances, $stock): array
    {
        $options = [];
        $demand = clone $order->demand;
        foreach ($order->pending as $proposal) {
            $id = $proposal['nuevo']['id'];
            $demand->put($id, $demand->get($id,0) + $proposal['nuevo']['cantidad']);
        }
        $originals = json_decode($order->articulos, true);
        foreach ($order->items as $line => $item) {
            if (isset($order->pending[$line]) || $order->completada_en || $stock->get($item['id'], 0) >= $demand[$item['id']]) {
                continue;
            }
            $original = $originals[$line];
            $source = InventarioVariante::with('producto')->find($original['id']);
            if (! $source || ! $source->producto) {
                continue;
            }
            foreach ($balances as $variant) {
                if ($variant->id == $item['id'] || $variant->stock_actual - $demand->get($variant->id, 0) < $item['cantidad']) {
                    continue;
                }
                $sameCategory = filled($source->producto->categoria)
                    && $variant->producto->categoria === $source->producto->categoria
                    && $variant->producto->tipo === $source->producto->tipo;
                if (($variant->producto_id == $source->producto_id || $sameCategory)
                    && $variant->producto->unidad_medida === $source->producto->unidad_medida) {
                    $options[$line][$variant->id] = $variant;
                }
            }
        }
        return $options;
    }

    public function substitute(Request $request, int $pedido)
    {
        $data = $request->validate([
            'linea' => 'required|integer|min:0',
            'actual' => 'required|integer',
            'reemplazo' => 'required|integer',
        ]);
        DB::transaction(function () use ($pedido, $request, $data) {
            $query = DB::table('solicitudes_epp')->where('id', $pedido);
            $order = (clone $query)->lockForUpdate()->first();
            abort_unless($order, 404);
            $this->prepareOrder($order);
            $line = $data['linea'];
            $previous = $order->items[$line] ?? null;
            if (isset($order->pending[$line]) || $order->completada_en || ! $previous || $previous['id'] != $data['actual']) {
                throw ValidationException::withMessages(['pedido' => 'El pedido cambió. Revisa su estado actual.']);
            }
            $central = $this->central();
            $balances = $central ? app(InventarioStockService::class)->balances($central) : collect();
            $variant = $this->alternatives($order, $balances, $this->stockByVariant($central))[$line][$data['reemplazo']] ?? null;
            if (! $variant) {
                throw ValidationException::withMessages(['reemplazo' => 'El reemplazo ya no corresponde al faltante o al stock disponible. Revisa el detalle.']);
            }
            $proposal = [
                'id' => (string) Str::uuid(), 'respuesta' => 'pendiente',
                'linea' => (int) $line, 'anterior' => $previous,
                'nuevo' => $this->item($variant) + ['cantidad' => $previous['cantidad']],
                'usuario_id' => $request->user()->id, 'gestor' => $request->user()->name,
                'fecha' => now()->toDateTimeString(),
            ];
            $order->events[] = $proposal;
            $query->update(['cambios' => json_encode($order->events, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            SolicitudEppCorreo::registrar($pedido, 'En gestión · Reemplazo propuesto', $proposal);
        });
        return redirect()->route('inventario-bodega.epp.index', [
            'mes' => $request->query('mes'), 'estado' => 'gestion', 'pedido' => $pedido,
        ])->with('epp_status', 'Propuesta registrada. Pendiente de respuesta del solicitante.');
    }

    public function respond(Request $request, int $pedido, string $propuesta)
    {
        $identity = $this->identity($request);
        abort_unless($identity, 403);
        $answer = $request->validate(['respuesta'=>'required|in:aceptada,rechazada'])['respuesta'];
        DB::transaction(function () use ($pedido, $propuesta, $answer, $identity) {
            $query = DB::table('solicitudes_epp')->where('id',$pedido)->where('email',$identity['email']);
            $order = (clone $query)->lockForUpdate()->first();
            abort_unless($order,404);
            $events = json_decode($order->cambios ?? '[]',true);
            $index = collect($events)->search(fn ($event) => ($event['id'] ?? null) === $propuesta);
            abort_if($index === false,404);
            $event = $events[$index];
            if ($event['respuesta'] === $answer) return;
            abort_if($order->completada_en || $event['respuesta'] !== 'pendiente',409,'Esta propuesta ya fue resuelta.');
            $event['respuesta'] = $answer;
            $event['respondida_en'] = now()->toDateTimeString();
            $event['respondida_por'] = $identity['email'];
            $events[$index] = $event;
            $query->update(['cambios'=>json_encode($events, JSON_THROW_ON_ERROR),'updated_at'=>now()]);
            SolicitudEppCorreo::registrar($pedido, $answer === 'aceptada' ? 'Reemplazo aceptado' : 'Reemplazo rechazado', $event);
        });
        return redirect()->route('epp.portal',['pedido'=>$pedido])->withFragment('pedido-'.$pedido);
    }

    public function complete(Request $request, int $pedido)
    {
        DB::transaction(function () use ($request, $pedido) {
            $query = DB::table('solicitudes_epp')->where('id',$pedido);
            $order = (clone $query)->lockForUpdate()->first();
            abort_unless($order,404);
            if ($order->completada_en) return;
            $this->prepareOrder($order);
            if ($order->pending) {
                throw ValidationException::withMessages(['pedido'=>'Hay propuestas pendientes de respuesta.']);
            }
            $query->update(['completada_en'=>now(),'completada_por'=>$request->user()->id,'updated_at'=>now()]);
            SolicitudEppCorreo::registrar($pedido,'Solicitud completada');
        });
        return back()->with('epp_status', 'Solicitud marcada como completada.');
    }
}
