<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SAEP Platform</title>
    <link rel="icon" href="{{ asset('brand/wp/saep_favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #fff;
            background: #0f1b4c;
        }
        .brand-hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background:
                linear-gradient(90deg, rgba(15, 27, 76, .92), rgba(15, 27, 76, .68)),
                url("{{ asset('brand/wp/optimized/banner-vitrina-1520x800.webp') }}") center/cover no-repeat;
        }
        .brand-panel {
            max-width: 640px;
            text-align: center;
        }
        .brand-panel img {
            height: 58px;
            width: auto;
            margin: 0 auto 2rem;
        }
        .brand-panel h1 {
            margin: 0 0 .75rem;
            font-size: clamp(2rem, 5vw, 4rem);
            line-height: 1;
            font-weight: 800;
        }
        .brand-panel p {
            margin: 0 auto 2rem;
            max-width: 520px;
            color: rgba(255, 255, 255, .78);
            font-size: 1rem;
            line-height: 1.7;
        }
        .brand-panel a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 1.25rem;
            border-radius: 8px;
            background: #f97316;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="brand-hero">
        <section class="brand-panel" aria-label="SAEP Platform">
            <img src="{{ asset('brand/wp/Logo-Saep_footer.svg') }}" alt="SAEP">
            <h1>SAEP Platform</h1>
            <p>Gestión empresarial, prevención, seguridad y procesos documentales con activos de marca servidos localmente.</p>
            <a href="{{ route('login') }}">Ingresar</a>
        </section>
    </main>
</body>
</html>
