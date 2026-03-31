<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SAEP Platform')</title>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Scripts & Styles via Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="antialiased">
    <!-- Animated Background -->
    <div class="bg-blobs"></div>

    <!-- Mobile overlay -->
    <div id="sidebar-overlay" style="display:none;"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP" class="logo-img">
                <img src="https://saep.cl/wp-content/uploads/2023/11/Logo-Saep_footer.svg" alt="SAEP" class="logo-img-collapsed">
            </div>
            <button class="toggle-btn" id="sidebar-toggle">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-fill"></i>
                <span>Panel Principal</span>
            </a>

            {{-- SOLICITUDES --}}
            @if(auth()->user()->tieneAcceso('respuestas') || auth()->user()->tieneAcceso('formularios') || auth()->user()->tieneAcceso('categorias_formularios'))
            <div class="nav-section-label">Solicitudes</div>
            @if(auth()->user()->tieneAcceso('respuestas'))
            <a href="{{ route('respuestas.index') }}" class="nav-item {{ request()->routeIs('respuestas.*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span>Mis Solicitudes</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('formularios'))
            <a href="{{ route('formularios.index') }}" class="nav-item {{ request()->routeIs('formularios.*') ? 'active' : '' }}">
                <i class="bi bi-ui-checks"></i>
                <span>Formularios</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('categorias_formularios'))
            <a href="{{ route('categorias-formularios.index') }}" class="nav-item {{ request()->routeIs('categorias-formularios.*') ? 'active' : '' }}">
                <i class="bi bi-tag-fill"></i>
                <span>Categorías</span>
            </a>
            @endif
            @endif

            {{-- SST --}}
            @if(auth()->user()->tieneAcceso('kizeo_analytics') || auth()->user()->tieneAcceso('charlas') || auth()->user()->tieneAcceso('carta_gantt') || auth()->user()->tieneAcceso('visitas_sst') || auth()->user()->tieneAcceso('auditorias_sst') || auth()->user()->tieneAcceso('accidentes_sst') || auth()->user()->tieneAcceso('ley_karin') || auth()->user()->tieneAcceso('ley_karin_denuncia'))
            <div class="nav-section-label">Prevención SST</div>
            @if(auth()->user()->tieneAcceso('kizeo_analytics'))
            <a href="{{ route('kizeo.dashboard') }}" class="nav-item {{ request()->routeIs('kizeo.*') ? 'active' : '' }}">
                <i class="bi bi-activity"></i>
                <span>Kizeo Analytics</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('charlas'))
            <a href="{{ route('charlas.index') }}" class="nav-item {{ request()->routeIs('charlas.*') ? 'active' : '' }}">
                <i class="bi bi-mic-fill"></i>
                <span>Charlas SST</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('carta_gantt'))
            <a href="{{ route('carta-gantt.index') }}" class="nav-item {{ request()->routeIs('carta-gantt.*') ? 'active' : '' }}">
                <i class="bi bi-kanban-fill"></i>
                <span>Carta Gantt</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('visitas_sst'))
            <a href="{{ route('visitas-sst.index') }}" class="nav-item {{ request()->routeIs('visitas-sst.*') ? 'active' : '' }}">
                <i class="bi bi-binoculars-fill"></i>
                <span>Visitas / Inspecciones</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('auditorias_sst'))
            <a href="{{ route('auditorias-sst.index') }}" class="nav-item {{ request()->routeIs('auditorias-sst.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard2-check-fill"></i>
                <span>Auditorías</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('accidentes_sst'))
            <a href="{{ route('accidentes-sst.index') }}" class="nav-item {{ request()->routeIs('accidentes-sst.*') ? 'active' : '' }}">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Accidentes</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('ley_karin'))
            <a href="{{ route('ley-karin.index') }}" class="nav-item {{ request()->routeIs('ley-karin.*') ? 'active' : '' }}">
                <i class="bi bi-shield-fill-exclamation"></i>
                <span>Ley Karin</span>
            </a>
            @elseif(auth()->user()->tieneAcceso('ley_karin_denuncia'))
            <a href="{{ route('ley-karin.denuncia') }}" class="nav-item {{ request()->routeIs('ley-karin.denuncia*') || request()->routeIs('ley-karin.confirmacion') ? 'active' : '' }}">
                <i class="bi bi-shield-fill-exclamation"></i>
                <span>Canal de Denuncia</span>
            </a>
            @endif
            @endif

            {{-- ADMINISTRACIÓN --}}
            @if(auth()->user()->tieneAcceso('usuarios') || auth()->user()->tieneAcceso('departamentos') || auth()->user()->tieneAcceso('cargos') || auth()->user()->tieneAcceso('centros_costo'))
            <div class="nav-section-label">Administración</div>
            @if(auth()->user()->tieneAcceso('usuarios'))
            <a href="{{ route('usuarios.index') }}" class="nav-item {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
                <i class="bi bi-people-fill"></i>
                <span>Usuarios</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('departamentos'))
            <a href="{{ route('departamentos.index') }}" class="nav-item {{ request()->routeIs('departamentos.*') ? 'active' : '' }}">
                <i class="bi bi-building"></i>
                <span>Departamentos</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('cargos'))
            <a href="{{ route('cargos.index') }}" class="nav-item {{ request()->routeIs('cargos.*') ? 'active' : '' }}">
                <i class="bi bi-person-badge-fill"></i>
                <span>Cargos</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('centros_costo'))
            <a href="{{ route('centros-costo.index') }}" class="nav-item {{ request()->routeIs('centros-costo.*') ? 'active' : '' }}">
                <i class="bi bi-geo-alt-fill"></i>
                <span>Centros de Costo</span>
            </a>
            @endif
            @endif

            {{-- SISTEMA --}}
            @if(auth()->user()->tieneAcceso('configuracion') || auth()->user()->tieneAcceso('permisos') || auth()->user()->tieneAcceso('importacion'))
            <div class="nav-section-label">Sistema</div>
            @if(auth()->user()->tieneAcceso('configuracion'))
            <a href="{{ route('configuraciones.index') }}" class="nav-item {{ request()->routeIs('configuraciones.*') ? 'active' : '' }}">
                <i class="bi bi-gear-fill"></i>
                <span>Configuración</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('permisos'))
            <a href="{{ route('permisos.index') }}" class="nav-item {{ request()->routeIs('permisos.*') ? 'active' : '' }}">
                <i class="bi bi-key-fill"></i>
                <span>Permisos por Rol</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('importacion'))
            <a href="{{ route('importacion.index') }}" class="nav-item {{ request()->routeIs('importacion.*') ? 'active' : '' }}">
                <i class="bi bi-cloud-upload-fill"></i>
                <span>Importar Datos</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('configuracion'))
            <a href="{{ route('webhook-logs.index') }}" class="nav-item {{ request()->routeIs('webhook-logs.*') ? 'active' : '' }}">
                <i class="bi bi-activity"></i>
                <span>Webhooks Log</span>
            </a>
            @endif
            @endif

            {{-- PROTECCIÓN DE DATOS (Ley 21.719) --}}
            @if(auth()->user()->tieneAcceso('proteccion_datos'))
            <div class="nav-section-label">Protección de Datos</div>
            <a href="{{ route('proteccion-datos.index') }}" class="nav-item {{ request()->routeIs('proteccion-datos.index') || request()->routeIs('proteccion-datos.crear-solicitud') || request()->routeIs('proteccion-datos.ver-solicitud') ? 'active' : '' }}">
                <i class="bi bi-shield-lock-fill"></i>
                <span>Mis Derechos ARCO</span>
            </a>
            @if(auth()->user()->tieneAcceso('proteccion_datos', 'puede_editar'))
            <a href="{{ route('proteccion-datos.administrar') }}" class="nav-item {{ request()->routeIs('proteccion-datos.administrar') ? 'active' : '' }}">
                <i class="bi bi-shield-check"></i>
                <span>Gestión Solicitudes</span>
            </a>
            <a href="{{ route('proteccion-datos.registro-tratamiento') }}" class="nav-item {{ request()->routeIs('proteccion-datos.registro-tratamiento') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i>
                <span>Registro Tratamiento</span>
            </a>
            @endif
            @endif

            {{-- DOCUMENTACIÓN --}}
            @if(auth()->user()->tieneAcceso('documentacion'))
            <div class="nav-section-label">Ayuda</div>
            <a href="{{ route('documentacion.index') }}" class="nav-item {{ request()->routeIs('documentacion.*') ? 'active' : '' }}">
                <i class="bi bi-book-fill"></i>
                <span>Documentación</span>
            </a>
            @endif

            {{-- NOTAS PERSONALES --}}
            @if(auth()->user()->tieneAcceso('notas_personales'))
            <div class="nav-section-label">Mis Herramientas</div>
            <a href="{{ route('notas.index') }}" class="nav-item {{ request()->routeIs('notas.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i>
                <span>Notas por Voz</span>
            </a>
            @endif
        </nav>

        <div class="user-profile">
            <div class="avatar">
                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}{{ strtoupper(substr(strstr(auth()->user()->name ?? ' X', ' '), 1, 1)) }}
            </div>
            <div class="user-info">
                <span class="user-name">{{ auth()->user()->name ?? 'Usuario' }}</span>
                <span class="user-role">{{ auth()->user()->rol->nombre ?? 'Sin Rol' }}</span>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" style="padding: 0 1rem 1rem;">
            @csrf
            <button type="submit" class="nav-item" style="width:100%; border:none; background:none; cursor:pointer; color: #ef4444;">
                <i class="bi bi-box-arrow-right" style="color: #ef4444;"></i>
                <span>Cerrar Sesión</span>
            </button>
        </form>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <button class="icon-btn mobile-menu-btn" id="mobile-menu-trigger" style="display:none;">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title">@yield('title', 'Dashboard')</h1>
            </div>
            <div class="header-actions">
                <button class="icon-btn" id="dark-mode-toggle">
                    <i class="bi bi-moon-fill"></i>
                </button>
                <button class="icon-btn">
                    <i class="bi bi-bell-fill"></i>
                </button>
            </div>
        </header>

        <!-- Page Content -->
        @yield('content')
    </main>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav" id="mobile-bottom-nav">
        <div class="bottom-nav-items">
            <a href="{{ route('dashboard') }}" class="bottom-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-fill"></i>
                <span>Inicio</span>
            </a>
            <a href="{{ route('respuestas.index') }}" class="bottom-nav-item {{ request()->routeIs('respuestas.*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span>Solicitudes</span>
            </a>
            <a href="{{ route('charlas.index') }}" class="bottom-nav-item {{ request()->routeIs('charlas.*') ? 'active' : '' }}">
                <i class="bi bi-mic-fill"></i>
                <span>Charlas</span>
            </a>
            <a href="{{ route('proteccion-datos.index') }}" class="bottom-nav-item {{ request()->routeIs('proteccion-datos.*') ? 'active' : '' }}">
                <i class="bi bi-shield-lock-fill"></i>
                <span>ARCO</span>
            </a>
            <button class="bottom-nav-item menu-toggle-btn" id="mobile-nav-menu-btn">
                <i class="bi bi-three-dots"></i>
                <span>Más</span>
            </button>
        </div>
    </nav>

    @stack('scripts')
</body>
</html>
