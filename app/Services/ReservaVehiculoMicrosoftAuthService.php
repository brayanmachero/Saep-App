<?php

namespace App\Services;

use App\Models\SolicitanteReservaVehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ReservaVehiculoMicrosoftAuthService
{
    private const SESSION_STATE = 'reserva_vehiculo_microsoft_state';

    private const SESSION_IDENTITY = 'reserva_vehiculo_microsoft_identity';

    public function urlAutorizacion(Request $request): string
    {
        $this->asegurarConfiguracion();

        $state = Str::random(64);
        $request->session()->put(self::SESSION_STATE, $state);

        return 'https://login.microsoftonline.com/'.rawurlencode($this->tenantId()).'/oauth2/v2.0/authorize?'.http_build_query([
            'client_id' => $this->config('client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read',
            'state' => $state,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function verificarCallback(Request $request): array
    {
        $this->asegurarConfiguracion();

        if ($request->filled('error')) {
            throw new RuntimeException('Microsoft no autorizo el acceso solicitado.');
        }

        $stateEsperado = (string) $request->session()->pull(self::SESSION_STATE, '');
        if (! $stateEsperado || ! hash_equals($stateEsperado, (string) $request->input('state'))) {
            throw new RuntimeException('No fue posible validar la sesion de Microsoft. Inicia sesion nuevamente.');
        }

        $code = (string) $request->input('code');
        if ($code === '') {
            throw new RuntimeException('Microsoft no devolvio un codigo de autorizacion.');
        }

        $token = Http::asForm()->timeout(15)->post(
            'https://login.microsoftonline.com/'.rawurlencode($this->tenantId()).'/oauth2/v2.0/token',
            [
                'client_id' => $this->config('client_id'),
                'client_secret' => $this->config('client_secret'),
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ],
        )->throw()->json();

        $profile = Http::withToken((string) ($token['access_token'] ?? ''))
            ->timeout(15)
            ->get('https://graph.microsoft.com/v1.0/me', ['$select' => 'id,displayName,mail,userPrincipalName'])
            ->throw()
            ->json();

        $email = strtolower(trim((string) ($profile['mail'] ?? $profile['userPrincipalName'] ?? '')));
        $domain = strtolower(ltrim((string) $this->config('allowed_domain', 'saep.cl'), '@'));

        if (! $email || ! Str::endsWith($email, '@'.$domain)) {
            throw new RuntimeException('Este portal de reservas esta disponible solo para cuentas corporativas SAEP con correo @'.$domain.'.');
        }

        if ($this->config('require_approved_requester', false)
            && ! SolicitanteReservaVehiculo::query()->where('email', $email)->where('activo', true)->exists()) {
            throw new RuntimeException('Tu cuenta corporativa no esta habilitada aun para solicitar vehiculos. Contacta a Bodega.');
        }

        $identidad = [
            'oid' => (string) ($profile['id'] ?? ''),
            'email' => $email,
            'name' => trim((string) ($profile['displayName'] ?? $email)),
        ];

        $request->session()->put(self::SESSION_IDENTITY, $identidad);

        return $identidad;
    }

    public function identidad(Request $request): ?array
    {
        return $request->session()->get(self::SESSION_IDENTITY);
    }

    public function cerrarSesion(Request $request): void
    {
        $request->session()->forget([self::SESSION_STATE, self::SESSION_IDENTITY]);
    }

    public function estaConfigurado(): bool
    {
        return (bool) ($this->config('tenant_id') && $this->config('client_id') && $this->config('client_secret'));
    }

    private function asegurarConfiguracion(): void
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('El acceso Microsoft para reservas aun no esta configurado.');
        }
    }

    private function tenantId(): string
    {
        return (string) $this->config('tenant_id');
    }

    private function redirectUri(): string
    {
        return (string) ($this->config('redirect') ?: route('reservas-vehiculos.microsoft.callback'));
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config('services.reservas_vehiculos_microsoft.'.$key, $default);
    }
}
