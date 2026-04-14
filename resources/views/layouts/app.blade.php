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
            <a href="{{ route('perfil.show') }}" class="nav-item {{ request()->routeIs('perfil.*') ? 'active' : '' }}">
                <i class="bi bi-person-circle"></i>
                <span>Mi Perfil</span>
            </a>

            {{-- FORMULARIOS --}}
            <div class="nav-section-label">Formularios</div>
            <a href="{{ route('mis-formularios.index') }}" class="nav-item {{ request()->routeIs('mis-formularios.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard-check-fill"></i>
                <span>Mis Formularios</span>
            </a>
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

            {{-- SST --}}
            @if(auth()->user()->tieneAcceso('kizeo_analytics') || auth()->user()->tieneAcceso('charlas') || auth()->user()->tieneAcceso('carta_gantt') || auth()->user()->tieneAcceso('visitas_sst') || auth()->user()->tieneAcceso('auditorias_sst') || auth()->user()->tieneAcceso('accidentes_sst') || auth()->user()->tieneAcceso('ley_karin') || auth()->user()->tieneAcceso('ley_karin_denuncia'))
            <div class="nav-section-label">Prevención SST</div>
            @if(auth()->user()->tieneAcceso('kizeo_analytics'))
            <a href="{{ route('kizeo.dashboard') }}" class="nav-item {{ request()->routeIs('kizeo.*') && !request()->routeIs('charla-tracking.*') ? 'active' : '' }}">
                <i class="bi bi-activity"></i>
                <span>Kizeo Analytics</span>
            </a>
            <a href="{{ route('charla-tracking.index') }}" class="nav-item {{ request()->routeIs('charla-tracking.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard-data"></i>
                <span>Seguimiento Charlas</span>
            </a>
            <a href="{{ route('stop-dashboard') }}" class="nav-item {{ request()->routeIs('stop-dashboard*') ? 'active' : '' }}">
                <i class="bi bi-hand-index-fill"></i>
                <span>Tarjeta STOP CCU</span>
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
            @if(auth()->user()->tieneAcceso('notas_personales') || auth()->user()->tieneAcceso('kanban'))
            <div class="nav-section-label">Mis Herramientas</div>
            @if(auth()->user()->tieneAcceso('notas_personales'))
            <a href="{{ route('notas.index') }}" class="nav-item {{ request()->routeIs('notas.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i>
                <span>Notas por Voz</span>
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('kanban'))
            <a href="{{ route('kanban.index') }}" class="nav-item {{ request()->routeIs('kanban.*') ? 'active' : '' }}">
                <i class="bi bi-kanban"></i>
                <span>Tablero Kanban</span>
            </a>
            @endif
            @endif
        </nav>

        <div class="user-profile">
            <a href="{{ route('perfil.show') }}" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:.65rem;width:100%;">
                @if(auth()->user()->foto_perfil)
                    <img src="{{ asset('storage/' . auth()->user()->foto_perfil) }}" alt="Foto" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                @else
                    <div class="avatar">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}{{ strtoupper(substr(strstr(auth()->user()->name ?? ' X', ' '), 1, 1)) }}
                    </div>
                @endif
                <div class="user-info">
                    <span class="user-name">{{ auth()->user()->name ?? 'Usuario' }}</span>
                    <span class="user-role">{{ auth()->user()->rol->nombre ?? 'Sin Rol' }}</span>
                </div>
            </a>
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
                <div style="position:relative;" id="notif-wrapper">
                    <button class="icon-btn" id="notif-toggle" style="position:relative;">
                        <i class="bi bi-bell-fill"></i>
                        <span id="notif-badge" style="display:none;position:absolute;top:2px;right:2px;width:18px;height:18px;border-radius:50%;background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;line-height:18px;text-align:center;">0</span>
                    </button>
                    <div id="notif-dropdown" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:360px;max-height:420px;overflow-y:auto;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e2e8f0);border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,0.12);z-index:9999;">
                        <div style="padding:.85rem 1rem;border-bottom:1px solid var(--border-color,#e2e8f0);display:flex;align-items:center;justify-content:space-between;">
                            <span style="font-weight:600;font-size:.95rem;color:var(--text-color);">Notificaciones</span>
                            <button id="notif-read-all" style="background:none;border:none;color:var(--primary-color,#0f1b4c);font-size:.8rem;cursor:pointer;font-weight:500;">Marcar todas</button>
                        </div>
                        <div id="notif-list" style="padding:.5rem;">
                            <p id="notif-empty" style="text-align:center;color:var(--text-muted,#94a3b8);font-size:.85rem;padding:2rem 1rem;">
                                <i class="bi bi-bell" style="font-size:1.5rem;display:block;margin-bottom:.5rem;opacity:.4;"></i>
                                Sin notificaciones nuevas
                            </p>
                        </div>
                    </div>
                </div>
                <a href="{{ route('perfil.show') }}" class="icon-btn" title="Mi Perfil" style="text-decoration:none;">
                    @if(auth()->user()->foto_perfil)
                        <img src="{{ asset('storage/' . auth()->user()->foto_perfil) }}" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                    @else
                        <i class="bi bi-person-circle"></i>
                    @endif
                </a>
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
            <a href="{{ route('mis-formularios.index') }}" class="bottom-nav-item {{ request()->routeIs('mis-formularios.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard-check-fill"></i>
                <span>Mis Formularios</span>
            </a>
            <a href="{{ route('proteccion-datos.index') }}" class="bottom-nav-item {{ request()->routeIs('proteccion-datos.*') ? 'active' : '' }}">
                <i class="bi bi-shield-lock-fill"></i>
                <span>ARCO</span>
            </a>
            <button class="bottom-nav-item" id="mobile-notif-toggle" style="position:relative;background:none;border:none;">
                <i class="bi bi-bell-fill"></i>
                <span id="mobile-notif-badge" style="display:none;position:absolute;top:2px;right:calc(50% - 16px);width:16px;height:16px;border-radius:50%;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;line-height:16px;text-align:center;">0</span>
                <span>Alertas</span>
            </button>
            <button class="bottom-nav-item menu-toggle-btn" id="mobile-nav-menu-btn">
                <i class="bi bi-three-dots"></i>
                <span>Más</span>
            </button>
        </div>
    </nav>

    {{-- Notification system --}}
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('notif-toggle');
        const dropdown = document.getElementById('notif-dropdown');
        const badge = document.getElementById('notif-badge');
        const mobileBadge = document.getElementById('mobile-notif-badge');
        const mobileToggle = document.getElementById('mobile-notif-toggle');
        const list = document.getElementById('notif-list');
        const empty = document.getElementById('notif-empty');
        const readAll = document.getElementById('notif-read-all');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!toggle) return;

        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const open = dropdown.style.display !== 'none';
            dropdown.style.display = open ? 'none' : 'block';
            dropdown.classList.remove('mobile-sheet');
            if (!open) loadNotifications();
        });

        if (mobileToggle) {
            mobileToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const open = dropdown.style.display !== 'none';
                if (open) {
                    dropdown.style.display = 'none';
                    dropdown.classList.remove('mobile-sheet');
                } else {
                    dropdown.style.display = 'block';
                    dropdown.classList.add('mobile-sheet');
                    loadNotifications();
                }
            });
        }

        document.addEventListener('click', function(e) {
            if (!document.getElementById('notif-wrapper')?.contains(e.target) && e.target !== mobileToggle && !mobileToggle?.contains(e.target)) {
                dropdown.style.display = 'none';
                dropdown.classList.remove('mobile-sheet');
            }
        });

        readAll.addEventListener('click', function() {
            fetch('{{ route("notificaciones.read-all") }}', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            }).then(() => { loadNotifications(); });
        });

        function loadNotifications() {
            fetch('{{ route("notificaciones.index") }}', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    badge.style.display = data.length > 0 ? 'block' : 'none';
                    badge.textContent = data.length;
                    if (mobileBadge) {
                        mobileBadge.style.display = data.length > 0 ? 'block' : 'none';
                        mobileBadge.textContent = data.length;
                    }
                    if (data.length === 0) {
                        empty.style.display = 'block';
                        list.querySelectorAll('.notif-item').forEach(el => el.remove());
                        return;
                    }
                    empty.style.display = 'none';
                    list.querySelectorAll('.notif-item').forEach(el => el.remove());
                    data.forEach(n => {
                        const d = n.data;
                        const div = document.createElement('div');
                        div.className = 'notif-item';
                        div.style.cssText = 'padding:.65rem .75rem;border-radius:10px;cursor:pointer;transition:background .15s;display:flex;gap:.65rem;align-items:flex-start;';
                        div.onmouseenter = () => div.style.background = 'var(--surface-bg,#f8fafc)';
                        div.onmouseleave = () => div.style.background = 'transparent';
                        const iconMap = { info:'bi-info-circle-fill', success:'bi-check-circle-fill', warning:'bi-exclamation-triangle-fill', danger:'bi-x-circle-fill' };
                        const colorMap = { info:'#3b82f6', success:'#10b981', warning:'#f59e0b', danger:'#ef4444' };
                        const type = d.type || 'info';
                        div.innerHTML = `
                            <div style="width:32px;height:32px;border-radius:8px;background:${colorMap[type]}15;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="bi ${iconMap[type]}" style="color:${colorMap[type]};font-size:.9rem;"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <p style="margin:0;font-size:.85rem;font-weight:500;color:var(--text-color);">${d.title || 'Notificación'}</p>
                                <p style="margin:.15rem 0 0;font-size:.78rem;color:var(--text-muted);line-height:1.4;">${d.message || ''}</p>
                                <span style="font-size:.7rem;color:var(--text-muted);opacity:.7;">${timeAgo(n.created_at)}</span>
                            </div>`;
                        div.addEventListener('click', () => {
                            fetch('/notificaciones/' + n.id + '/read', {
                                method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                            }).then(() => { div.remove(); loadNotifications(); if (d.url) window.location = d.url; });
                        });
                        list.appendChild(div);
                    });
                });
        }

        function timeAgo(dt) {
            const diff = (Date.now() - new Date(dt)) / 1000;
            if (diff < 60) return 'Ahora';
            if (diff < 3600) return Math.floor(diff/60) + ' min';
            if (diff < 86400) return Math.floor(diff/3600) + ' h';
            return Math.floor(diff/86400) + ' d';
        }

        // Initial badge load
        loadNotifications();
        setInterval(loadNotifications, 60000);
    });
    </script>

    {{-- RUT Formatter — auto-applies to any input[data-rut] --}}
    <script>
    (function(){
        function formatRut(value) {
            let clean = value.replace(/[^0-9kK]/g, '').toUpperCase();
            if (!clean) return '';
            // Separate body from dv
            let dv = clean.slice(-1);
            let body = clean.slice(0, -1);
            if (!body) return clean; // single char, no format yet
            // Add dots from right
            let formatted = '';
            let count = 0;
            for (let i = body.length - 1; i >= 0; i--) {
                formatted = body[i] + formatted;
                count++;
                if (count % 3 === 0 && i > 0) formatted = '.' + formatted;
            }
            return formatted + '-' + dv;
        }

        function handleRutInput(e) {
            const input = e.target;
            const pos = input.selectionStart;
            const oldLen = input.value.length;
            input.value = formatRut(input.value);
            const newLen = input.value.length;
            const newPos = Math.max(0, pos + (newLen - oldLen));
            input.setSelectionRange(newPos, newPos);
        }

        function initRutInputs() {
            document.querySelectorAll('[data-rut]').forEach(function(input) {
                if (input.dataset.rutInit) return;
                input.dataset.rutInit = '1';
                input.setAttribute('maxlength', '12');
                input.setAttribute('placeholder', '12.345.678-9');
                // Format existing value
                if (input.value) input.value = formatRut(input.value);
                input.addEventListener('input', handleRutInput);
                input.addEventListener('paste', function() {
                    setTimeout(function(){ input.value = formatRut(input.value); }, 0);
                });
            });
        }

        // Initialize on DOM ready and observe for dynamically added inputs
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initRutInputs);
        } else {
            initRutInputs();
        }
        new MutationObserver(initRutInputs).observe(document.body, { childList: true, subtree: true });
    })();
    </script>

    @stack('scripts')
</body>
</html>
