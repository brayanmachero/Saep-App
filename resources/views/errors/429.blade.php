<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demasiados intentos — SAEP Platform</title>
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

        .login-split {
            display: flex;
            min-height: 100vh;
        }

        /* ── LEFT: Hero ── */
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

        /* ── RIGHT: Panel ── */
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

        .error-content {
            width: 100%;
            max-width: 360px;
            text-align: center;
        }

        .error-icon {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: #fef2f2;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: #ef4444;
            font-size: 2.5rem;
            animation: pulse-icon 2s ease-in-out infinite;
        }
        @keyframes pulse-icon {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2); }
            50% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); }
        }

        .error-code {
            font-size: 3rem;
            font-weight: 800;
            color: #0f1b4c;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .error-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f1b4c;
            margin-bottom: 0.75rem;
        }
        .error-message {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        /* ── Countdown ── */
        .countdown-box {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.75rem;
        }
        .countdown-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .countdown-timer {
            font-size: 2rem;
            font-weight: 700;
            color: #f97316;
            font-variant-numeric: tabular-nums;
        }
        .countdown-progress {
            width: 100%;
            height: 4px;
            background: #e2e8f0;
            border-radius: 4px;
            margin-top: 0.75rem;
            overflow: hidden;
        }
        .countdown-progress__bar {
            height: 100%;
            background: linear-gradient(90deg, #0f1b4c, #f97316);
            border-radius: 4px;
            width: 100%;
            transition: width 1s linear;
        }

        .btn-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
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
            text-decoration: none;
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
        .btn-login:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
            .error-code { font-size: 2.5rem; }
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

        {{-- RIGHT: Error --}}
        <div class="login-form-side">
            <div class="error-content">
                <div class="error-icon">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>

                <div class="error-code">429</div>
                <h1 class="error-title">Demasiados intentos</h1>
                <p class="error-message">
                    Has superado el número máximo de intentos de inicio de sesión.
                    Por seguridad, tu acceso ha sido bloqueado temporalmente.
                </p>

                <div class="countdown-box">
                    <div class="countdown-label">Podrás intentar nuevamente en</div>
                    <div class="countdown-timer" id="countdown">01:00</div>
                    <div class="countdown-progress">
                        <div class="countdown-progress__bar" id="progressBar"></div>
                    </div>
                </div>

                <a href="{{ url('/login') }}" class="btn-login" id="retryBtn" disabled>
                    <i class="bi bi-arrow-clockwise"></i>
                    <span id="retryText">Espera para intentar de nuevo</span>
                </a>

                <div class="login-footer">
                    <p>&copy; {{ date('Y') }} SAEP — Todos los derechos reservados</p>
                    <div class="login-footer__links">
                        <a href="https://www.linkedin.com/company/saep" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="https://www.instagram.com/saep_chile" target="_blank" title="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="https://saep.cl" target="_blank" title="Sitio web"><i class="bi bi-globe2"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const totalSeconds = 60;
            let remaining = totalSeconds;
            const countdownEl = document.getElementById('countdown');
            const progressBar = document.getElementById('progressBar');
            const retryBtn = document.getElementById('retryBtn');
            const retryText = document.getElementById('retryText');

            function pad(n) { return n.toString().padStart(2, '0'); }

            function update() {
                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                countdownEl.textContent = pad(mins) + ':' + pad(secs);
                progressBar.style.width = ((remaining / totalSeconds) * 100) + '%';

                if (remaining <= 0) {
                    clearInterval(timer);
                    countdownEl.textContent = '00:00';
                    progressBar.style.width = '0%';
                    retryBtn.removeAttribute('disabled');
                    retryBtn.style.opacity = '1';
                    retryBtn.style.cursor = 'pointer';
                    retryText.textContent = 'Volver a iniciar sesión';
                }
                remaining--;
            }

            update();
            const timer = setInterval(update, 1000);
        })();
    </script>
</body>
</html>
