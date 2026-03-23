<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                <div class="logo-icon">S</div>
                <span class="logo-text">SAEP Platform</span>
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
            <div class="nav-section-label">Solicitudes</div>
            <a href="{{ route('respuestas.index') }}" class="nav-item {{ request()->routeIs('respuestas.*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span>Mis Solicitudes</span>
            </a>
            <a href="{{ route('formularios.index') }}" class="nav-item {{ request()->routeIs('formularios.*') ? 'active' : '' }}">
                <i class="bi bi-ui-checks"></i>
                <span>Formularios</span>
            </a>
            <a href="{{ route('categorias-formularios.index') }}" class="nav-item {{ request()->routeIs('categorias-formularios.*') ? 'active' : '' }}">
                <i class="bi bi-tag-fill"></i>
                <span>Categorías</span>
            </a>

            {{-- SST --}}
            <div class="nav-section-label">Prevención SST</div>
            <a href="{{ route('charlas.index') }}" class="nav-item {{ request()->routeIs('charlas.*') ? 'active' : '' }}">
                <i class="bi bi-mic-fill"></i>
                <span>Charlas SST</span>
            </a>
            <a href="{{ route('carta-gantt.index') }}" class="nav-item {{ request()->routeIs('carta-gantt.*') ? 'active' : '' }}">
                <i class="bi bi-kanban-fill"></i>
                <span>Carta Gantt</span>
            </a>
            <a href="{{ route('visitas-sst.index') }}" class="nav-item {{ request()->routeIs('visitas-sst.*') ? 'active' : '' }}">
                <i class="bi bi-binoculars-fill"></i>
                <span>Visitas / Inspecciones</span>
            </a>
            <a href="{{ route('auditorias-sst.index') }}" class="nav-item {{ request()->routeIs('auditorias-sst.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard2-check-fill"></i>
                <span>Auditorías</span>
            </a>
            <a href="{{ route('accidentes-sst.index') }}" class="nav-item {{ request()->routeIs('accidentes-sst.*') ? 'active' : '' }}">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Accidentes</span>
            </a>
            <a href="{{ route('ley-karin.index') }}" class="nav-item {{ request()->routeIs('ley-karin.*') ? 'active' : '' }}">
                <i class="bi bi-shield-fill-exclamation"></i>
                <span>Ley Karin</span>
            </a>

            {{-- MAESTROS --}}
            <div class="nav-section-label">Administración</div>
            <a href="{{ route('usuarios.index') }}" class="nav-item {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
                <i class="bi bi-people-fill"></i>
                <span>Usuarios</span>
            </a>
            <a href="{{ route('departamentos.index') }}" class="nav-item {{ request()->routeIs('departamentos.*') ? 'active' : '' }}">
                <i class="bi bi-building"></i>
                <span>Departamentos</span>
            </a>
            <a href="{{ route('cargos.index') }}" class="nav-item {{ request()->routeIs('cargos.*') ? 'active' : '' }}">
                <i class="bi bi-person-badge-fill"></i>
                <span>Cargos</span>
            </a>
            <a href="{{ route('centros-costo.index') }}" class="nav-item {{ request()->routeIs('centros-costo.*') ? 'active' : '' }}">
                <i class="bi bi-geo-alt-fill"></i>
                <span>Centros de Costo</span>
            </a>
            <a href="{{ route('configuraciones.index') }}" class="nav-item {{ request()->routeIs('configuraciones.*') ? 'active' : '' }}">
                <i class="bi bi-gear-fill"></i>
                <span>Configuración</span>
            </a>
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
            <h1 class="page-title">@yield('title', 'Dashboard')</h1>
            <div class="header-actions">
                <button class="icon-btn" id="dark-mode-toggle">
                    <i class="bi bi-moon-fill"></i>
                </button>
                <button class="icon-btn">
                    <i class="bi bi-bell-fill"></i>
                </button>
                <button class="btn-premium" onclick="window.location='#'">
                    <i class="bi bi-plus-lg"></i> Nueva Solicitud
                </button>
            </div>
        </header>

        <!-- Page Content -->
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
