<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión — SAEP Platform</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0b1437;
            color: #1e293b;
            min-height: 100vh;
            overflow: hidden;
        }

        /* ── Full-screen split ── */
        .login-split {
            display: flex;
            min-height: 100vh;
        }

        /* ── LEFT: Hero con imagen de fondo ── */
        .login-hero {
            flex: 1 1 55%;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 3rem 3.5rem;
            overflow: hidden;
        }
        .login-hero__bg {
            position: absolute;
            inset: 0;
            background: url('https://saep.cl/wp-content/uploads/2023/11/banner-vitrina-1.jpg') center/cover no-repeat;
        }
        .login-hero__overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to top,
                rgba(10, 18, 50, 0.92) 0%,
                rgba(10, 18, 50, 0.6) 40%,
                rgba(10, 18, 50, 0.35) 100%
            );
        }
        .login-hero__content {
            position: relative;
            z-index: 2;
            max-width: 540px;
        }
        .login-hero__logo {
            height: 40px;
            margin-bottom: 1.75rem;
        }
        .login-hero__title {
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1.2;
            color: #ffffff;
            margin-bottom: 0.75rem;
        }
        .login-hero__title span {
            color: #f97316;
        }
        .login-hero__desc {
            font-size: 1.05rem;
            color: rgba(255,255,255,0.75);
            line-height: 1.6;
            max-width: 440px;
        }
        .login-hero__stats {
            display: flex;
            gap: 2.5rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.15);
        }
        .login-hero__stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #f97316;
            line-height: 1;
        }
        .login-hero__stat-label {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
            margin-top: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* ── RIGHT: Formulario ── */
        .login-form-side {
            flex: 0 0 42%;
            max-width: 520px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem 3.5rem;
            position: relative;
        }
        .login-form-side::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, #0f1b4c, #f97316);
        }

        .login-form {
            width: 100%;
            max-width: 360px;
        }
        .login-form__logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
        }
        .login-form__logo img {
            height: 40px;
        }
        .login-form__welcome {
            text-align: center;
            margin-bottom: 0.25rem;
        }
        .login-form__welcome h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f1b4c;
        }
        .login-form__subtitle {
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        /* ── Form elements ── */
        .field {
            margin-bottom: 1.25rem;
        }
        .field__label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .field__input-wrap {
            position: relative;
        }
        .field__input {
            width: 100%;
            padding: 0.8rem 1rem;
            padding-left: 2.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            color: #1e293b;
            background: #f8fafc;
            outline: none;
            transition: all 0.2s;
        }
        .field__input::placeholder { color: #94a3b8; }
        .field__input:focus {
            border-color: #0f1b4c;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(15, 27, 76, 0.08);
        }
        .field__input.is-invalid {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.08);
        }
        .field__icon {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            pointer-events: none;
        }
        .field__icon-right {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        .field__icon-right:hover { color: #0f1b4c; }
        .field__input:focus ~ .field__icon { color: #0f1b4c; }

        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
        }
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-check input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #0f1b4c;
            cursor: pointer;
        }
        .form-check label {
            font-size: 0.85rem;
            color: #64748b;
            cursor: pointer;
            user-select: none;
        }

        .btn-login {
            width: 100%;
            padding: 0.9rem;
            border-radius: 10px;
            border: none;
            background: #0f1b4c;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.25s;
            letter-spacing: 0.02em;
            position: relative;
            overflow: hidden;
        }
        .btn-login::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(249,115,22,0.15) 100%);
            opacity: 0;
            transition: opacity 0.25s;
        }
        .btn-login:hover {
            background: #1e3a8a;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(15, 27, 76, 0.3);
        }
        .btn-login:hover::after { opacity: 1; }
        .btn-login:active { transform: translateY(0); }

        .global-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            color: #dc2626;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .login-footer {
            text-align: center;
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }
        .login-footer p {
            color: #94a3b8;
            font-size: 0.75rem;
        }
        .login-footer__links {
            display: flex;
            justify-content: center;
            gap: 1.25rem;
            margin-top: 0.5rem;
        }
        .login-footer__links a {
            color: #94a3b8;
            font-size: 1rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        .login-footer__links a:hover { color: #0f1b4c; }

        /* ── Responsive ── */
        @media (max-width: 960px) {
            .login-hero { display: none; }
            .login-form-side {
                flex: 1;
                max-width: 100%;
                padding: 2rem;
            }
            .login-form-side::before { display: none; }
        }
        @media (max-width: 480px) {
            .login-form-side { padding: 1.5rem; }
            .login-hero__title { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
    <div class="login-split">
        {{-- LEFT: Hero con banner --}}
        <div class="login-hero">
            <div class="login-hero__bg"></div>
            <div class="login-hero__overlay"></div>
            <div class="login-hero__content">
                <img src="https://saep.cl/wp-content/uploads/2023/11/Logo-Saep_footer.svg"
                     alt="SAEP" class="login-hero__logo">
                <h2 class="login-hero__title">
                    Servicios de personal <span>a tu medida</span>
                </h2>
                <p class="login-hero__desc">
                    Plataforma integral de gestión de prevención, seguridad y salud ocupacional para potenciar tu empresa.
                </p>
                <div class="login-hero__stats">
                    <div>
                        <div class="login-hero__stat-value">13+</div>
                        <div class="login-hero__stat-label">Años de experiencia</div>
                    </div>
                    <div>
                        <div class="login-hero__stat-value">500+</div>
                        <div class="login-hero__stat-label">Trabajadores gestionados</div>
                    </div>
                    <div>
                        <div class="login-hero__stat-value">100%</div>
                        <div class="login-hero__stat-label">Compromiso SST</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Formulario --}}
        <div class="login-form-side">
            <div class="login-form">
                <div class="login-form__logo">
                    <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP">
                </div>
                <div class="login-form__welcome">
                    <h1>Bienvenido</h1>
                </div>
                <p class="login-form__subtitle">Ingresa tus credenciales para acceder</p>

                @if ($errors->any())
                    <div class="global-error">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="field">
                        <label class="field__label" for="email">Correo electrónico</label>
                        <div class="field__input-wrap">
                            <i class="bi bi-envelope field__icon"></i>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="field__input @error('email') is-invalid @enderror"
                                value="{{ old('email') }}"
                                placeholder="nombre@empresa.cl"
                                autocomplete="email"
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="field">
                        <label class="field__label" for="password">Contraseña</label>
                        <div class="field__input-wrap">
                            <i class="bi bi-lock field__icon"></i>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="field__input"
                                placeholder="••••••••"
                                autocomplete="current-password"
                            >
                            <i class="bi bi-eye-slash field__icon-right" id="toggle-password"></i>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-check">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Recordarme</label>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <span>Iniciar sesión</span>
                    </button>
                </form>

                <div class="login-footer">
                    <p>&copy; {{ date('Y') }} S.A.E.P. — Todos los derechos reservados</p>
                    <p style="margin-top: 0.4rem;"><a href="{{ route('proteccion-datos.politica-privacidad') }}" target="_blank" style="color: #64748b; text-decoration: none; font-size: 0.75rem;"><i class="bi bi-shield-check"></i> Política de Datos Personales</a></p>
                    <div class="login-footer__links">
                        <a href="https://www.linkedin.com/company/saep-ltda/" target="_blank" rel="noopener noreferrer" title="LinkedIn">
                            <i class="bi bi-linkedin"></i>
                        </a>
                        <a href="https://www.instagram.com/saep_ltda/" target="_blank" rel="noopener noreferrer" title="Instagram">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="https://saep.cl" target="_blank" rel="noopener noreferrer" title="Sitio web">
                            <i class="bi bi-globe2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('toggle-password').addEventListener('click', function () {
            const input = document.getElementById('password');
            if (input.type === 'password') {
                input.type = 'text';
                this.className = 'bi bi-eye field__icon-right';
            } else {
                input.type = 'password';
                this.className = 'bi bi-eye-slash field__icon-right';
            }
        });
    </script>
</body>
</html>
