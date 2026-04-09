@extends('layouts.app')

@section('title', 'Mi Perfil')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">Mi Perfil</h2>
            <p class="page-subheading">Gestiona tu información personal y configuración de seguridad</p>
        </div>
    </div>

    {{-- Alertas --}}
    @if(session('warning'))
        <div style="background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #f59e0b;border-radius:12px;padding:1rem 1.25rem;color:#92400e;font-size:.9rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem;">
            <i class="bi bi-exclamation-triangle-fill"></i> <strong>{{ session('warning') }}</strong>
        </div>
    @endif
    @if(auth()->user()->must_change_password)
        <div style="background:#fef2f2;border:1px solid #fecaca;border-left:4px solid #ef4444;border-radius:12px;padding:1rem 1.25rem;color:#dc2626;font-size:.9rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem;">
            <i class="bi bi-shield-exclamation"></i> <strong>Tiene una contraseña provisoria.</strong> Debe cambiarla antes de continuar usando la plataforma.
        </div>
    @endif
    @if(session('success'))
        <div class="alert-success" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1rem 1.25rem;color:#16a34a;font-size:.9rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem;">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert-error" style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:1rem 1.25rem;color:#dc2626;font-size:.9rem;margin-bottom:1.5rem;">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.25rem;">
                <i class="bi bi-exclamation-circle-fill"></i> <strong>Se encontraron errores:</strong>
            </div>
            <ul style="margin:0;padding-left:1.25rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:340px 1fr;gap:1.5rem;align-items:start;">

        {{-- LEFT: Tarjeta de perfil --}}
        <div class="glass-card" style="text-align:center;padding:2rem 1.5rem;">
            {{-- Foto --}}
            <div style="position:relative;display:inline-block;margin-bottom:1.25rem;">
                @if($user->foto_perfil)
                    <img src="{{ asset('storage/' . $user->foto_perfil) }}" alt="Foto de perfil"
                         style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid var(--primary-color);">
                @else
                    <div style="width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#0f1b4c,#1e3a8a);display:flex;align-items:center;justify-content:center;margin:0 auto;">
                        <span style="font-size:2.5rem;font-weight:700;color:#fff;">
                            {{ strtoupper(substr($user->name, 0, 1)) }}{{ strtoupper(substr($user->apellido_paterno ?? '', 0, 1)) }}
                        </span>
                    </div>
                @endif
            </div>

            <h3 style="margin:0 0 .25rem;font-size:1.15rem;font-weight:700;color:var(--text-color);">
                {{ $user->nombre_completo }}
            </h3>
            <p style="margin:0 0 .5rem;font-size:.85rem;color:var(--text-muted);">{{ $user->email }}</p>
            <span class="badge {{ $user->activo ? 'success' : 'danger' }}" style="font-size:.75rem;">
                {{ $user->activo ? 'Activo' : 'Inactivo' }}
            </span>

            {{-- Upload foto --}}
            <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border-color);">
                <form method="POST" action="{{ route('perfil.foto') }}" enctype="multipart/form-data" id="foto-form">
                    @csrf
                    <label for="foto-input" class="btn-outline" style="display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.85rem;padding:.5rem 1rem;border-radius:8px;border:1.5px solid var(--border-color);background:transparent;color:var(--text-color);transition:all .2s;">
                        <i class="bi bi-camera-fill"></i> Cambiar foto
                    </label>
                    <input type="file" id="foto-input" name="foto" accept="image/jpeg,image/png,image/webp" style="display:none;"
                           onchange="document.getElementById('foto-form').submit();">
                </form>
                @if($user->foto_perfil)
                <form method="POST" action="{{ route('perfil.foto.delete') }}" style="margin-top:.5rem;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="background:none;border:none;color:#ef4444;font-size:.8rem;cursor:pointer;text-decoration:underline;">
                        Eliminar foto
                    </button>
                </form>
                @endif
                <p style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem;">JPG, PNG o WebP. Máx. 2 MB.</p>
            </div>

            {{-- Info rápida --}}
            <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border-color);text-align:left;">
                <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.85rem;">
                    <span style="color:var(--text-muted);">Rol</span>
                    <span style="font-weight:600;color:var(--text-color);">{{ $user->rol->nombre ?? '—' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.85rem;border-top:1px solid var(--border-color);">
                    <span style="color:var(--text-muted);">Departamento</span>
                    <span style="font-weight:600;color:var(--text-color);">{{ $user->departamento->nombre ?? '—' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.85rem;border-top:1px solid var(--border-color);">
                    <span style="color:var(--text-muted);">Cargo</span>
                    <span style="font-weight:600;color:var(--text-color);">{{ $user->cargo->nombre ?? '—' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.85rem;border-top:1px solid var(--border-color);">
                    <span style="color:var(--text-muted);">Centro de Costo</span>
                    <span style="font-weight:600;color:var(--text-color);">{{ $user->centroCosto->nombre ?? '—' }}</span>
                </div>
                @if($user->ultimo_acceso)
                <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.85rem;border-top:1px solid var(--border-color);">
                    <span style="color:var(--text-muted);">Último acceso</span>
                    <span style="font-weight:500;color:var(--text-color);">{{ $user->ultimo_acceso->format('d/m/Y H:i') }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- RIGHT: Formularios --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem;">

            {{-- Información Personal --}}
            <div class="glass-card">
                <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:.75rem;">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(15,27,76,0.08);display:flex;align-items:center;justify-content:center;color:var(--primary-color);font-size:1.1rem;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:1rem;font-weight:600;color:var(--text-color);">Información Personal</h3>
                        <p style="margin:0;font-size:.8rem;color:var(--text-muted);">Datos editables de tu cuenta</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('perfil.update') }}" style="padding:1.5rem;">
                    @csrf
                    @method('PUT')

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                        <div>
                            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.03em;">Nombre</label>
                            <input type="text" value="{{ $user->name }}" disabled
                                style="width:100%;padding:.65rem .85rem;border:1.5px solid var(--border-color);border-radius:10px;background:var(--surface-bg);color:var(--text-muted);font-size:.9rem;font-family:inherit;cursor:not-allowed;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.03em;">RUT</label>
                            <input type="text" value="{{ $user->rut ?? '—' }}" disabled
                                style="width:100%;padding:.65rem .85rem;border:1.5px solid var(--border-color);border-radius:10px;background:var(--surface-bg);color:var(--text-muted);font-size:.9rem;font-family:inherit;cursor:not-allowed;">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                        <div>
                            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.03em;">Correo electrónico</label>
                            <input type="email" value="{{ $user->email }}" disabled
                                style="width:100%;padding:.65rem .85rem;border:1.5px solid var(--border-color);border-radius:10px;background:var(--surface-bg);color:var(--text-muted);font-size:.9rem;font-family:inherit;cursor:not-allowed;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-color);margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.03em;">Teléfono</label>
                            <input type="text" name="telefono" value="{{ old('telefono', $user->telefono) }}" placeholder="+56 9 1234 5678"
                                style="width:100%;padding:.65rem .85rem;border:1.5px solid var(--border-color);border-radius:10px;background:var(--bg-color);color:var(--text-color);font-size:.9rem;font-family:inherit;outline:none;transition:border-color .2s;">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                        <div>
                            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-color);margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.03em;">Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', $user->fecha_nacimiento ? \Carbon\Carbon::parse($user->fecha_nacimiento)->format('Y-m-d') : '') }}"
                                style="width:100%;padding:.65rem .85rem;border:1.5px solid var(--border-color);border-radius:10px;background:var(--bg-color);color:var(--text-color);font-size:.9rem;font-family:inherit;outline:none;transition:border-color .2s;">
                        </div>
                        <div></div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">
                        <div>
                            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.03em;">Fecha de ingreso</label>
                            <input type="text" value="{{ $user->fecha_ingreso ? \Carbon\Carbon::parse($user->fecha_ingreso)->format('d/m/Y') : '—' }}" disabled
                                style="width:100%;padding:.65rem .85rem;border:1.5px solid var(--border-color);border-radius:10px;background:var(--surface-bg);color:var(--text-muted);font-size:.9rem;font-family:inherit;cursor:not-allowed;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.03em;">Tipo nómina</label>
                            <input type="text" value="{{ $user->tipo_nomina ?? '—' }}" disabled
                                style="width:100%;padding:.65rem .85rem;border:1.5px solid var(--border-color);border-radius:10px;background:var(--surface-bg);color:var(--text-muted);font-size:.9rem;font-family:inherit;cursor:not-allowed;">
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <p style="font-size:.8rem;color:var(--text-muted);margin:0;">
                            <i class="bi bi-info-circle"></i> Los campos deshabilitados solo pueden ser modificados por un administrador.
                        </p>
                        <button type="submit" class="btn-primary" style="padding:.6rem 1.5rem;border-radius:10px;border:none;background:var(--primary-color);color:#fff;font-size:.9rem;font-weight:600;font-family:inherit;cursor:pointer;transition:all .2s;">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>

            {{-- Cambiar Contraseña --}}
            <div class="glass-card">
                <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:.75rem;">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(245,158,11,0.1);display:flex;align-items:center;justify-content:center;color:#f59e0b;font-size:1.1rem;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:1rem;font-weight:600;color:var(--text-color);">Cambiar Contraseña</h3>
                        <p style="margin:0;font-size:.8rem;color:var(--text-muted);">Actualiza tu contraseña de acceso</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('perfil.password') }}" style="padding:1.5rem;">
                    @csrf
                    @method('PUT')

                    <div style="margin-bottom:1rem;">
                        <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-color);margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.03em;">Contraseña actual</label>
                        <div style="position:relative;">
                            <input type="password" name="current_password" required id="current_password"
                                style="width:100%;padding:.65rem .85rem;padding-right:2.5rem;border:1.5px solid var(--border-color);border-radius:10px;background:var(--bg-color);color:var(--text-color);font-size:.9rem;font-family:inherit;outline:none;">
                            <button type="button" onclick="togglePw('current_password')" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">
                        <div>
                            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-color);margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.03em;">Nueva contraseña</label>
                            <div style="position:relative;">
                                <input type="password" name="password" required id="new_password" minlength="8"
                                    style="width:100%;padding:.65rem .85rem;padding-right:2.5rem;border:1.5px solid var(--border-color);border-radius:10px;background:var(--bg-color);color:var(--text-color);font-size:.9rem;font-family:inherit;outline:none;">
                                <button type="button" onclick="togglePw('new_password')" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label style="display:block;font-size:.8rem;font-weight:600;color:var(--text-color);margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.03em;">Confirmar contraseña</label>
                            <div style="position:relative;">
                                <input type="password" name="password_confirmation" required id="confirm_password"
                                    style="width:100%;padding:.65rem .85rem;padding-right:2.5rem;border:1.5px solid var(--border-color);border-radius:10px;background:var(--bg-color);color:var(--text-color);font-size:.9rem;font-family:inherit;outline:none;">
                                <button type="button" onclick="togglePw('confirm_password')" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Requisitos de contraseña --}}
                    <div style="background:var(--surface-bg);border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;">
                        <p style="font-size:.8rem;font-weight:600;color:var(--text-color);margin:0 0 .5rem;">Requisitos de la contraseña:</p>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem;">
                            <span id="req-length" style="font-size:.8rem;color:var(--text-muted);display:flex;align-items:center;gap:.35rem;">
                                <i class="bi bi-circle" style="font-size:.5rem;"></i> Mínimo 8 caracteres
                            </span>
                            <span id="req-upper" style="font-size:.8rem;color:var(--text-muted);display:flex;align-items:center;gap:.35rem;">
                                <i class="bi bi-circle" style="font-size:.5rem;"></i> Una letra mayúscula
                            </span>
                            <span id="req-lower" style="font-size:.8rem;color:var(--text-muted);display:flex;align-items:center;gap:.35rem;">
                                <i class="bi bi-circle" style="font-size:.5rem;"></i> Una letra minúscula
                            </span>
                            <span id="req-number" style="font-size:.8rem;color:var(--text-muted);display:flex;align-items:center;gap:.35rem;">
                                <i class="bi bi-circle" style="font-size:.5rem;"></i> Un número
                            </span>
                        </div>
                    </div>

                    <div style="text-align:right;">
                        <button type="submit" class="btn-primary" style="padding:.6rem 1.5rem;border-radius:10px;border:none;background:#f59e0b;color:#fff;font-size:.9rem;font-weight:600;font-family:inherit;cursor:pointer;transition:all .2s;">
                            <i class="bi bi-shield-check"></i> Actualizar contraseña
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function togglePw(id) {
    const input = document.getElementById(id);
    const icon = input.nextElementSibling.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

const pwInput = document.getElementById('new_password');
if (pwInput) {
    pwInput.addEventListener('input', function() {
        const v = this.value;
        check('req-length', v.length >= 8);
        check('req-upper', /[A-Z]/.test(v));
        check('req-lower', /[a-z]/.test(v));
        check('req-number', /[0-9]/.test(v));
    });
}

function check(id, ok) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.color = ok ? '#10b981' : 'var(--text-muted)';
    el.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
    el.querySelector('i').style.fontSize = ok ? '.75rem' : '.5rem';
}
</script>
@endpush

@push('styles')
<style>
    @media (max-width: 960px) {
        .page-container > div:last-child {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endpush
