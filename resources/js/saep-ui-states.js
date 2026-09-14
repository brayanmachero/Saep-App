import '../css/saep-ui-states.css';

const VALID_STATES = new Set(['loading', 'empty', 'error', 'content']);
const savedContents = new WeakMap();

const defaultCopy = {
    loading: {
        icon: 'arrow-repeat',
        title: 'Cargando información',
        message: 'Estamos preparando los datos solicitados.',
    },
    empty: {
        icon: 'inbox',
        title: 'No hay información para mostrar',
        message: 'Prueba cambiando los filtros o vuelve a intentarlo más tarde.',
    },
    error: {
        icon: 'exclamation-triangle',
        title: 'No fue posible cargar la información',
        message: 'La información no se modificó. Puedes volver a intentarlo.',
    },
};

function getElement(target) {
    if (typeof target === 'string') {
        return document.querySelector(target);
    }

    return target instanceof Element ? target : null;
}

function normalizeState(state) {
    return VALID_STATES.has(state) ? state : 'empty';
}

function createElement(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined && text !== null) element.textContent = text;
    return element;
}

/**
 * Renders a standard SAEP state inside a dedicated content region.
 * Calling `setState(region, 'content')` restores the original content.
 */
export function setState(target, state, options = {}) {
    const region = getElement(target);
    if (!region) return null;

    const normalizedState = normalizeState(state);
    if (normalizedState === 'content') {
        const original = savedContents.get(region);
        if (original !== undefined) {
            region.replaceChildren(...original.cloneNode(true).childNodes);
            savedContents.delete(region);
        }
        region.classList.remove('saep-data-state-host', 'is-loading', 'is-empty', 'is-error');
        region.removeAttribute('aria-busy');
        region.removeAttribute('data-saep-state');
        return region;
    }

    if (!savedContents.has(region)) {
        const snapshot = document.createDocumentFragment();
        snapshot.append(...Array.from(region.childNodes).map((node) => node.cloneNode(true)));
        savedContents.set(region, snapshot);
    }

    const copy = { ...defaultCopy[normalizedState], ...options };
    const statePanel = createElement('section', `saep-data-state is-${normalizedState}`);
    statePanel.dataset.saepState = normalizedState;
    statePanel.setAttribute('role', normalizedState === 'error' ? 'alert' : 'status');
    statePanel.setAttribute('aria-live', normalizedState === 'error' ? 'assertive' : 'polite');

    const icon = createElement('i', `bi bi-${copy.icon} saep-data-state__icon`);
    icon.setAttribute('aria-hidden', 'true');
    const body = createElement('div', 'saep-data-state__body');
    body.append(
        createElement('h3', 'saep-data-state__title', copy.title),
        createElement('p', 'saep-data-state__message', copy.message),
    );

    if (normalizedState === 'loading') {
        icon.classList.add('saep-data-state__spinner');
        statePanel.setAttribute('aria-busy', 'true');
    }

    if (normalizedState === 'error' && typeof copy.onRetry === 'function') {
        const retryButton = createElement('button', 'btn-ghost saep-data-state__action', copy.retryLabel || 'Reintentar');
        retryButton.type = 'button';
        retryButton.prepend(Object.assign(document.createElement('i'), { className: 'bi bi-arrow-clockwise' }));
        retryButton.addEventListener('click', () => copy.onRetry());
        body.append(retryButton);
    }

    statePanel.append(icon, body);
    region.replaceChildren(statePanel);
    region.classList.add('saep-data-state-host', `is-${normalizedState}`);
    region.dataset.saepState = normalizedState;
    region.toggleAttribute('aria-busy', normalizedState === 'loading');

    return statePanel;
}

/**
 * Small helper for pages that fetch a dedicated block of information.
 * It never changes the request itself; it only exposes loading/error/empty UI.
 */
export async function runWithState(target, request, options = {}) {
    const region = getElement(target);
    if (!region || typeof request !== 'function') return null;

    setState(region, 'loading', options.loading);

    try {
        const result = await request();
        const isEmpty = typeof options.isEmpty === 'function'
            ? options.isEmpty(result)
            : Array.isArray(result) && result.length === 0;

        if (isEmpty) {
            setState(region, 'empty', options.empty);
        } else if (typeof options.onSuccess === 'function') {
            await options.onSuccess(result);
            setState(region, 'content');
        } else {
            setState(region, 'content');
        }

        return result;
    } catch (error) {
        const message = error instanceof Error && error.message
            ? error.message
            : defaultCopy.error.message;

        setState(region, 'error', {
            ...options.error,
            message,
            onRetry: () => runWithState(region, request, options),
        });

        return null;
    }
}

function createRequestProgress() {
    let element = document.getElementById('saep-request-progress');
    if (!element) {
        element = document.createElement('div');
        element.id = 'saep-request-progress';
        element.className = 'saep-request-progress';
        element.setAttribute('role', 'progressbar');
        element.setAttribute('aria-label', 'Cargando información');
        element.setAttribute('aria-hidden', 'true');
        document.body.append(element);
    }

    return element;
}

function observeFetchRequests() {
    if (typeof window.fetch !== 'function' || window.fetch.__saepStateObserver) return;

    const nativeFetch = window.fetch.bind(window);
    let pendingRequests = 0;
    let showTimer = null;
    const progress = createRequestProgress();

    const show = () => {
        if (pendingRequests > 0) {
            progress.classList.add('is-visible');
            progress.setAttribute('aria-hidden', 'false');
        }
    };

    const hide = () => {
        if (pendingRequests !== 0) return;
        if (showTimer) {
            window.clearTimeout(showTimer);
            showTimer = null;
        }
        progress.classList.remove('is-visible');
        progress.setAttribute('aria-hidden', 'true');
    };

    const observedFetch = (...args) => {
        pendingRequests += 1;
        if (pendingRequests === 1) {
            showTimer = window.setTimeout(show, 180);
        }

        let request;
        try {
            request = nativeFetch(...args);
        } catch (error) {
            pendingRequests -= 1;
            hide();
            throw error;
        }

        return Promise.resolve(request).finally(() => {
            pendingRequests = Math.max(0, pendingRequests - 1);
            hide();
        });
    };

    observedFetch.__saepStateObserver = true;
    window.fetch = observedFetch;
}

function bindRetryEvents() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-saep-state-retry]');
        if (!trigger) return;

        const region = trigger.closest('[data-saep-state]');
        region?.dispatchEvent(new CustomEvent('saep:retry', {
            bubbles: true,
            detail: { event: trigger.dataset.saepRetryEvent || null },
        }));
    });
}

function canEnhanceEmptyState(element) {
    if (!(element instanceof HTMLElement) || element.dataset.saepEmptyEnhanced === 'true') return false;
    if (element.closest('[data-saep-state], pre, code, script, style, template')) return false;

    const isEmptyClass = Array.from(element.classList).some((className) => className.toLowerCase().includes('empty'));
    if (!isEmptyClass && element.tagName !== 'TD') return false;
    if (element.querySelector('table, form, input, select, textarea, button')) return false;

    const text = (element.textContent || '').replace(/\s+/g, ' ').trim();
    if (!text || text.length > 260) return false;

    return /^(aún no hay|aun no hay|no hay|sin datos|sin registros|no se encontraron|no existen)/i.test(text);
}

function enhanceEmptyState(element) {
    if (!canEnhanceEmptyState(element)) return;

    const originalNodes = Array.from(element.childNodes);
    const panel = createElement('section', 'saep-data-state saep-data-state--compact is-empty');
    panel.dataset.saepState = 'empty';
    panel.setAttribute('role', 'status');
    panel.setAttribute('aria-live', 'polite');

    const icon = createElement('i', 'bi bi-inbox saep-data-state__icon');
    icon.setAttribute('aria-hidden', 'true');
    const body = createElement('div', 'saep-data-state__body');
    body.append(createElement('h3', 'saep-data-state__title', 'Sin información disponible'));

    const message = createElement('div', 'saep-data-state__message');
    originalNodes.forEach((node) => {
        if (!(node instanceof HTMLElement) || !node.matches('i.bi, i[class*="bi-"]')) {
            message.append(node);
        }
    });
    body.append(message);
    panel.append(icon, body);

    element.replaceChildren(panel);
    element.dataset.saepEmptyEnhanced = 'true';
}

function observeEmptyStates() {
    const scan = (root) => {
        if (!(root instanceof HTMLElement)) return;
        enhanceEmptyState(root);
        root.querySelectorAll?.('td, [class*="empty"], [class*="Empty"]').forEach(enhanceEmptyState);
    };

    scan(document.body);
    new MutationObserver((records) => {
        records.forEach((record) => {
            record.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) scan(node);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
}

function observePageNavigation() {
    const progress = createRequestProgress();
    let showTimer = null;

    const show = () => {
        progress.classList.add('is-visible');
        progress.setAttribute('aria-hidden', 'false');
    };

    const schedule = () => {
        if (showTimer) return;
        showTimer = window.setTimeout(show, 120);
    };

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!link || link.target || link.hasAttribute('download')) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin || destination.href === window.location.href) return;

        schedule();
    });

    document.addEventListener('submit', (event) => {
        if (!event.defaultPrevented && !event.target.target && !event.target.hasAttribute('data-saep-skip-navigation-state')) {
            schedule();
        }
    });

    window.addEventListener('pageshow', () => {
        if (showTimer) {
            window.clearTimeout(showTimer);
            showTimer = null;
        }
        progress.classList.remove('is-visible');
        progress.setAttribute('aria-hidden', 'true');
    });
}

function initialize() {
    observeFetchRequests();
    observePageNavigation();
    bindRetryEvents();
    observeEmptyStates();

    window.SAEPDataState = Object.freeze({
        set: setState,
        run: runWithState,
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
} else {
    initialize();
}
