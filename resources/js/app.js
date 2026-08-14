import './bootstrap';
import '../css/app.css';

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const darkModeBtn = document.getElementById('dark-mode-toggle');
    const overlay = document.getElementById('sidebar-overlay');
    const mobileMenuTrigger = document.getElementById('mobile-menu-trigger');
    const mobileNavMenuBtn = document.getElementById('mobile-nav-menu-btn');
    const sidebarNav = sidebar?.querySelector('.sidebar-nav');
    const navSections = Array.from(document.querySelectorAll('.nav-section'));
    const isMobile = () => window.innerWidth < 768;
    const sectionIcons = {
        operaciones: 'bi-box-seam',
        bodega: 'bi-boxes',
        formularios: 'bi-clipboard-check',
        sst: 'bi-shield-check',
        rrhh: 'bi-people',
        comercial: 'bi-graph-up-arrow',
        administracion: 'bi-sliders2',
        sistema: 'bi-gear',
        proteccion: 'bi-shield-lock',
        ayuda: 'bi-question-circle',
        herramientas: 'bi-tools',
    };
    const sectionStorageKey = 'saep_sidebar_open_section';
    let openFlyout = null;
    let tooltipTarget = null;
    const flyoutLayer = document.createElement('div');
    const tooltipLayer = document.createElement('div');

    flyoutLayer.className = 'sidebar-flyout-layer';
    flyoutLayer.setAttribute('aria-live', 'polite');
    tooltipLayer.className = 'sidebar-tooltip-layer';
    tooltipLayer.setAttribute('role', 'tooltip');
    document.body.append(flyoutLayer, tooltipLayer);

    function updateMobileUI() {
        if (mobileMenuTrigger) {
            mobileMenuTrigger.style.display = isMobile() ? 'flex' : 'none';
        }
    }

    function updateToggleButton() {
        if (!toggleBtn || !sidebar) return;
        const collapsed = sidebar.classList.contains('collapsed');
        toggleBtn.setAttribute('aria-expanded', String(!collapsed));
        toggleBtn.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Contraer menú');
    }

    function openMobile() {
        sidebar?.classList.add('open');
        if (overlay) overlay.style.display = 'block';
    }

    function closeMobile() {
        sidebar?.classList.remove('open');
        if (overlay) overlay.style.display = 'none';
    }

    function closeFlyout() {
        if (!openFlyout && !flyoutLayer.classList.contains('is-open')) return;
        openFlyout?.classList.remove('is-flyout-open');
        openFlyout = null;
        flyoutLayer.classList.remove('is-open');
        flyoutLayer.replaceChildren();
    }

    function hideTooltip() {
        tooltipTarget = null;
        tooltipLayer.classList.remove('is-open');
        tooltipLayer.textContent = '';
    }

    function showTooltip(target) {
        const title = target.dataset.tooltip;
        if (!title || !sidebar?.classList.contains('collapsed') || isMobile()) return;

        tooltipTarget = target;
        tooltipLayer.textContent = title;
        tooltipLayer.classList.add('is-open');
        requestAnimationFrame(() => {
            if (tooltipTarget !== target || !sidebar) return;
            const sidebarBounds = sidebar.getBoundingClientRect();
            const targetBounds = target.getBoundingClientRect();
            const tooltipBounds = tooltipLayer.getBoundingClientRect();
            const top = Math.min(
                Math.max(8, targetBounds.top + (targetBounds.height / 2) - (tooltipBounds.height / 2)),
                Math.max(8, window.innerHeight - tooltipBounds.height - 8),
            );
            tooltipLayer.style.setProperty('--sidebar-tooltip-left', `${Math.round(sidebarBounds.right + 12)}px`);
            tooltipLayer.style.setProperty('--sidebar-tooltip-top', `${Math.round(top)}px`);
        });
    }

    function positionFlyout(section) {
        const trigger = section.querySelector('.nav-section-toggle');
        if (!sidebar || !trigger) return;
        const sidebarBounds = sidebar.getBoundingClientRect();
        const triggerBounds = trigger.getBoundingClientRect();
        const left = Math.round(sidebarBounds.right + 12);
        const width = Math.max(170, Math.min(300, window.innerWidth - left - 14));
        flyoutLayer.style.setProperty('--nav-flyout-left', `${left}px`);
        flyoutLayer.style.setProperty('--nav-flyout-width', `${width}px`);

        const panelBounds = flyoutLayer.getBoundingClientRect();
        const top = Math.min(
            Math.max(12, triggerBounds.top - 8),
            Math.max(12, window.innerHeight - panelBounds.height - 12),
        );
        flyoutLayer.style.setProperty('--nav-flyout-top', `${Math.round(top)}px`);
    }

    function openFlyoutPanel(section) {
        const title = section.querySelector('.nav-section-toggle span')?.textContent.trim() || 'Opciones';
        const heading = document.createElement('span');
        const links = document.createElement('div');
        heading.className = 'sidebar-flyout-title';
        heading.textContent = title;
        links.className = 'sidebar-flyout-links';
        section.querySelectorAll('.nav-section-items > a.nav-item').forEach((link) => {
            const clone = link.cloneNode(true);
            clone.addEventListener('click', closeFlyout);
            links.append(clone);
        });
        flyoutLayer.replaceChildren(heading, links);
        flyoutLayer.setAttribute('aria-label', `Opciones de ${title}`);
        flyoutLayer.classList.add('is-open');
        section.classList.add('is-flyout-open');
        openFlyout = section;
        requestAnimationFrame(() => positionFlyout(section));
    }

    function setSectionOpen(section, open) {
        if (open) {
            navSections.forEach((otherSection) => {
                if (otherSection !== section) {
                    otherSection.classList.remove('is-open');
                    otherSection.querySelector('.nav-section-toggle')?.setAttribute('aria-expanded', 'false');
                }
            });
            localStorage.setItem(sectionStorageKey, section.dataset.navSection || '');
        } else if (localStorage.getItem(sectionStorageKey) === section.dataset.navSection) {
            localStorage.removeItem(sectionStorageKey);
        }

        section.classList.toggle('is-open', open);
        section.querySelector('.nav-section-toggle')?.setAttribute('aria-expanded', String(open));
    }

    function prepareSidebarSections() {
        navSections.forEach((section) => {
            const trigger = section.querySelector('.nav-section-toggle');
            if (!trigger) return;
            const title = trigger.querySelector('span')?.textContent.trim() || 'Opciones';
            const icon = document.createElement('i');
            icon.className = `bi ${sectionIcons[section.dataset.navSection] || 'bi-grid-3x3-gap'} nav-section-icon`;
            icon.setAttribute('aria-hidden', 'true');
            trigger.prepend(icon);
            trigger.dataset.tooltip = title;
            trigger.setAttribute('aria-expanded', 'false');
            if (section.querySelector('.nav-item.active')) section.classList.add('has-active-item');

            trigger.addEventListener('click', () => {
                hideTooltip();
                if (!isMobile() && sidebar?.classList.contains('collapsed')) {
                    const isCurrentFlyout = openFlyout === section;
                    closeFlyout();
                    if (!isCurrentFlyout) openFlyoutPanel(section);
                    return;
                }
                setSectionOpen(section, !section.classList.contains('is-open'));
            });

            section.querySelectorAll('.nav-section-items > a.nav-item').forEach((link) => {
                link.addEventListener('click', closeFlyout);
            });
        });

        sidebarNav?.querySelectorAll(':scope > .nav-item').forEach((link) => {
            const title = link.querySelector('span')?.textContent.trim();
            if (title) link.dataset.tooltip = title;
        });

        const tooltipTargets = [
            ...navSections.map((section) => section.querySelector('.nav-section-toggle')).filter(Boolean),
            ...Array.from(sidebarNav?.querySelectorAll(':scope > .nav-item[data-tooltip]') || []),
        ];
        tooltipTargets.forEach((target) => {
            target.addEventListener('mouseenter', () => showTooltip(target));
            target.addEventListener('mouseleave', hideTooltip);
            target.addEventListener('focus', () => showTooltip(target));
            target.addEventListener('blur', hideTooltip);
        });

        const activeSection = navSections.find((section) => section.querySelector('.nav-item.active'));
        const rememberedSection = navSections.find((section) => section.dataset.navSection === localStorage.getItem(sectionStorageKey));
        if (activeSection || rememberedSection) setSectionOpen(activeSection || rememberedSection, true);
    }

    updateMobileUI();
    prepareSidebarSections();
    window.addEventListener('resize', () => {
        updateMobileUI();
        closeFlyout();
        hideTooltip();
    });
    window.addEventListener('scroll', closeFlyout, true);
    document.addEventListener('pointerdown', (event) => {
        if (openFlyout && !openFlyout.contains(event.target) && !flyoutLayer.contains(event.target)) closeFlyout();
    });

    if (toggleBtn && sidebar) {
        if (!isMobile() && localStorage.getItem('sidebar_collapsed') === 'true') sidebar.classList.add('collapsed');
        updateToggleButton();
        toggleBtn.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.contains('open') ? closeMobile() : openMobile();
                return;
            }
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebar_collapsed', String(sidebar.classList.contains('collapsed')));
            closeFlyout();
            hideTooltip();
            updateToggleButton();
        });
    }

    if (overlay) overlay.addEventListener('click', closeMobile);

    if (sidebarNav) {
        const sidebarScrollKey = 'saep_sidebar_nav_scroll';
        const savedSidebarScroll = localStorage.getItem(sidebarScrollKey);
        if (savedSidebarScroll !== null) {
            requestAnimationFrame(() => {
                sidebarNav.scrollTop = parseInt(savedSidebarScroll, 10) || 0;
            });
        }
        const saveSidebarScroll = () => localStorage.setItem(sidebarScrollKey, String(sidebarNav.scrollTop));
        sidebarNav.addEventListener('scroll', saveSidebarScroll, { passive: true });
        document.addEventListener('submit', saveSidebarScroll);
        document.addEventListener('click', (event) => {
            if (event.target.closest('.sidebar a, .sidebar button, button[type="submit"], a[data-method]')) saveSidebarScroll();
        });
        window.addEventListener('beforeunload', saveSidebarScroll);
    }

    if (mobileMenuTrigger) {
        mobileMenuTrigger.addEventListener('click', () => {
            sidebar?.classList.contains('open') ? closeMobile() : openMobile();
        });
    }

    if (mobileNavMenuBtn) {
        mobileNavMenuBtn.addEventListener('click', () => {
            sidebar?.classList.contains('open') ? closeMobile() : openMobile();
        });
    }

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
        }
    }
});
