<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso denegado | SAEP</title>
    <link rel="icon" href="{{ asset('brand/wp/saep_favicon.svg') }}" type="image/svg+xml">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #102033;
            background:
                linear-gradient(rgba(12, 26, 51, .72), rgba(12, 26, 51, .82)),
                url("{{ asset('brand/wp/banner-vitrina-1520x800.jpg') }}") center / cover no-repeat,
                #0b1437;
        }
        .error-card {
            width: min(100%, 460px);
            padding: 2rem;
            border-radius: 16px;
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 24px 70px rgba(0, 0, 0, .28);
            text-align: center;
        }
        .brand {
            height: 48px;
            max-width: 180px;
            object-fit: contain;
            margin-bottom: 1.25rem;
        }
        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 68px;
            height: 68px;
            border-radius: 999px;
            margin-bottom: 1rem;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 1.45rem;
            font-weight: 800;
        }
        h1 {
            margin: 0 0 .5rem;
            font-size: 1.45rem;
            line-height: 1.2;
        }
        p {
            margin: 0;
            color: #526174;
            line-height: 1.6;
            font-size: .95rem;
        }
        .actions {
            display: flex;
            justify-content: center;
            gap: .75rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: .7rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: .9rem;
        }
        .btn-primary {
            background: #0b1437;
            color: #fff;
        }
        .btn-secondary {
            background: #eef2f7;
            color: #344256;
        }
    </style>
</head>
<body>
    <main class="error-card" role="main">
        <img class="brand" src="{{ asset('brand/wp/Logo_Saep.svg') }}" alt="SAEP">
        <div class="status">403</div>
        <h1>Acceso denegado</h1>
        <p>{{ $exception->getMessage() ?: 'El enlace no es valido, expiro o no tienes permisos para acceder a esta informacion.' }}</p>

        <div class="actions">
            @auth
                <a class="btn btn-primary" href="{{ route('dashboard') }}">Volver al panel</a>
            @else
                <a class="btn btn-primary" href="{{ route('login') }}">Ir al acceso</a>
            @endauth
            <a class="btn btn-secondary" href="{{ url()->previous() }}">Volver</a>
        </div>
    </main>
</body>
</html>
