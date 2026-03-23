<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión — SAEP Platform</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            border-radius: 24px;
            background: var(--surface-color);
            border: 1px solid var(--surface-border);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            box-shadow: var(--glass-shadow);
        }
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .login-logo .logo-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--primary-color), #ec4899);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.5rem;
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.4);
        }
        .login-logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .login-subtitle {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: 1px solid var(--surface-border);
            background: rgba(255,255,255,0.05);
            color: var(--text-main);
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.15);
        }
        .form-input.is-invalid {
            border-color: #ef4444;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper .bi {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
        }
        .input-wrapper .form-input {
            padding-right: 2.75rem;
        }
        .error-msg {
            color: #ef4444;
            font-size: 0.8rem;
            margin-top: 0.35rem;
        }
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .form-check input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
        }
        .form-check label {
            font-size: 0.875rem;
            color: var(--text-muted);
            cursor: pointer;
        }
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            letter-spacing: 0.02em;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(79,70,229,0.35);
        }
        .global-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: #ef4444;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="bg-blobs"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <div class="logo-icon">S</div>
                <h1>SAEP Platform</h1>
            </div>
            <p class="login-subtitle">Ingresa tus credenciales para continuar</p>

            @if ($errors->any())
                <div class="global-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <div class="input-wrapper">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input @error('email') is-invalid @enderror"
                            value="{{ old('email') }}"
                            placeholder="nombre@empresa.cl"
                            autocomplete="email"
                            autofocus
                        >
                        <i class="bi bi-envelope"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="••••••••"
                            autocomplete="current-password"
                        >
                        <i class="bi bi-eye-slash" id="toggle-password" style="cursor:pointer;"></i>
                    </div>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Recordarme</label>
                </div>

                <button type="submit" class="btn-login">
                    Iniciar sesión
                </button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('toggle-password').addEventListener('click', function () {
            const input = document.getElementById('password');
            if (input.type === 'password') {
                input.type = 'text';
                this.className = 'bi bi-eye';
            } else {
                input.type = 'password';
                this.className = 'bi bi-eye-slash';
            }
        });
    </script>
</body>
</html>
