import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../public/js/charla-destinatarios.js', import.meta.url), 'utf8');
function fixture({ rejectClipboard = false, count = 12 } = {}) {
    const listeners = {};
    const drawn = [];
    let downloaded = false;
    let copied = false;
    const elements = Object.fromEntries(['orden', 'copiar', 'estado', 'titulo'].map(id => [
        `destinatarios-${id}`, { addEventListener: (event, fn) => { listeners[`${id}:${event}`] = fn; } },
    ]));
    const select = elements['destinatarios-orden'];
    Object.defineProperty(select, 'selectedOptions', { get: () => [{ textContent: select.value }] });
    const rows = Array.from({ length: count }, (_, i) => ({
        cells: [`Persona ${String(i).padStart(3, '0')}`, '20', String(i % 21), '0', String(20 - i % 21), `${i % 21 * 5}%`]
            .map(textContent => ({ textContent })),
        dataset: { pendientes: 20 - i % 21 },
    }));
    const body = { rows, append(row) { this.rows = this.rows.filter(item => item !== row).concat(row); } };
    elements['destinatarios-tabla'] = { tBodies: [body], dataset: { periodo: 'Desde: 2026-10-01 · Hasta: 2026-10-20' } };
    const ctx = { measureText: text => ({ width: text.length * 8 }), fillRect() {}, fillText(text) { drawn.push(text); } };
    vm.runInNewContext(source, {
        document: {
            getElementById: id => elements[id],
            addEventListener: (_, fn) => fn(),
            createElement: tag => tag === 'canvas'
                ? { getContext: () => ctx, toBlob: fn => fn({ type: 'image/png' }) }
                : { click() { downloaded = true; } },
        },
        navigator: { clipboard: { async write(items) {
            if (rejectClipboard) throw new Error('denied');
            await items[0].data['image/png']; copied = true;
        } } },
        ClipboardItem: class { constructor(data) { this.data = data; } },
        URL: { createObjectURL: () => 'blob:test', revokeObjectURL() {} },
        setTimeout() {},
    });
    return {
        elements, drawn, body,
        visible: () => body.rows.filter(row => !row.hidden).map(row => row.cells[0].textContent),
        change(mode) { select.value = mode; listeners['orden:change'](); },
        copy: () => listeners['copiar:click'](),
        copied: () => copied, downloaded: () => downloaded,
    };
}
test('rankings cover recipients beyond the original ten and all mode restores every row', () => {
    const f = fixture();
    assert.equal(f.visible().length, 10);
    assert.equal(f.visible()[0], 'Persona 000');
    f.change('mayor');
    assert.equal(f.visible()[0], 'Persona 011');
    f.change('menor');
    assert.equal(f.visible()[0], 'Persona 000');
    f.change('todos');
    assert.equal(f.visible().length, 12);
});
test('PNG includes all selected rows and filter context', async () => {
    const f = fixture();
    f.change('todos');
    await f.copy();
    assert.equal(f.copied(), true);
    for (const name of f.visible()) assert.ok(f.drawn.includes(name));
    assert.ok(f.drawn.some(text => text.includes('2026-10-20')));
});
test('denied clipboard downloads PNG and restores controls', async () => {
    const f = fixture({ rejectClipboard: true });
    await f.copy();
    assert.equal(f.downloaded(), true);
    assert.equal(f.elements['destinatarios-copiar'].disabled, false);
    assert.match(f.elements['destinatarios-estado'].textContent, /descargó el PNG/);
});
test('oversized all export gives an actionable error without silently truncating rows', async () => {
    const f = fixture({ count: 400 });
    f.change('todos');
    await f.copy();
    assert.equal(f.copied(), false);
    assert.equal(f.downloaded(), false);
    assert.match(f.elements['destinatarios-estado'].textContent, /demasiado grande/);
});
