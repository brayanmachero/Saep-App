import './bootstrap';
import '../css/app.css';

document.addEventListener('DOMContentLoaded', () => {
    const sidebar  = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const darkModeBtn = document.getElementById('dark-mode-toggle');
    const overlay  = document.getElementById('sidebar-overlay');
    const mobileMenuTrigger = document.getElementById('mobile-menu-trigger');
    const mobileNavMenuBtn = document.getElementById('mobile-nav-menu-btn');

    const isMobile = () => window.innerWidth < 768;

    // Show/hide mobile hamburger button
    function updateMobileUI() {
        if (mobileMenuTrigger) {
            mobileMenuTrigger.style.display = isMobile() ? 'flex' : 'none';
        }
    }
    updateMobileUI();
    window.addEventListener('resize', updateMobileUI);

    function openMobile() {
        sidebar.classList.add('open');
        if (overlay) { overlay.style.display = 'block'; }
    }
    function closeMobile() {
        sidebar.classList.remove('open');
        if (overlay) { overlay.style.display = 'none'; }
    }

    // Sidebar Toggle (desktop collapse button)
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.contains('open') ? closeMobile() : openMobile();
            } else {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
            }
        });

        // Close mobile sidebar when overlay clicked
        if (overlay) {
            overlay.addEventListener('click', closeMobile);
        }

        // Restore desktop collapsed state
        if (!isMobile() && localStorage.getItem('sidebar_collapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    }

    // Mobile hamburger trigger (top header)
    if (mobileMenuTrigger) {
        mobileMenuTrigger.addEventListener('click', () => {
            sidebar.classList.contains('open') ? closeMobile() : openMobile();
        });
    }

    // Mobile bottom nav "Más" button
    if (mobileNavMenuBtn) {
        mobileNavMenuBtn.addEventListener('click', () => {
            sidebar.classList.contains('open') ? closeMobile() : openMobile();
        });
    }

    // Dark Mode Toggle
    if (darkModeBtn) {
        darkModeBtn.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('dark_mode', isDark);
            darkModeBtn.innerHTML = isDark ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-fill"></i>';
        });

        const savedDarkMode = localStorage.getItem('dark_mode');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedDarkMode === 'true' || (savedDarkMode === null && prefersDark)) {
            document.body.classList.add('dark-mode');
            darkModeBtn.innerHTML = '<i class="bi bi-sun-fill"></i>';
        } else {
            darkModeBtn.innerHTML = '<i class="bi bi-moon-fill"></i>';
        }
    }
});
