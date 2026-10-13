<?php

namespace App\Console\Commands;

use App\Modules\Comercial\Models\CentroCosto;
use App\Modules\Comercial\Models\TarifaReferencia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomologarTarifas extends Command
{
    protected $signature = 'comercial:homologar-tarifas {--aplicar : Guardar coincidencias únicas con auditoría}';

    protected $description = 'Identifica clientes de referencias pendientes sin aprobar precios ni cambiar centros';

    public static function normalizar(string $text): string
    {
        $text = Str::upper(Str::ascii(trim($text)));
        $text = preg_replace('/^(?:COPIA DE\s+)?(?:\d+(?:[.-]\d+)*[.\s-]+)?(?:FINAL[\s-]+)?/', '', $text);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/\b(EST|SUB)\b|[.]/', ' ', $text)));
    }

    public static function resolver(string $centro, array $maestros): ?string
    {
        $key = self::normalizar($centro);
        if ($key === '') {
            return null;
        }
        $exact = collect($maestros)->filter(fn ($m) => self::normalizar($m['centro']) === $key)->pluck('cliente')->filter()->unique()->values();
        if ($exact->isNotEmpty()) {
            return $exact->count() === 1 ? $exact->first() : null;
        }
        // LTS is an existing commercial-center designation. Resolve its owner
        // only when every LTS master agrees, never by a hard-coded client ID.
        if (str_starts_with($key, 'LTS ')) {
            $lts = collect($maestros)->filter(fn ($m) => str_starts_with(self::normalizar($m['centro']), 'LTS '))->pluck('cliente')->filter()->unique()->values();
            if ($lts->count() === 1) {
                return $lts->first();
            }
            return null;
        }
        // A customer name must appear as a complete prefix; no fuzzy spelling,
        // first-word guesses, or automatic assignment of a cost-center ID.
        $clients = collect($maestros)->pluck('cliente')->filter()->unique()->filter(function ($cliente) use ($key) {
            $prefix = self::normalizar($cliente);

            return $prefix !== '' && ($key === $prefix || str_starts_with($key, $prefix.' '));
        })->values();

        return $clients->count() === 1 ? $clients->first() : null;
    }

    public function handle(): int
    {
        $maestros = CentroCosto::with('cliente')->get()->map(fn ($c) => [
            'centro' => $c->nombre, 'cliente' => $c->cliente?->nombre_comercial ?: $c->cliente?->nombre,
        ])->all();
        $changes = [];
        DB::transaction(function () use ($maestros, &$changes) {
            $query = TarifaReferencia::where('cliente', 'Por homologar')->where('estado', 'pendiente')->whereNull('revisado_at');
            if ($this->option('aplicar')) {
                $query->lockForUpdate();
            }
            foreach ($query->get() as $tarifa) {
                $cliente = self::resolver((string) $tarifa->centro, $maestros);
                if ($cliente === null) {
                    continue;
                }
                $changes[] = ['id' => $tarifa->id, 'centro' => $tarifa->centro, 'cliente' => $cliente];
                if ($this->option('aplicar')) {
                    $antes = $tarifa->toArray();
                    $tarifa->cliente = $cliente;
                    $tarifa->grupo = TarifaReferencia::grupoPara($tarifa->toArray());
                    $tarifa->save();
                    $tarifa->revisiones()->create(['usuario_id' => null, 'antes' => $antes,
                        'despues' => $tarifa->toArray() + ['motivo_revision' => 'Homologación inequívoca con maestros comerciales: centro normalizado, nombre completo del cliente o denominación LTS con propietario único. Se omiten prefijos de archivo numéricos/FINAL/Copia de; no aprueba precio ni vigencia.'],
                    ]);
                }
            }
        });
        $this->line(json_encode(['aplicado' => (bool) $this->option('aplicar'), 'coincidencias' => count($changes), 'detalle' => $changes], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
