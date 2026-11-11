/**
 * Intérprete de tablas pegadas en Programación de Contenedores.
 *
 * Tres capas, de la más confiable a la más flexible:
 *  1. Encabezado: si la primera fila trae nombres de columna reconocibles (con sinónimos),
 *     se mapea por nombre, en cualquier orden.
 *  2. Plantilla alineada: sin encabezado, se usa la planilla de programación como
 *     plantilla y se alinea cada fila por el código de contenedor (o la fecha), lo que
 *     cubre tablas copiadas sin las primeras o últimas columnas.
 *  3. Contenido: si la plantilla deja datos fuera de lugar, se perfila cada columna por
 *     lo que contiene (contenedores, fechas, horas, meses, días, números, textos) y se
 *     asigna por tipo, sin importar el orden.
 * Lo que ninguna capa identifica se informa en `sinIdentificar`, para sugerir la IA.
 */
(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    root.SaepContenedoresParser = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
    const scheduleColumns = [
        'operacion',
        'bodega',
        'supervisor_nombre',
        'facturacion_mes',
        'fecha',
        'contenedor',
        'equipo_descarga',
        'hora_cita',
        'hora_inicio_descarga',
        'hora_termino_descarga',
        'item',
        'cajas',
        'pallets',
        'producto',
        'observacion',
        'anio',
        'mes',
        'semana',
        'dia',
        'dato',
        'fact_codigo',
    ];
    const FECHA_POS = scheduleColumns.indexOf('fecha');
    const CONTENEDOR_POS = scheduleColumns.indexOf('contenedor');

    const fieldLabels = {
        operacion: 'Operación',
        bodega: 'Bodega',
        supervisor_nombre: 'Supervisor',
        facturacion_mes: 'Mes facturación',
        fecha: 'Fecha',
        contenedor: 'Contenedor',
        equipo_descarga: 'Equipo',
        hora_cita: 'H. cita',
        hora_inicio_descarga: 'H. inicio',
        hora_termino_descarga: 'H. término',
        item: 'Ítem',
        cajas: 'Cajas',
        pallets: 'Pallets',
        producto: 'Producto',
        observacion: 'Observación',
        anio: 'Año',
        mes: 'Mes',
        semana: 'Semana',
        dia: 'Día',
        dato: 'Dato',
        fact_codigo: 'FACT',
    };

    // Claves ya normalizadas: minúsculas, sin tildes ni signos (ver normalizeHeader).
    const headerAliases = {
        operacion: 'operacion', cliente: 'operacion', operaciones: 'operacion', naviera: 'operacion',
        bodega: 'bodega', centro: 'bodega', cd: 'bodega', centrodistribucion: 'bodega', sucursal: 'bodega',
        destino: 'bodega', planta: 'bodega', centrodecosto: 'bodega', centrocosto: 'bodega',
        supequipo: 'supervisor_nombre', supervisor: 'supervisor_nombre', supervisorencargado: 'supervisor_nombre',
        encargado: 'supervisor_nombre', jefeturno: 'supervisor_nombre', responsable: 'supervisor_nombre', sup: 'supervisor_nombre',
        facturacion: 'facturacion_mes', facturacionmes: 'facturacion_mes', mesfacturacion: 'facturacion_mes',
        mesfact: 'facturacion_mes', mesdefacturacion: 'facturacion_mes',
        fecha: 'fecha', fechadescarga: 'fecha', fechallegada: 'fecha', fechaprogramada: 'fecha',
        fechacita: 'fecha', fechaprogramacion: 'fecha',
        contenedor: 'contenedor', contenedores: 'contenedor', container: 'contenedor', cont: 'contenedor',
        ncontenedor: 'contenedor', nrocontenedor: 'contenedor', numerocontenedor: 'contenedor',
        nodecontenedor: 'contenedor', ndecontenedor: 'contenedor', nrocont: 'contenedor', ncont: 'contenedor',
        edescarga: 'equipo_descarga', equipodescarga: 'equipo_descarga', hdescarga: 'equipo_descarga',
        equipo: 'equipo_descarga', cuadrilla: 'equipo_descarga', empresa: 'equipo_descarga',
        hcita: 'hora_cita', horacita: 'hora_cita', hicitacion: 'hora_cita', cita: 'hora_cita',
        horallegada: 'hora_cita', llegada: 'hora_cita', hllegada: 'hora_cita', horaprogramada: 'hora_cita',
        hidescarga: 'hora_inicio_descarga', inicio: 'hora_inicio_descarga', iniciodescarga: 'hora_inicio_descarga',
        horainicio: 'hora_inicio_descarga', hinicio: 'hora_inicio_descarga', horainiciodescarga: 'hora_inicio_descarga',
        htdescarga: 'hora_termino_descarga', termino: 'hora_termino_descarga', terminodescarga: 'hora_termino_descarga',
        horatermino: 'hora_termino_descarga', htermino: 'hora_termino_descarga', horafin: 'hora_termino_descarga',
        fin: 'hora_termino_descarga', horaterminodescarga: 'hora_termino_descarga', hfin: 'hora_termino_descarga',
        item: 'item', items: 'item', sku: 'item', skus: 'item', nitems: 'item',
        cajas: 'cajas', bultos: 'cajas', cantidadcajas: 'cajas', ncajas: 'cajas', cantidad: 'cajas', unidades: 'cajas',
        pallets: 'pallets', pallet: 'pallets', paletas: 'pallets', palets: 'pallets', npallets: 'pallets',
        producto: 'producto', productos: 'producto', descripcion: 'producto', mercaderia: 'producto',
        mercancia: 'producto', carga: 'producto', detalle: 'producto',
        observacion: 'observacion', observaciones: 'observacion', obs: 'observacion', comentario: 'observacion',
        comentarios: 'observacion', estado: 'observacion', estatus: 'observacion', status: 'observacion',
        ano: 'anio', anio: 'anio', year: 'anio',
        mes: 'mes',
        semana: 'semana', nsemana: 'semana', sem: 'semana',
        dia: 'dia', diasemana: 'dia', diadelasemana: 'dia',
        dato: 'dato',
        fact: 'fact_codigo', factcodigo: 'fact_codigo', codigofact: 'fact_codigo', codfact: 'fact_codigo',
        tarifa: 'fact_codigo', codigotarifa: 'fact_codigo',
    };

    const CONTAINER_RE = /^[A-Z]{4}\s?-?\d{6}\s?-?\d$/i;
    const DATE_RE = /^(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}|\d{4}[\/\-.]\d{1,2}[\/\-.]\d{1,2})(\s+\d{1,2}:\d{2}(:\d{2})?)?$/;
    const TIME_RE = /^\d{1,2}:\d{2}(:\d{2})?(\s*[ap]\.?\s?m\.?)?$/i;
    const INT_RE = /^\d{1,7}$/;
    const DEC_RE = /^\d{1,7}[.,]\d{1,3}$/;
    const YEAR_RE = /^20\d{2}$/;
    const FACT_RE = /^[A-Z]{2,5}-?\d{2,4}$/i;
    const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
        'septiembre', 'setiembre', 'octubre', 'noviembre', 'diciembre'];
    const WEEKDAYS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    const OBS_RE = /(descargad|^ok\b|pendiente|cancelad|reprogram|anulad|no lleg|en proceso|finalizad|terminad)/i;
    const TEAM_RE = /^saep\b/i;

    function normalizeText(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .trim();
    }

    function normalizeHeader(value) {
        return normalizeText(value).replace(/[°º#]/g, 'n').replace(/[^a-z0-9]/g, '');
    }

    function splitRow(line) {
        if (line.includes('\t')) return line.split('\t');
        if (line.includes(';')) return line.split(';');
        return line.split(',');
    }

    function headerKey(cell) {
        const key = normalizeHeader(cell);
        if (headerAliases[key]) return headerAliases[key];
        // "N° contenedor" → "nncontenedor"; "Nro. de contenedor" → "nrodecontenedor".
        const sinPrefijo = key.replace(/^(nn|n|nro|numero|num)(de)?/, '');
        if (sinPrefijo !== key && headerAliases[sinPrefijo]) return headerAliases[sinPrefijo];
        return null;
    }

    function buildHeaderIndexes(row) {
        return row.reduce((indexes, cell, index) => {
            const key = headerKey(cell);
            if (key && indexes[key] === undefined) indexes[key] = index;
            return indexes;
        }, {});
    }

    function hasRecognizableHeader(row) {
        const indexes = buildHeaderIndexes(row);
        const mapped = Object.keys(indexes).length;
        const anchor = indexes.fecha !== undefined || indexes.contenedor !== undefined
            || indexes.operacion !== undefined || indexes.fact_codigo !== undefined;
        // Un encabezado no trae datos: si la fila ya tiene un contenedor o una fecha, es data.
        const hasData = row.some(cell => isContainer(cell) || isDate(cell));
        return !hasData && anchor && (mapped >= 4 || (mapped >= 2 && indexes.contenedor !== undefined));
    }

    const isContainer = value => CONTAINER_RE.test(String(value || '').trim());
    const isDate = value => DATE_RE.test(String(value || '').trim());
    const isTime = value => TIME_RE.test(String(value || '').trim());

    function makeEmptyRow() {
        return { estado: 'borrador', participantes: [] };
    }

    /* ---------------- Capa 2: plantilla alineada ---------------- */

    function rowOffset(row) {
        const containerIdx = row.findIndex(isContainer);
        if (containerIdx !== -1) return containerIdx - CONTENEDOR_POS;
        const dateIdx = row.findIndex(isDate);
        if (dateIdx !== -1) return dateIdx - FECHA_POS;
        return null;
    }

    function mostCommon(values) {
        const counts = new Map();
        values.forEach(v => counts.set(v, (counts.get(v) || 0) + 1));
        let best = null;
        let bestCount = 0;
        counts.forEach((count, value) => {
            if (count > bestCount) { best = value; bestCount = count; }
        });
        return best;
    }

    function parseByTemplate(rows) {
        const offsets = rows.map(rowOffset);
        const fallback = mostCommon(offsets.filter(o => o !== null));
        const defaultOffset = fallback === null ? 0 : fallback;
        const items = rows.map((row, i) => {
            const offset = offsets[i] === null ? defaultOffset : offsets[i];
            const item = makeEmptyRow();
            scheduleColumns.forEach((key, pos) => { item[key] = row[pos + offset] || ''; });
            return item;
        });
        // Celdas con datos que quedan fuera de la plantilla.
        const maxLen = Math.max(...rows.map(r => r.length));
        const sinIdentificar = [];
        for (let col = 0; col < maxLen; col++) {
            const used = rows.every((row, i) => {
                const pos = col - (offsets[i] === null ? defaultOffset : offsets[i]);
                return pos >= 0 && pos < scheduleColumns.length || !String(row[col] || '').trim();
            });
            if (!used) sinIdentificar.push(col);
        }
        return { rows: items, mode: 'posicion', offset: defaultOffset, sinIdentificar };
    }

    /* ---------------- Capa 3: perfil por contenido ---------------- */

    function cellType(value) {
        const v = String(value || '').trim();
        if (!v) return null;
        const n = normalizeText(v);
        if (isContainer(v)) return 'container';
        if (isDate(v)) return 'date';
        if (isTime(v)) return 'time';
        if (YEAR_RE.test(v)) return 'year';
        if (INT_RE.test(v)) return 'int';
        if (DEC_RE.test(v)) return 'dec';
        if (MONTHS.includes(n)) return 'month';
        if (WEEKDAYS.includes(n)) return 'weekday';
        if (FACT_RE.test(v)) return 'fact';
        if (OBS_RE.test(n)) return 'obs';
        if (TEAM_RE.test(v)) return 'team';
        return 'text';
    }

    function profileColumns(rows) {
        const maxLen = Math.max(...rows.map(r => r.length));
        const columns = [];
        for (let col = 0; col < maxLen; col++) {
            const values = rows.map(r => String(r[col] || '').trim()).filter(Boolean);
            if (!values.length) { columns.push({ col, type: null, values }); continue; }
            const counts = {};
            values.forEach(v => { const t = cellType(v); counts[t] = (counts[t] || 0) + 1; });
            // Los años y números chicos también son enteros; se decide por mayoría.
            let type = Object.entries(counts).sort((a, b) => b[1] - a[1])[0][0];
            if (counts[type] / values.length < 0.6) {
                const numeric = (counts.int || 0) + (counts.dec || 0) + (counts.year || 0);
                type = numeric / values.length >= 0.6 ? 'int' : 'text';
            }
            const avgLen = values.reduce((s, v) => s + v.length, 0) / values.length;
            const maxNum = Math.max(...values.map(v => Number(String(v).replace(',', '.')) || 0));
            columns.push({ col, type, values, avgLen, maxNum });
        }
        return columns;
    }

    function parseByContent(rows, context = {}) {
        const columns = profileColumns(rows);
        const assign = {};
        const taken = new Set();
        const put = (field, column) => {
            if (!column || assign[field] !== undefined || taken.has(column.col)) return;
            assign[field] = column.col;
            taken.add(column.col);
        };
        const ofType = type => columns.filter(c => c.type === type && !taken.has(c.col));

        put('contenedor', ofType('container')[0]);
        put('fecha', ofType('date')[0]);
        const fechaCol = assign.fecha ?? assign.contenedor ?? -1;

        const times = ofType('time');
        if (times.length >= 3) {
            put('hora_cita', times[0]); put('hora_inicio_descarga', times[1]); put('hora_termino_descarga', times[2]);
        } else if (times.length === 2) {
            put('hora_inicio_descarga', times[0]); put('hora_termino_descarga', times[1]);
        } else if (times.length === 1) {
            put('hora_cita', times[0]);
        }

        put('dia', ofType('weekday')[0]);
        const months = ofType('month');
        months.forEach(m => put(m.col < fechaCol || fechaCol === -1 ? 'facturacion_mes' : 'mes', m));
        months.forEach(m => put('mes', m));

        const years = ofType('year');
        put('anio', years[0]);
        put('fact_codigo', ofType('fact')[0]);
        put('observacion', ofType('obs')[0]);
        put('equipo_descarga', ofType('team')[0]);

        // Semana: entero 1..53 inmediatamente después del año o del mes.
        const semanaAncla = assign.anio ?? assign.mes;
        if (semanaAncla !== undefined) {
            put('semana', columns.find(c => !taken.has(c.col) && c.type === 'int' && c.maxNum <= 53
                && c.col > semanaAncla && c.col <= semanaAncla + 2));
        }

        // Ítem, cajas y pallets: números después de las horas (o del contenedor), en orden.
        const afterCol = Math.max(assign.hora_termino_descarga ?? -1, assign.hora_inicio_descarga ?? -1,
            assign.hora_cita ?? -1, assign.contenedor ?? -1);
        const numbers = columns.filter(c => !taken.has(c.col) && (c.type === 'int' || c.type === 'dec' || c.type === 'year')
            && c.col > afterCol && (assign.anio === undefined || c.col < assign.anio)).slice(0, 3);
        if (numbers.length === 3) {
            put('item', numbers[0]); put('cajas', numbers[1]); put('pallets', numbers[2]);
        } else if (numbers.length === 2) {
            put('cajas', numbers[0]); put('pallets', numbers[1]);
        } else if (numbers.length === 1) {
            put('cajas', numbers[0]);
        }

        // Textos: el que coincide con un centro es la bodega; a la izquierda de la fecha van
        // operación / bodega / supervisor; a la derecha, el texto más largo es el producto.
        const texts = () => columns.filter(c => !taken.has(c.col) && c.type === 'text');
        const centerNames = (context.centers || []).map(normalizeText).filter(Boolean);
        if (centerNames.length) {
            put('bodega', texts().find(c => c.values.filter(v => {
                const n = normalizeText(v);
                return centerNames.some(name => name.includes(n) || n.includes(name));
            }).length / c.values.length >= 0.6));
        }
        const left = texts().filter(c => fechaCol !== -1 && c.col < fechaCol);
        ['operacion', 'bodega', 'supervisor_nombre'].filter(f => assign[f] === undefined)
            .forEach((field, i) => put(field, left[i]));
        const right = texts().filter(c => c.col > fechaCol).sort((a, b) => b.avgLen - a.avgLen);
        put('producto', right[0]);
        if (assign.equipo_descarga === undefined) {
            put('equipo_descarga', texts().find(c => c.col > (assign.contenedor ?? fechaCol) && c.col < (assign.hora_cita ?? assign.hora_inicio_descarga ?? Infinity) && c.avgLen <= 20));
        }

        const items = rows.map(row => {
            const item = makeEmptyRow();
            scheduleColumns.forEach(key => {
                item[key] = assign[key] === undefined ? '' : (row[assign[key]] || '');
            });
            return item;
        });
        const sinIdentificar = columns.filter(c => c.type && !taken.has(c.col)).map(c => c.col);

        return { rows: items, mode: 'contenido', offset: null, sinIdentificar, asignacion: assign };
    }

    /* ---------------- Validación ---------------- */

    /** Problemas visibles de una fila ya interpretada (columnas corridas, datos sin forma). */
    function rowIssues(item) {
        const issues = [];
        const contenedor = String(item.contenedor || '').trim();
        const fecha = String(item.fecha || '').trim();

        if (!fecha) issues.push('Sin fecha');
        else if (!isDate(fecha)) issues.push(`Fecha no válida: "${fecha}"`);

        if (!contenedor) issues.push('Sin contenedor');
        else if (isTime(contenedor) || isDate(contenedor) || /^\d+([.,]\d+)?$/.test(contenedor)) {
            issues.push(`Contenedor no válido: "${contenedor}"`);
        }

        ['hora_cita', 'hora_inicio_descarga', 'hora_termino_descarga'].forEach(key => {
            const value = String(item[key] || '').trim();
            if (value && !isTime(value)) issues.push(`Hora no válida en ${fieldLabels[key]}: "${value}"`);
        });

        return issues;
    }

    function countIssues(items) {
        return items.reduce((total, item) => total + rowIssues(item).length, 0);
    }

    /* ---------------- Entrada ---------------- */

    /**
     * @param {string} text tabla pegada
     * @param {{centers?: string[]}} context nombres de centros para reconocer la bodega
     * @returns {{rows: object[], mode: 'encabezado'|'posicion'|'contenido', offset: number|null, sinIdentificar: number[], filasConProblemas: number}}
     */
    function parse(text, context = {}) {
        const rows = String(text || '').replace(/\r/g, '').split('\n')
            .map(line => splitRow(line).map(cell => cell.trim()))
            .filter(row => row.some(cell => cell !== ''));

        const finish = result => ({
            ...result,
            filasConProblemas: result.rows.filter(item => rowIssues(item).length).length,
        });

        if (!rows.length) return finish({ rows: [], mode: 'posicion', offset: null, sinIdentificar: [] });

        if (hasRecognizableHeader(rows[0])) {
            const indexes = buildHeaderIndexes(rows[0]);
            const used = new Set(Object.values(indexes));
            const dataRows = rows.slice(1);
            return finish({
                rows: dataRows.map(row => {
                    const item = makeEmptyRow();
                    Object.entries(indexes).forEach(([key, index]) => { item[key] = row[index] || ''; });
                    return item;
                }),
                mode: 'encabezado',
                offset: null,
                sinIdentificar: rows[0].map((cell, i) => (!used.has(i) && dataRows.some(r => String(r[i] || '').trim()) ? i : null))
                    .filter(i => i !== null),
                encabezadosIgnorados: rows[0].filter((cell, i) => !used.has(i) && String(cell).trim()),
            });
        }

        const template = parseByTemplate(rows);
        if (countIssues(template.rows) === 0 && template.sinIdentificar.length === 0) return finish(template);

        const content = parseByContent(rows, context);
        const better = countIssues(content.rows) < countIssues(template.rows)
            || (countIssues(content.rows) === countIssues(template.rows) && content.sinIdentificar.length < template.sinIdentificar.length);

        return finish(better ? content : template);
    }

    return { parse, rowIssues, isContainer, isDate, isTime, scheduleColumns, fieldLabels };
});
