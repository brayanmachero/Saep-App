const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const rect = (top, left = 10, width = 200, height = 32) => ({
  top, left, width, height, bottom: top + height, right: left + width,
});

function setup() {
  const frames = [];
  const body = { tagName: 'BODY' };
  const scroller = {
    parentElement: body,
    style: { overflow: 'auto', overflowX: 'hidden', overflowY: 'auto', position: 'static' },
    rect: rect(100, 0, 600, 400),
    getBoundingClientRect() { return this.rect; },
  };
  const anchor = {
    parentElement: scroller,
    isConnected: true,
    visible: true,
    rect: rect(150),
    getClientRects() { return this.visible ? [this.rect] : []; },
    getBoundingClientRect() { return this.rect; },
  };
  const window = {
    innerWidth: 1280,
    innerHeight: 800,
    document: { body, documentElement: {} },
    getComputedStyle: el => el.style,
    requestAnimationFrame: fn => { frames.push(fn); return frames.length; },
    cancelAnimationFrame: () => {},
    setTimeout,
    clearTimeout,
  };
  window.window = window;
  vm.runInNewContext(fs.readFileSync('public/js/contenedores-floating-anchor.js', 'utf8'), window);
  const tick = () => { const fn = frames.shift(); if (fn) fn(); };
  return { api: window.SaepFloatingAnchor, anchor, scroller, tick, frames };
}

test('reposiciona solo cuando el campo se mueve', () => {
  const { api, anchor, tick } = setup();
  const moves = [];
  api.track(anchor, { onMove: r => moves.push(r.top), onLost: () => assert.fail('no debe cerrar') });
  tick();
  tick();
  assert.deepEqual(moves, [150]);
  anchor.rect = rect(190); // el panel se animó o cambió el contenido
  tick();
  assert.deepEqual(moves, [150, 190]);
});

test('cierra cuando el campo se oculta o sale del contenedor con scroll', () => {
  const hidden = setup();
  let lost = 0;
  hidden.api.track(hidden.anchor, { onMove() {}, onLost: () => lost++ });
  hidden.tick();
  hidden.anchor.visible = false; // pestaña cambiada (hidden)
  hidden.tick();
  assert.equal(lost, 1);
  assert.equal(hidden.frames.length, 0, 'deja de pedir cuadros');

  const scrolled = setup();
  let lostScroll = 0;
  scrolled.api.track(scrolled.anchor, { onMove() {}, onLost: () => lostScroll++ });
  scrolled.tick();
  scrolled.anchor.rect = rect(60); // quedó sobre el encabezado del panel
  scrolled.tick();
  assert.equal(lostScroll, 1);
});

test('stop e isOpen detienen el seguimiento', () => {
  const { api, anchor, tick, frames } = setup();
  let open = true;
  api.track(anchor, { onMove() {}, onLost: () => assert.fail('no debe cerrar'), isOpen: () => open });
  tick();
  open = false;
  tick();
  assert.equal(frames.length, 0);

  const stop = api.track(anchor, { onMove() {}, onLost() {} });
  stop();
  tick();
  assert.equal(frames.length, 0);
});

test('isAnchorVisible respeta viewport y recortes', () => {
  const { api } = setup();
  const viewport = { width: 1280, height: 800 };
  assert.equal(api.isAnchorVisible(rect(150), [rect(100, 0, 600, 400)], viewport), true);
  assert.equal(api.isAnchorVisible(rect(900), [], viewport), false);
  assert.equal(api.isAnchorVisible(rect(520), [rect(100, 0, 600, 400)], viewport), false);
  assert.equal(api.isAnchorVisible(rect(150, 10, 0, 0), [], viewport), false);
});
