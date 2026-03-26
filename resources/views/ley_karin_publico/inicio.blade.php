<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Canal de Denuncia — Ley Karin</title>
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
            overflow-x: hidden;
        }

        .page-split { display: flex; min-height: 100vh; }

        /* Left: Hero */
        .hero-side {
            flex: 1 1 50%;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 4rem;
            overflow: hidden;
        }
        .hero-side__bg {
            position: absolute; inset: 0;
            background: linear-gradient(135deg, #0b1437 0%, #1a237e 50%, #dc2626 100%);
        }
        .hero-side__overlay {
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .hero-content { position: relative; z-index: 2; }
        .hero-content .logo { height: 50px; margin-bottom: 2.5rem; filter: brightness(0) invert(1); }
        .hero-content h1 {
            font-size: 2.2rem; font-weight: 800; color: #fff;
            line-height: 1.2; margin-bottom: 1rem;
        }
        .hero-content h1 span { color: #f87171; }
        .hero-content p { font-size: 1rem; color: rgba(255,255,255,.7); line-height: 1.7; max-width: 480px; }

        .legal-badges {
            display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 2rem;
        }
        .legal-badge {
            display: flex; align-items: center; gap: .5rem;
            background: rgba(255,255,255,.08); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.12); border-radius: 10px;
            padding: .6rem 1rem; color: rgba(255,255,255,.85); font-size: .82rem;
        }
        .legal-badge i { font-size: 1rem; }

        /* Right: Login card */
        .form-side {
            flex: 1 1 50%;
            display: flex; align-items: center; justify-content: center;
            background: #f8fafc;
            padding: 2rem;
        }
        .login-card {
            width: 100%; max-width: 480px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(0,0,0,.08);
            padding: 3rem 2.5rem;
        }
        .login-card__icon {
            width: 64px; height: 64px; border-radius: 16px;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .login-card__icon i { font-size: 1.8rem; color: #fff; }
        .login-card h2 {
            text-align: center; font-size: 1.4rem; font-weight: 700;
            color: #0f1b4c; margin-bottom: .5rem;
        }
        .login-card__sub {
            text-align: center; font-size: .9rem; color: #6b7280;
            margin-bottom: 2rem; line-height: 1.6;
        }

        /* Google Button */
        .btn-google {
            display: flex; align-items: center; justify-content: center; gap: .75rem;
            width: 100%; padding: .9rem 1.5rem;
            background: #fff; color: #1e293b;
            border: 2px solid #e2e8f0; border-radius: 12px;
            font-size: .95rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
            text-decoration: none;
        }
        .btn-google:hover {
            border-color: #4285f4; background: #f0f7ff;
            box-shadow: 0 4px 16px rgba(66,133,244,.15);
        }
        .btn-google svg { width: 22px; height: 22px; flex-shrink: 0; }

        .divider {
            display: flex; align-items: center; gap: 1rem;
            margin: 1.5rem 0; color: #9ca3af; font-size: .8rem;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: #e5e7eb;
        }

        .info-box {
            background: #f0f4ff; border-left: 3px solid #0f1b4c;
            border-radius: 8px; padding: 1rem 1.15rem; margin-top: 1.5rem;
        }
        .info-box h4 { font-size: .82rem; color: #0f1b4c; margin-bottom: .4rem; display: flex; align-items: center; gap: .4rem; }
        .info-box p { font-size: .8rem; color: #4b5563; line-height: 1.6; margin: 0; }

        .alert-error {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;
            padding: .85rem 1rem; margin-bottom: 1.25rem;
            display: flex; align-items: center; gap: .5rem;
            font-size: .85rem; color: #991b1b;
        }

        .footer-note {
            text-align: center; margin-top: 1.5rem;
            font-size: .75rem; color: #9ca3af; line-height: 1.6;
        }

        @media (max-width: 900px) {
            .page-split { flex-direction: column; }
            .hero-side { flex: none; padding: 1.5rem; min-height: auto; }
            .hero-content .logo { height: 36px; margin-bottom: 1.25rem; }
            .hero-content h1 { font-size: 1.4rem; }
            .hero-content p { font-size: .85rem; }
            .legal-badges { gap: .5rem; margin-top: 1.25rem; }
            .legal-badge { font-size: .72rem; padding: .45rem .7rem; }
            .form-side { padding: 1rem; }
            .login-card { padding: 1.75rem 1.25rem; border-radius: 16px; }
            .login-card__icon { width: 52px; height: 52px; border-radius: 14px; margin-bottom: 1rem; }
            .login-card__icon i { font-size: 1.4rem; }
            .login-card h2 { font-size: 1.15rem; }
            .login-card__sub { font-size: .82rem; margin-bottom: 1.5rem; }
            .btn-google { padding: .75rem 1rem; font-size: .88rem; }
            .info-box { padding: .85rem 1rem; }
            .info-box h4 { font-size: .78rem; }
            .info-box p { font-size: .75rem; }
        }

        @media (max-width: 480px) {
            .hero-side { padding: 1.25rem 1rem; }
            .hero-content .logo { height: 30px; margin-bottom: 1rem; }
            .hero-content h1 { font-size: 1.2rem; }
            .hero-content p { font-size: .8rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
            .legal-badges { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .legal-badge { flex-shrink: 0; font-size: .68rem; padding: .4rem .6rem; }
            .form-side { padding: .75rem; }
            .login-card { padding: 1.5rem 1rem; }
            .login-card h2 { font-size: 1.05rem; }
            .footer-note { font-size: .7rem; }
        }
    </style>
</head>
<body>
<div class="page-split">
    <!-- Left: Hero -->
    <div class="hero-side">
        <div class="hero-side__bg"></div>
        <div class="hero-side__overlay"></div>
        <div class="hero-content">
            <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP" class="logo">
            <h1>Canal de Denuncia<br><span>Ley Karin</span></h1>
            <p>
                Plataforma segura para denunciar acoso laboral, sexual o violencia en el trabajo,
                conforme a la Ley 21.643. Tu identidad está protegida y la denuncia será tratada
                con estricta confidencialidad.
            </p>
            <div class="legal-badges">
                <div class="legal-badge"><i class="bi bi-shield-lock-fill"></i> Confidencialidad garantizada</div>
                <div class="legal-badge"><i class="bi bi-file-earmark-lock2-fill"></i> Ley 21.643</div>
                <div class="legal-badge"><i class="bi bi-patch-check-fill"></i> Datos protegidos</div>
            </div>
        </div>
    </div>

    <!-- Right: Login -->
    <div class="form-side">
        <div class="login-card">
            <div class="login-card__icon">
                <i class="bi bi-megaphone-fill"></i>
            </div>
            <h2>Accede al Formulario de Denuncia</h2>
            <p class="login-card__sub">
                Para garantizar la veracidad y trazabilidad de tu denuncia,
                es necesario verificar tu identidad a través de una cuenta de Google.
            </p>

            @if(session('error'))
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                {{ session('error') }}
            </div>
            @endif

            <a href="{{ route('ley-karin-publico.google') }}" class="btn-google">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Iniciar sesión con Google
            </a>

            <div class="divider">Tu correo se detectará automáticamente</div>

            <div class="info-box">
                <h4><i class="bi bi-info-circle-fill"></i> ¿Por qué Google?</h4>
                <p>
                    Verificamos tu correo electrónico para asegurar la autenticidad de la denuncia
                    y poder contactarte sobre el avance de tu caso. No accedemos a ninguna otra
                    información de tu cuenta.
                </p>
            </div>

            <div class="info-box" style="background:#fef2f2;border-color:#dc2626;">
                <h4 style="color:#dc2626;"><i class="bi bi-shield-check"></i> Tus Derechos</h4>
                <p>
                    La Ley 21.643 prohíbe cualquier represalia contra el denunciante.
                    Tu denuncia será investigada en un plazo máximo de 30 días hábiles.
                    También puedes denunciar directamente ante la
                    <strong>Inspección del Trabajo</strong>.
                </p>
            </div>

            <p class="footer-note">
                <i class="bi bi-lock-fill"></i> Conexión segura · Datos protegidos conforme a la Ley 21.719<br>
                &copy; {{ date('Y') }} SAEP Platform. Todos los derechos reservados.
            </p>
        </div>
    </div>
</div>
</body>
</html>
