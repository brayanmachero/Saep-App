<?php

namespace App\Modules\Comercial\Services;

use App\Modules\Comercial\Models\Parametro;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class IntegradorGobiernoService
{
    private Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    public function obtenerUF(): ?float
    {
        $uf = $this->obtenerSerieBancoCentral(config('comercial.government_apis.bcch.uf_series', 'F073.UFF.PRE.Z.D'));

        if ($uf === null && config('comercial.government_apis.mindicador.enabled', true)) {
            $uf = $this->obtenerIndicadorMindicador('uf');
        }

        if ($uf !== null) {
            $this->guardarParametro('UF', $uf, 'Valor UF actualizado desde fuente configurada');
        }

        return $uf;
    }

    public function obtenerSueldoMinimo(): ?int
    {
        $url = config('comercial.government_apis.sueldo_minimo.url');

        if (! $url) {
            Log::info('Sueldo mínimo sin API oficial configurada; se mantiene valor manual del mantenedor.');
            return null;
        }

        try {
            $response = $this->httpClient->get($url, ['headers' => ['Accept' => 'application/json']]);
            $data = json_decode($response->getBody()->getContents(), true);
            $valor = $data['valor'] ?? $data['sueldo_minimo'] ?? null;

            if ($valor !== null) {
                $sueldo = (int) $valor;
                $this->guardarParametro('SUELDO_MINIMO', $sueldo, 'Sueldo mínimo actualizado desde fuente configurada');
                return $sueldo;
            }
        } catch (\Throwable $e) {
            Log::warning('No fue posible obtener sueldo mínimo: '.$e->getMessage());
        }

        return null;
    }

    public function obtenerIPC(): ?float
    {
        $ipc = null;

        if (config('comercial.government_apis.mindicador.enabled', true)) {
            $ipc = $this->obtenerIndicadorMindicador('ipc');
        }

        if ($ipc !== null) {
            $this->guardarParametro('IPC', $ipc, 'IPC actualizado desde fuente configurada');
        }

        return $ipc;
    }

    public function obtenerTasasDeSeguro(): ?array
    {
        return [
            'nota' => 'Las tasas previsionales se mantienen en el mantenedor Comercial y deben ser revisadas por el responsable del proceso.',
        ];
    }

    public function actualizarTodos(): array
    {
        return [
            'uf' => $this->obtenerUF(),
            'sueldo_minimo' => $this->obtenerSueldoMinimo(),
            'ipc' => $this->obtenerIPC(),
            'tasas' => $this->obtenerTasasDeSeguro(),
        ];
    }

    private function obtenerSerieBancoCentral(string $serie): ?float
    {
        $user = config('comercial.government_apis.bcch.user');
        $pass = config('comercial.government_apis.bcch.pass');

        if (! $user || ! $pass) {
            return null;
        }

        try {
            $response = $this->httpClient->get('https://si3.bcentral.cl/SieteRestWS/SieteRestWS.ashx', [
                'query' => [
                    'user' => $user,
                    'pass' => $pass,
                    'function' => 'GetSeries',
                    'timeseries' => $serie,
                    'firstdate' => now()->subDays(10)->format('Y-m-d'),
                    'lastdate' => now()->format('Y-m-d'),
                ],
                'headers' => ['Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $observaciones = $data['Series']['Obs'] ?? [];
            $ultima = collect($observaciones)->reverse()->first(fn ($obs) => isset($obs['value']) && $obs['value'] !== 'NaN');

            return $ultima ? $this->parseNumero($ultima['value']) : null;
        } catch (\Throwable $e) {
            Log::warning('No fue posible obtener serie BCCh '.$serie.': '.$e->getMessage());
            return null;
        }
    }

    private function obtenerIndicadorMindicador(string $indicador): ?float
    {
        try {
            $response = $this->httpClient->get("https://mindicador.cl/api/{$indicador}", [
                'headers' => ['Accept' => 'application/json'],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            $valor = $data['serie'][0]['valor'] ?? $data['valor'] ?? null;

            return $valor !== null ? (float) $valor : null;
        } catch (\Throwable $e) {
            Log::warning("No fue posible obtener indicador {$indicador}: ".$e->getMessage());
            return null;
        }
    }

    private function guardarParametro(string $clave, mixed $valor, string $descripcion = ''): void
    {
        $parametro = Parametro::where('clave', $clave)->first();

        if ($parametro) {
            $parametro->valor_anterior = $parametro->valor;
            $parametro->version += 1;
            $parametro->valor = (string) $valor;
            $parametro->actualizado_por = auth()->id();
            $parametro->descripcion = $descripcion ?: $parametro->descripcion;
            $parametro->save();

            return;
        }

        Parametro::create([
            'clave' => $clave,
            'nombre' => ucwords(str_replace('_', ' ', strtolower($clave))),
            'descripcion' => $descripcion,
            'valor' => (string) $valor,
            'tipo' => is_int($valor) ? 'integer' : 'decimal',
            'editable' => true,
            'categoria' => 'GOBIERNO',
            'version' => 1,
            'actualizado_por' => auth()->id(),
        ]);
    }

    private function parseNumero(string|int|float $valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $limpio = str_replace('.', '', (string) $valor);
        $limpio = str_replace(',', '.', $limpio);

        return (float) $limpio;
    }
}
