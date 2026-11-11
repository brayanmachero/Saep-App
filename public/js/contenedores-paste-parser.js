/**
 * Intérprete de tablas pegadas en Programación de Contenedores.
 *
 * Con encabezado reconocible se mapea por nombre de columna. Sin encabezado se usa la
 * planilla de programación como plantilla, pero alineada por contenido: el código de
 * contenedor (AAAA1234567) y la fecha son anclas, de modo que una tabla copiada sin las
 * primeras columnas (OPERACIÓN, BODEGA, SUP/EQUIPO) o sin las últimas no queda corrida.
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

    const headerAliases = {
        operacion: 'operacion',
        bodega: 'bodega',
        supequipo: 'supervisor_nombre',
        supervisor: 'supervisor_nombre',
        supervisorencargado: 'supervisor_nombre',
        facturacion: 'facturacion_mes',
        facturacionmes: 'facturacion_mes',
        mesfacturacion: 'facturacion_mes',
        fecha: 'fecha',
        contenedor: 'contenedor',
        edescarga: 'equipo_descarga',
        equipodescarga: 'equipo_descarga',
        hdescarga: 'equipo_descarga',
        hcita: 'hora_cita',
        horacita: 'hora_cita',
        hicitacion: 'hora_cita',
        hidescarga: 'hora_inicio_descarga',
        inicio: 'hora_inicio_descarga',
        iniciodescarga: 'hora_inicio_descarga',
        horainicio: 'hora_inicio_descarga',
        htdescarga: 'hora_termino_descarga',
        termino: 'hora_termino_descarga',
        terminodescarga: 'hora_termino_descarga',
        horatermino: 'hora_termino_descarga',
        item: 'item',
        items: 'item',
        cajas: 'cajas',
        pallets: 'pallets',
        producto: 'producto',
        productos: 'producto',
        observacion: 'observacion',
        obs: 'observacion',
        ano: 'anio',
        anio: 'anio',
        mes: 'mes',
        semana: 'semana',
        dia: 'dia',
        dato: 'dato',
        fact: 'fact_codigo',
        factcodigo: 'fact_codigo',
        codigofact: 'fact_codigo',
    };

    const CONTAINER_RE = /^[A-Z]{4}\s?-?\d{6}\s?-?\d$/i;
    const DATE_RE = /^(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}|\d{4}[\/\-.]\d{1,2}[\/\-.]\d{1,2})(\s+\d{1,2}:\d{2}(:\d{2})?)?$/;
    const TIME_RE = /^\d{1,2}:\d{2}(:\d{2})?(\s*[ap]\.?\s?m\.?)?$/i;

    function normalizeText(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .trim();
    }

    function normalizeHeader(value) {
        return normalizeText(value).replace(/[^a-z0-9]/g, '');
    }

    function splitRow(line) {
        if (line.includes('\t')) return line.split('\t');
        if (line.includes(';')) return line.split(';');
        return line.split(',');
    }

    function buildHeaderIndexes(row) {
        return row.reduce((indexes, cell, index) => {
            const key = headerAliases[normalizeHeader(cell)];
            if (key && indexes[key] === undefined) indexes[key] = index;
            return indexes;
        }, {});
    }

    function hasRecognizableHeader(row) {
        const indexes = buildHeaderIndexes(row);
        return Object.keys(indexes).length >= 4 && (
            indexes.fecha !== undefined
            || indexes.contenedor !== undefined
            || indexes.operacion !== undefined
            || indexes.fact_codigo !== undefined
        );
    }

    const isContainer = value => CONTAINER_RE.test(String(value || '').trim());
    const isDate = value => DATE_RE.test(String(value || '').trim());
    const isTime = value => TIME_RE.test(String(value || '').trim());

    /**
     * Desplazamiento de la fila respecto de la plantilla (fila[pos + offset] = columna pos),
     * o null si la fila no trae anclas reconocibles.
     */
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

    function makeEmptyRow() {
        return { estado: 'borrador', participantes: [] };
    }

    function parseRowByPosition(row, offset) {
        const item = makeEmptyRow();
        scheduleColumns.forEach((key, pos) => {
            item[key] = row[pos + offset] || '';
        });
        return item;
    }

    function parseRowByHeaders(row, indexes) {
        const item = makeEmptyRow();
        Object.entries(indexes).forEach(([key, index]) => {
            item[key] = row[index] || '';
        });
        return item;
    }

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
            if (value && !isTime(value)) issues.push(`Hora no válida en ${key.replace(/_/g, ' ')}: "${value}"`);
        });

        return issues;
    }

    /**
     * @returns {{rows: object[], mode: 'encabezado'|'posicion', offset: number|null}}
     */
    function parse(text) {
        const rows = String(text || '').replace(/\r/g, '').split('\n')
            .map(line => splitRow(line).map(cell => cell.trim()))
            .filter(row => row.some(cell => cell !== ''));

        if (!rows.length) return { rows: [], mode: 'posicion', offset: null };

        if (hasRecognizableHeader(rows[0])) {
            const indexes = buildHeaderIndexes(rows[0]);
            return {
                rows: rows.slice(1).map(row => parseRowByHeaders(row, indexes)),
                mode: 'encabezado',
                offset: null,
            };
        }

        const offsets = rows.map(rowOffset);
        const fallback = mostCommon(offsets.filter(o => o !== null));
        const defaultOffset = fallback === null ? 0 : fallback;

        return {
            rows: rows.map((row, i) => parseRowByPosition(row, offsets[i] === null ? defaultOffset : offsets[i])),
            mode: 'posicion',
            offset: defaultOffset,
        };
    }

    return { parse, rowIssues, isContainer, isDate, isTime, scheduleColumns };
});
