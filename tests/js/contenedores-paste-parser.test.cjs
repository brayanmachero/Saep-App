const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const sandbox = { window: {} };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/contenedores-paste-parser.js'), 'utf8'), sandbox);
const { parse, rowIssues } = sandbox.window.SaepContenedoresParser;

const tsv = rows => rows.map(r => r.join('\t')).join('\n');

const FULL = ['WALMART', 'EL PEÑÓN', 'RAFAEL MORENO', 'AGOSTO', '21-07-2026', 'GLDU7323259', 'SAEP 2',
    '22:00:00', '22:45:00', '1:15:00', '8', '480', '45', 'BAJ DE CAMAJUTE BOR', 'OK, DESCARGADO',
    '2026', 'JULIO', '30', 'MARTES', '11', 'CNT001'];

// Fila real de la carga 4 en producción: copiada sin OPERACIÓN, BODEGA ni SUP/EQUIPO.
const SIN_TRES_PRIMERAS = ['NOVIEMBRE', '02/11/2026', 'MSBU5332438', 'SAEP', '8:30:00', '8:40:00', '11:50:00',
    '2', '3875', '38', 'JUGUETES VARIOS', 'OK, DESCARGADO', '2026', 'NOVIEMBRE', '45', 'LUNES'];

test('planilla completa sin encabezado se mapea igual que antes', () => {
    const { rows, offset } = parse(tsv([FULL]));
    assert.strictEqual(offset, 0);
    assert.strictEqual(rows[0].operacion, 'WALMART');
    assert.strictEqual(rows[0].contenedor, 'GLDU7323259');
    assert.strictEqual(rows[0].fecha, '21-07-2026');
    assert.strictEqual(rows[0].fact_codigo, 'CNT001');
    assert.strictEqual(JSON.stringify(rowIssues(rows[0])), JSON.stringify([]));
});

test('tabla copiada sin las tres primeras columnas se alinea por contenedor', () => {
    const { rows, offset } = parse(tsv([SIN_TRES_PRIMERAS, SIN_TRES_PRIMERAS.map(c => c === 'MSBU5332438' ? 'MSMU6301712' : c)]));
    assert.strictEqual(offset, -3);
    const r = rows[0];
    assert.strictEqual(r.operacion, '');
    assert.strictEqual(r.bodega, '');
    assert.strictEqual(r.supervisor_nombre, '');
    assert.strictEqual(r.facturacion_mes, 'NOVIEMBRE');
    assert.strictEqual(r.fecha, '02/11/2026');
    assert.strictEqual(r.contenedor, 'MSBU5332438');
    assert.strictEqual(r.equipo_descarga, 'SAEP');
    assert.strictEqual(r.hora_cita, '8:30:00');
    assert.strictEqual(r.hora_inicio_descarga, '8:40:00');
    assert.strictEqual(r.hora_termino_descarga, '11:50:00');
    assert.strictEqual(r.item, '2');
    assert.strictEqual(r.cajas, '3875');
    assert.strictEqual(r.pallets, '38');
    assert.strictEqual(r.producto, 'JUGUETES VARIOS');
    assert.strictEqual(r.observacion, 'OK, DESCARGADO');
    assert.strictEqual(r.anio, '2026');
    assert.strictEqual(r.semana, '45');
    assert.strictEqual(r.dia, 'LUNES');
    assert.strictEqual(rows[1].contenedor, 'MSMU6301712');
    assert.strictEqual(JSON.stringify(rowIssues(r)), JSON.stringify([]));
});

test('fila sin contenedor se alinea por la fecha', () => {
    const fila = [...SIN_TRES_PRIMERAS];
    fila[2] = '';
    const { rows } = parse(tsv([fila]));
    assert.strictEqual(rows[0].fecha, '02/11/2026');
    assert.strictEqual(rows[0].hora_cita, '8:30:00');
    assert.strictEqual(JSON.stringify(rowIssues(rows[0])), JSON.stringify(['Sin contenedor']));
});

test('con encabezado se usa el encabezado', () => {
    const { rows, mode } = parse(tsv([['FECHA', 'CONTENEDOR', 'H. CITA', 'CAJAS'], ['02/11/2026', 'TXGU5578197', '8:55', '4652']]));
    assert.strictEqual(mode, 'encabezado');
    assert.strictEqual(rows[0].contenedor, 'TXGU5578197');
    assert.strictEqual(rows[0].cajas, '4652');
});

test('detecta datos corridos que no se pudieron alinear', () => {
    const issues = rowIssues({ fecha: '8:30:00', contenedor: '8:40:00', hora_cita: '2', hora_inicio_descarga: '', hora_termino_descarga: '' });
    assert.strictEqual(issues.length, 3);
});

const j = v => JSON.stringify(v);

test('sin encabezado y con columnas en otro orden se asigna por contenido', () => {
    const filas = [
        ['TXGU5578197', 'LUNES', '02/11/2026', '8:30', '8:55', '12:20', 'JUGUETES VARIOS', '24', '4652', '41', 'LTS PEÑON', 'OK, DESCARGADO', 'CNT001'],
        ['MSBU5332438', 'LUNES', '02/11/2026', '8:30', '8:40', '11:50', 'JUGUETES VARIOS', '2', '3875', '38', 'LTS PEÑON', 'OK, DESCARGADO', 'CNT001'],
    ];
    const res = parse(tsv(filas), { centers: ['LTS PEÑON SUB', 'MAERSK LA VARA SUB'] });
    assert.strictEqual(res.mode, 'contenido');
    const r = res.rows[0];
    assert.strictEqual(r.contenedor, 'TXGU5578197');
    assert.strictEqual(r.fecha, '02/11/2026');
    assert.strictEqual(r.dia, 'LUNES');
    assert.strictEqual(r.hora_cita, '8:30');
    assert.strictEqual(r.hora_inicio_descarga, '8:55');
    assert.strictEqual(r.hora_termino_descarga, '12:20');
    assert.strictEqual(r.producto, 'JUGUETES VARIOS');
    assert.strictEqual(r.item, '24');
    assert.strictEqual(r.cajas, '4652');
    assert.strictEqual(r.pallets, '41');
    assert.strictEqual(r.bodega, 'LTS PEÑON');
    assert.strictEqual(r.observacion, 'OK, DESCARGADO');
    assert.strictEqual(r.fact_codigo, 'CNT001');
    assert.strictEqual(res.filasConProblemas, 0);
    assert.strictEqual(j(res.sinIdentificar), j([]));
});

test('encabezados con sinónimos y en otro orden', () => {
    const res = parse(tsv([
        ['N° Contenedor', 'Fecha Llegada', 'Cliente', 'CD', 'Hora Llegada', 'H. Inicio', 'Hora Fin', 'Bultos', 'Paletas', 'Descripción', 'Comentarios', 'Patente'],
        ['MSMU6301712', '02-11-2026', 'MAERSK', 'LTS QUILICURA', '08:30', '12:10', '14:20', '6139', '43', 'JUGUETES', 'OK', 'ABCD12'],
    ]));
    assert.strictEqual(res.mode, 'encabezado');
    const r = res.rows[0];
    assert.strictEqual(r.contenedor, 'MSMU6301712');
    assert.strictEqual(r.fecha, '02-11-2026');
    assert.strictEqual(r.operacion, 'MAERSK');
    assert.strictEqual(r.bodega, 'LTS QUILICURA');
    assert.strictEqual(r.hora_cita, '08:30');
    assert.strictEqual(r.hora_inicio_descarga, '12:10');
    assert.strictEqual(r.hora_termino_descarga, '14:20');
    assert.strictEqual(r.cajas, '6139');
    assert.strictEqual(r.pallets, '43');
    assert.strictEqual(r.producto, 'JUGUETES');
    assert.strictEqual(r.observacion, 'OK');
    assert.strictEqual(j(res.encabezadosIgnorados), j(['Patente']));
    assert.strictEqual(j(res.sinIdentificar), j([11]));
});

test('una fila de datos no se confunde con encabezado', () => {
    const res = parse(tsv([['MAERSK', 'BODEGA', 'FECHA', '02/11/2026', 'TXGU5578197']]));
    assert.notStrictEqual(res.mode, 'encabezado');
    assert.strictEqual(res.rows.length, 1);
});

test('texto que no es una tabla queda con problemas para sugerir IA', () => {
    const res = parse('Hola equipo, mañana llegan 2 contenedores a Quilicura a las 9 y a las 11');
    assert.strictEqual(res.filasConProblemas, 1);
});
