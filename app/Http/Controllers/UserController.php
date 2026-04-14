<?php

namespace App\Http\Controllers;

use App\Mail\BienvenidaUsuarioMail;
use App\Models\Cargo;
use App\Models\Configuracion;
use App\Notifications\AppNotification;
use App\Models\CentroCosto;
use App\Models\Departamento;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['rol', 'departamento', 'cargo', 'centroCosto']);

        if ($request->filled('buscar')) {
            $q = str_replace(['%', '_'], ['\%', '\_'], $request->buscar);
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%")
                  ->orWhere('rut', 'like', "%$q%")
                  ->orWhere('apellido_paterno', 'like', "%$q%")
                  ->orWhere('apellido_materno', 'like', "%$q%");
            });
        }

        if ($request->filled('rol_id')) {
            $query->where('rol_id', $request->rol_id);
        }

        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', $request->departamento_id);
        }

        if ($request->filled('cargo_id')) {
            $query->where('cargo_id', $request->cargo_id);
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->activo);
        }

        $usuarios = $query->latest()->paginate(15)->withQueryString();
        $roles = Rol::all();
        $departamentos = Departamento::where('activo', true)->get();
        $cargos = Cargo::where('activo', true)->get();

        return view('usuarios.index', compact('usuarios', 'roles', 'departamentos', 'cargos'));
    }

    public function create()
    {
        $roles = Rol::all();
        $departamentos = Departamento::where('activo', true)->get();
        $cargos = Cargo::where('activo', true)->get();
        $centrosCosto = CentroCosto::where('activo', true)->get();
        return view('usuarios.create', compact('roles', 'departamentos', 'cargos', 'centrosCosto'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:200'],
            'apellido_paterno'  => ['nullable', 'string', 'max:200'],
            'apellido_materno'  => ['nullable', 'string', 'max:200'],
            'email'             => ['required', 'email', 'unique:users,email'],
            'rut'               => ['nullable', 'string', 'max:20'],
            'rol_id'            => ['required', 'exists:roles,id'],
            'departamento_id'   => ['nullable', 'exists:departamentos,id'],
            'cargo_id'          => ['nullable', 'exists:cargos,id'],
            'centro_costo_id'   => ['nullable', 'exists:centros_costo,id'],
            'tipo_nomina'       => ['nullable', 'in:NORMAL,TRANSITORIO'],
            'razon_social'      => ['nullable', 'string', 'max:300'],
            'fecha_nacimiento'  => ['nullable', 'date'],
            'nacionalidad'      => ['nullable', 'string', 'max:100'],
            'sexo'              => ['nullable', 'string', 'max:10'],
            'estado_civil'      => ['nullable', 'string', 'max:50'],
            'fecha_ingreso'     => ['nullable', 'date'],
            'telefono'          => ['nullable', 'string', 'max:50'],
            'activo'            => ['boolean'],
        ]);

        // Generar contraseña provisoria automática
        $tempPassword = Str::upper(Str::random(3)) . rand(100, 999) . Str::random(3);
        $data['password'] = $tempPassword;
        $data['must_change_password'] = true;
        $data['activo'] = $request->boolean('activo', true);

        $user = User::create($data);

        // Enviar email de bienvenida con credenciales
        $emailOk = false;
        if (Configuracion::get('notificaciones_email') === 'true') {
            try {
                Mail::to($user->email)->send(new BienvenidaUsuarioMail($user, $tempPassword));
                $emailOk = true;
            } catch (\Throwable $e) {
                Log::error('Error enviando email de bienvenida', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $user->notify(new AppNotification('Bienvenido a SAEP', 'Tu cuenta ha sido creada. Revisa tu correo para tus credenciales de acceso.', 'success', route('perfil.show')));

        $emailMsg = $emailOk
            ? ' Se envió correo con credenciales a ' . $user->email . '.'
            : ' (No se pudo enviar el correo. Contraseña provisoria: ' . $tempPassword . ')';

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.' . $emailMsg);
    }

    public function edit(User $usuario)
    {
        $roles = Rol::all();
        $departamentos = Departamento::where('activo', true)->get();
        $cargos = Cargo::where('activo', true)->get();
        $centrosCosto = CentroCosto::where('activo', true)->get();
        return view('usuarios.edit', compact('usuario', 'roles', 'departamentos', 'cargos', 'centrosCosto'));
    }

    public function update(Request $request, User $usuario)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:200'],
            'apellido_paterno'  => ['nullable', 'string', 'max:200'],
            'apellido_materno'  => ['nullable', 'string', 'max:200'],
            'email'             => ['required', 'email', Rule::unique('users')->ignore($usuario->id)],
            'rut'               => ['nullable', 'string', 'max:20'],
            'rol_id'            => ['required', 'exists:roles,id'],
            'departamento_id'   => ['nullable', 'exists:departamentos,id'],
            'cargo_id'          => ['nullable', 'exists:cargos,id'],
            'centro_costo_id'   => ['nullable', 'exists:centros_costo,id'],
            'tipo_nomina'       => ['nullable', 'in:NORMAL,TRANSITORIO'],
            'razon_social'      => ['nullable', 'string', 'max:300'],
            'fecha_nacimiento'  => ['nullable', 'date'],
            'nacionalidad'      => ['nullable', 'string', 'max:100'],
            'sexo'              => ['nullable', 'string', 'max:10'],
            'estado_civil'      => ['nullable', 'string', 'max:50'],
            'fecha_ingreso'     => ['nullable', 'date'],
            'telefono'          => ['nullable', 'string', 'max:50'],
            'activo'            => ['boolean'],
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
            ]);
            $data['password'] = $request->password;
        }

        $data['activo'] = $request->boolean('activo');

        $usuario->update($data);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $usuario->update(['activo' => false]);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario desactivado correctamente.');
    }

    public function resetPassword(User $usuario)
    {
        $tempPassword = Str::upper(Str::random(3)) . rand(100, 999) . Str::random(3);
        $usuario->update([
            'password'             => $tempPassword,
            'must_change_password' => true,
        ]);

        $emailOk = false;
        if (Configuracion::get('notificaciones_email') === 'true') {
            try {
                Mail::to($usuario->email)->send(new BienvenidaUsuarioMail($usuario, $tempPassword));
                $emailOk = true;
            } catch (\Throwable $e) {
                Log::error('Error enviando email de reset password', [
                    'user_id' => $usuario->id,
                    'email'   => $usuario->email,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $usuario->notify(new AppNotification(
            'Contraseña restablecida',
            'Tu contraseña ha sido restablecida por un administrador. Revisa tu correo.',
            'warning',
            route('perfil.show')
        ));

        if ($emailOk) {
            $emailMsg = " Se envió correo a {$usuario->email}.";
        } elseif (Configuracion::get('notificaciones_email') !== 'true') {
            $emailMsg = ' (Envío de email desactivado en configuración).';
        } else {
            $emailMsg = " No se pudo enviar el correo. Contraseña provisoria: {$tempPassword}";
        }

        return back()->with('success', "Contraseña de {$usuario->nombre_completo} restablecida.{$emailMsg}");
    }

    public function bulkResetPassword(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'exists:users,id',
        ]);

        $usuarios = User::whereIn('id', $request->ids)->get();
        $emailActivo = Configuracion::get('notificaciones_email') === 'true';
        $count = 0;
        $emailFails = 0;

        foreach ($usuarios as $usuario) {
            $tempPassword = Str::upper(Str::random(3)) . rand(100, 999) . Str::random(3);
            $usuario->update([
                'password'             => $tempPassword,
                'must_change_password' => true,
            ]);

            if ($emailActivo) {
                try {
                    Mail::to($usuario->email)->send(new BienvenidaUsuarioMail($usuario, $tempPassword));
                } catch (\Throwable $e) {
                    $emailFails++;
                    Log::error('Error enviando email de reset password (bulk)', [
                        'user_id' => $usuario->id,
                        'email'   => $usuario->email,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            $usuario->notify(new AppNotification(
                'Contraseña restablecida',
                'Tu contraseña ha sido restablecida por un administrador. Revisa tu correo.',
                'warning',
                route('perfil.show')
            ));

            $count++;
        }

        $emailMsg = !$emailActivo
            ? ' (Envío de email desactivado).'
            : ($emailFails > 0
                ? " Se enviaron correos, pero {$emailFails} fallaron. Revise los logs."
                : ' Se enviaron correos con las nuevas credenciales.');

        return back()->with('success', "Se restablecieron {$count} contraseña(s).{$emailMsg}");
    }
}
