const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup() {
  const element = (value = '') => ({value, handlers: {}, addEventListener(name, fn) {this.handlers[name] = fn;}, dispatchEvent(e) {this.handlers[e.type]?.({...e, target: this});}, focus() {}});
  const elements = {};
  for (const name of ['coordinador_nombre_origen', 'cargo_contacto', 'correo_contacto', 'telefono_contacto', 'jefe_operaciones']) elements[name] = element();
  elements.correo_contacto.value = 'propio@example.com';
  elements.crear_coordinador = {...element(), checked: false};
  const profile = {nombre: 'Ana', cargo: 'Coordinadora', correo: 'ana@example.com', telefono: '111', jefe_operaciones: 'Jefa'};
  elements.coordinador_id = {...element('1'), selectedOptions: [{dataset: {profile: JSON.stringify(profile)}}], options: []};
  vm.runInNewContext(fs.readFileSync('public/js/inventario-center-editor.js', 'utf8'), {document: {querySelectorAll: () => [{elements}]}, Event: class {constructor(type) {this.type = type;}}});
  return elements;
}
test('precarga contactos conocidos y conserva un correo específico del centro', () => {
  const e = setup();
  assert.equal(e.cargo_contacto.value, 'Coordinadora');
  assert.equal(e.telefono_contacto.value, '111');
  assert.equal(e.correo_contacto.value, 'propio@example.com');
  e.telefono_contacto.value = '999';
  e.telefono_contacto.dispatchEvent({type: 'input'});
  e.coordinador_id.selectedOptions = [{dataset: {profile: JSON.stringify({nombre: 'Luis', cargo: 'Supervisor', telefono: '222'})}}];
  e.coordinador_id.dispatchEvent({type: 'change'});
  assert.equal(e.cargo_contacto.value, 'Supervisor');
  assert.equal(e.telefono_contacto.value, '999');
});
test('alta nueva limpia datos propuestos y conserva modificaciones manuales', () => {
  const e = setup();
  e.crear_coordinador.checked = true;
  e.crear_coordinador.dispatchEvent({type: 'change'});
  assert.equal(e.coordinador_id.value, '');
  assert.equal(e.coordinador_nombre_origen.value, '');
  assert.equal(e.correo_contacto.value, 'propio@example.com');
});
