<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->to($this->defaultRedirect(Auth::user()));
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            $user->update(['ultimo_acceso' => now()]);

            $default = $this->defaultRedirect($user);

            if ($default !== route('dashboard') && ! $user->tieneAcceso('dashboard')) {
                return redirect()->to($default);
            }

            return redirect()->intended($default);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Credenciales incorrectas.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function defaultRedirect($user): string
    {
        if ($user && !$user->tieneAcceso('dashboard')) {
            foreach ([
                'inventario_bodega' => 'inventario-bodega.index',
                'solicitudes_epp' => 'inventario-bodega.epp.index',
                'entregas_bodega_dashboard' => 'entregas-bodega-dashboard.index',
                'gestion_vehiculos' => 'gestion-vehiculos.index',
            ] as $module => $route) {
                if ($user->tieneAcceso($module)) return route($route);
            }
        }

        return route('dashboard');
    }
}
