/*
 * Mantiene un menú flotante (position: fixed, montado en <body>) pegado a su
 * campo de origen mientras está abierto.
 *
 * Los menús de Contenedores se montan en <body> para que no los recorte el
 * panel lateral ni la tabla, pero entonces no siguen al campo cuando el panel
 * se anima, cambia de ancho, se reordena el contenido o se desplaza un
 * contenedor interno. Este helper revisa la posición del campo en cada cuadro
 * mientras el menú está abierto: si se movió, reposiciona; si el campo se
 * ocultó, se eliminó o salió del área visible de su contenedor, cierra.
 */
(function (global) {
    'use strict';

    if (global.SaepFloatingAnchor) return;

    const CLIP_OVERFLOW = /(auto|scroll|hidden|clip|overlay)/;
    const EDGE = 2;

    function clippingAncestors(anchor) {
        const result = [];
        let node = anchor.parentElement;
        while (node && node !== global.document.body && node !== global.document.documentElement) {
            const style = global.getComputedStyle(node);
            if (CLIP_OVERFLOW.test(`${style.overflow} ${style.overflowX} ${style.overflowY}`)) {
                result.push(node);
            }
            if (style.position === 'fixed') break;
            node = node.parentElement;
        }
        return result;
    }

    /** Pura: ¿el rectángulo del campo sigue visible dentro del viewport y de cada recorte? */
    function isAnchorVisible(rect, clipRects, viewport) {
        if (!rect || (rect.width <= 0 && rect.height <= 0)) return false;
        if (rect.bottom <= 0 || rect.top >= viewport.height) return false;
        if (rect.right <= 0 || rect.left >= viewport.width) return false;

        return (clipRects || []).every(clip =>
            rect.bottom > clip.top + EDGE
            && rect.top < clip.bottom - EDGE
            && rect.right > clip.left + EDGE
            && rect.left < clip.right - EDGE
        );
    }

    function rectKey(rect, viewport) {
        return [rect.top, rect.left, rect.width, rect.height, viewport.width, viewport.height]
            .map(value => Math.round(value))
            .join('|');
    }

    /**
     * Sigue al campo mientras el menú esté abierto.
     * options.onMove(rect): reposicionar el menú.
     * options.onLost(): cerrar el menú (el campo ya no está visible).
     * options.isOpen(): opcional; si devuelve false se deja de seguir.
     * Devuelve una función para detener el seguimiento.
     */
    function track(anchor, options) {
        const raf = global.requestAnimationFrame
            ? global.requestAnimationFrame.bind(global)
            : callback => global.setTimeout(callback, 16);
        const cancel = global.cancelAnimationFrame
            ? global.cancelAnimationFrame.bind(global)
            : global.clearTimeout.bind(global);
        const clips = clippingAncestors(anchor);
        let active = true;
        let handle = 0;
        let lastKey = '';

        function stop() {
            active = false;
            if (handle) cancel(handle);
            handle = 0;
        }

        function frame() {
            handle = 0;
            if (!active) return;

            if (options.isOpen && !options.isOpen()) {
                stop();
                return;
            }

            const viewport = { width: global.innerWidth, height: global.innerHeight };
            const connected = anchor.isConnected && anchor.getClientRects().length > 0;
            const rect = connected ? anchor.getBoundingClientRect() : null;

            if (!connected || !isAnchorVisible(rect, clips.map(el => el.getBoundingClientRect()), viewport)) {
                stop();
                options.onLost && options.onLost();
                return;
            }

            const key = rectKey(rect, viewport);
            if (key !== lastKey) {
                lastKey = key;
                options.onMove && options.onMove(rect);
            }

            handle = raf(frame);
        }

        handle = raf(frame);
        return stop;
    }

    global.SaepFloatingAnchor = { track, isAnchorVisible };
})(typeof window !== 'undefined' ? window : globalThis);
