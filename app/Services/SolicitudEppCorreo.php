<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SolicitudEppCorreo
{
    public static function registrar(int $pedido, string $evento, ?array $propuesta = null): void
    {
        $order = DB::table('solicitudes_epp')->find($pedido);
        $items = json_decode($order->articulos, true);
        foreach (json_decode($order->cambios ?? '[]', true) as $change) {
            if (($change['respuesta'] ?? 'aceptada') === 'aceptada') {
                $items[$change['linea']] = $change['nuevo'];
            }
        }
        $team = User::where('activo', true)->whereHas('rol', fn ($q) => $q->whereIn('codigo', ['BODEGA_ENTREGAS', 'BODEGA_VEHICULOS']))
            ->pluck('email')->prepend('bodega@saep.cl')->map(fn ($email) => strtolower(trim($email)))->filter()->unique()->values();
        $received = $evento === 'Solicitud recibida';
        $to = $received ? $team : collect([strtolower($order->email)]);
        $cc = collect(['bmachero@saep.cl']);
        if (in_array($evento, ['Reemplazo aceptado', 'Reemplazo rechazado'], true)) {
            $cc = $cc->merge($team);
        }
        $link = $received
            ? route('inventario-bodega.epp.index', ['buscar'=>$pedido,'mes'=>substr($order->created_at,0,7),'estado'=>'todas','pedido'=>$pedido])
            : route('epp.portal', ['pedido'=>$pedido]).'#pedido-'.$pedido;
        $payload = [
            'from' => 'Bodega SAEP <notificaciones@saep.cl>',
            'to' => $to->all(),
            'subject' => "Pedido EPP #{$pedido} · {$evento}",
            'html' => view('epp.correo', compact('order','items','evento','propuesta','link','received'))->render(),
        ];
        $copies = $cc->unique()->diff($to)->values();
        if ($copies->isNotEmpty()) {
            $payload['cc'] = $copies->all();
        }
        DB::table('epp_correos')->insert([
            'pedido_id' => $pedido,
            'clave' => implode('/', ['epp', $pedido, str($evento)->slug(), $propuesta['id'] ?? 'pedido']),
            'mensaje' => json_encode($payload, JSON_THROW_ON_ERROR),
            'proximo_intento' => now(),
        ]);
    }
}
