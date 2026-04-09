<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user()->load(['rol', 'departamento', 'cargo', 'centroCosto']);
        return view('perfil.index', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'telefono' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update($data);

        return back()->with('success', 'Información actualizada correctamente.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', function ($attribute, $value, $fail) use ($user) {
                if (!Hash::check($value, $user->password)) {
                    $fail('La contraseña actual es incorrecta.');
                }
            }],
            'password' => ['required', 'confirmed', Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()],
        ], [
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.letters' => 'La nueva contraseña debe contener al menos una letra.',
            'password.mixed' => 'La nueva contraseña debe contener mayúsculas y minúsculas.',
            'password.numbers' => 'La nueva contraseña debe contener al menos un número.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'foto.max' => 'La imagen no debe superar los 2 MB.',
            'foto.mimes' => 'Solo se permiten imágenes JPG, PNG o WebP.',
        ]);

        $user = Auth::user();

        // Eliminar foto anterior si existe
        if ($user->foto_perfil && Storage::disk('public')->exists($user->foto_perfil)) {
            Storage::disk('public')->delete($user->foto_perfil);
        }

        $path = $request->file('foto')->store('fotos_perfil', 'public');
        $user->update(['foto_perfil' => $path]);

        return back()->with('success', 'Foto de perfil actualizada.');
    }

    public function deletePhoto()
    {
        $user = Auth::user();

        if ($user->foto_perfil && Storage::disk('public')->exists($user->foto_perfil)) {
            Storage::disk('public')->delete($user->foto_perfil);
        }

        $user->update(['foto_perfil' => null]);

        return back()->with('success', 'Foto de perfil eliminada.');
    }
}
